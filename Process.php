<?php

namespace WowMediaLibraryFix;

class Process {
	public $last_processed_description = '';

	// config
	private $c_guid;
	private $c_posts_delete_with_missing_images;
	private $c_files_thumbnails;
	private $c_regenerate_metadata;

	private $wp_upload_dir;
	private $unreferenced_files;
	public $errors_count = 0;



	public function __construct( $status ) {
		$this->c_guid = $status['guid'];
		$this->c_posts_delete_with_missing_images =
			$status['posts_delete_with_missing_images'];
		$this->c_files_thumbnails = $status['files_thumbnails'];
		$this->c_regenerate_metadata = $status['regenerate_metadata'];
		$this->wp_upload_dir = wp_upload_dir();
		$this->log = new ProcessLogger(
			( $status['log_to'] == 'file' ),
			$status['log_verbose'],
			$this->wp_upload_dir
		);

		$this->unreferenced_files = new ProcessUnreferencedFiles(
			$status['files_unreferenced'],
			$status['used_index_files'] );

		if ( $status['posts_processed'] == 0 ) {
			// on start
			$this->log->clear();
			$this->unreferenced_files->clear();
		}
	}



	static public function posts_count() {
		global $wpdb;
		$sql = "SELECT COUNT(ID)
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'";
		return $wpdb->get_var( $sql );
	}



	public function get_post_after( $post_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment' AND ID > %d
			ORDER BY ID
			LIMIT 1", $post_id );

		return $wpdb->get_var( $sql );
	}



	public function notices() {
		return $this->log->notices;
	}



	public function used_index_files() {
		return $this->unreferenced_files->used_index_files;
	}



	public function process_post( $post_id ) {
		$this->last_processed_description = '';
		// $post->post_name = '';
		// wp_update_post( $post );
		// check presense of _wp_attached_file

		// don't process non-images
		$post = get_post( $post_id );
		if ( substr( $post->post_mime_type, 0, 6 ) != 'image/' ) {
			$filename = get_attached_file( $post_id );
			$meta = wp_get_attachment_metadata( $post_id );
			$this->unreferenced_files->mark_referenced_by_metadata(
				$this->wp_upload_dir, $filename, $meta );

			if ( $this->log->verbose ) {
				$this->log->log( $post_id, 'Not image attachment, skipping' );
			}

			$this->last_processed_description = 'non-image attachment ' . $post_id;
			return;
		}


		$filename = $this->get_attached_filename( $post );
		if ( is_null( $filename ) ) {
			if ( $this->c_posts_delete_with_missing_images ) {
				wp_delete_post( $post_id, true );
				$this->errors_count++;
				if ( $this->log->verbose ) {
					$this->log->log( $post_id,
						'Attachment deleted since image is missing' );
				}
			}

			return;
		}

		$this->maybe_update_guid( $post, $filename );

		$t = new ProcessUnreferencedThumbnails( $post_id, $this->log,
			$this->c_files_thumbnails );
		$t->find_thumbnails_of( $filename );

		$meta = $this->maybe_regenerate_metadata( $post_id, $filename );

		$t->match_with_metadata( $this->wp_upload_dir, $meta );
		$this->errors_count += $t->errors_count;

		$this->unreferenced_files->mark_referenced_by_metadata(
			$this->wp_upload_dir, $filename, $meta );

		$this->last_processed_description = str_replace(
			ABSPATH, '', $filename );
		return;
	}



	public function process_used_index_file() {
		$v = $this->unreferenced_files->process_used_index_file( $this->log,
			$this->wp_upload_dir );
		$this->errors_count += $v->errors_count;
		$v->errors_count = 0;

		return $v;
	}



	private function get_attached_filename( $post ) {
		$filename = get_attached_file( $post->ID );

		if ( !empty( $filename ) && file_exists( $filename ) ) {
			return $filename;
		}

		// if not present - try to find by guid
		if ( empty( $post->guid ) ) {
			$this->errors_count++;
			if ( $this->log->verbose ) {
				$this->log->log( $post->ID, 'Attachment has empty GUID' );
			}
			return null;
		}
		$baseurl = $this->wp_upload_dir['baseurl'];

		if ( Util::starts_with( $post->guid, $baseurl ) ) {
			$guid_filename_postfix = substr( $post->guid,
				strlen( $baseurl ) + 1 );
		} else {
			// try default uploads keyword
			$pos = strpos( $post->guid, '/uploads/' );

			if ( $pos === FALSE ) {
				$this->errors_count++;
				if ( $this->log->verbose ) {
					$this->log->log( $post->ID, "Attachment GUID doesnt allow to restore filename " . $post->guid );
				}
				return null;
			}

			$guid_filename_postfix = substr( $post->guid, $pos + 9 );
		}

		$guid_filename =  $this->wp_upload_dir['basedir'] .
			DIRECTORY_SEPARATOR . $guid_filename_postfix;

		if ( !file_exists( $guid_filename ) ) {
			$log_postfix = ( $guid_filename == $filename ? '' :
			 	" and '$guid_filename'" );

			$this->errors_count++;
			$this->log->log( $post->ID,
				"Image file referenced by attachment doesn't exists. Tried '$filename'$log_postfix" );
			$this->last_processed_description = $post->guid;
			return null;
		}

		$this->errors_count++;
		$this->log->log( $post->ID,
			"Restored image file from guid: '$guid_filename'" );
		update_post_meta( $post->ID, '_wp_attached_file',
			$guid_filename_postfix );
		return $guid_filename;
	}



	private function maybe_update_guid( $post, $filename ) {
		if ( empty( $this->c_guid ) ) {
			return;
		}

		$required_guid = wp_get_attachment_url( $post->ID );
		if ( $required_guid == $post->guid ) {
			return;
		}

		$this->errors_count++;
		if ( $this->c_guid == 'log' ) {
			$this->log->log( $post->ID,
				"Post GUID mismatch: Actual value '{$post->guid}', but normalized is '$required_guid'" );
		} elseif ( $this->c_guid == 'fix' ) {
			// wp_update_post won't change guid (and thats correct)
			global $wpdb;
			$wpdb->update( $wpdb->posts,
				array( 'guid' => $required_guid ),
				array( 'id' => $post->ID ) );

			if ( $this->log->verbose ) {
				$old_guid = $post->guid;
				$this->log->log( $post->ID,
					"Post GUID changed from '$old_guid' to '$required_guid'" );
			}
		}
	}



	private function maybe_regenerate_metadata( $post_id, $filename ) {
		if ( !$this->c_regenerate_metadata ) {
			return wp_get_attachment_metadata( $post_id );
		}

		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$meta = wp_generate_attachment_metadata( $post_id, $filename );
		wp_update_attachment_metadata( $post_id, $meta );

		if ( $this->log->verbose ) {
			$count = 0;
			if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				$count = count( $meta['sizes'] );
			}

			$filename_for_log = str_replace( ABSPATH, '', $filename );

			$this->log->log( $post_id,
				'Regenerated attachment metadata. ' .
				$count . ' thumbnails generated for ' . $filename_for_log );
		}

		return $meta;
	}
}

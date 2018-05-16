<?php

namespace WowMediaLibraryFix;

class AdminPage {
	static public function admin_print_styles() {
		wp_enqueue_style( 'wow_media_library_fix',
			plugin_dir_url( __FILE__ ) . 'AdminPage_View.css',
			array(), '1.0' );
	}



	static public function admin_print_scripts() {
		wp_enqueue_script( 'wow_media_library_fix',
			plugin_dir_url( __FILE__ ) . 'AdminPage_View.js',
			array( 'jquery' ), '1.0' );

		wp_localize_script( 'wow_media_library_fix', 'wow_media_library_fix_nonce',
			wp_create_nonce( 'wow_media_library_fix' ) );

		$status = Util::status();
		$value = 'start';

		if ( isset( $status['status'] ) && $status['status'] == 'working' ) {
			$value = 'paused';
		}
		wp_localize_script( 'wow_media_library_fix', 'wow_mlf_state', $value );
	}



	static public function render() {
		$status = Util::status();

		$hide = 'style="display: none"';

		$messages = '';
		$style_config = '';
		$style_start_outer = '';
		$style_continue_outer = $hide;
		$style_process = $hide;
		$style_working_now = '';
		$process_total = 'starting...';
		$process_processed = '0';
		$process_errors = '0';

		if ( isset( $status['status'] ) &&
				( $status['status'] == 'working_posts' ||
				$status['status'] == 'working_index_files' ) ) {
			$messages =
				'<div class="updated settings-error notice is-dismissible">' .
				'<p><strong>Previous processing was not finished. Continue execution now or start new processing.</strong></p></div>';
			$style_config = $hide;
			$style_start_outer = $hide;
			$style_continue_outer = '';
			$style_process = '';
			$style_working_now = $hide;
			$process_total = $status['posts_all'];
			$process_processed = $status['posts_processed'];
			$process_errors = $status['errors_count'];
		}

		include( __DIR__ . DIRECTORY_SEPARATOR . 'AdminPage_View.php' );
	}



	static public function wp_ajax_wow_media_library_fix_process() {
		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'wow_media_library_fix' ) ) {
			wp_nonce_ays( 'wow_media_library_fix' );
			exit;
		}
		if ( !current_user_can( 'manage_options') ) {
			wp_nonce_ays( 'wow_media_library_fix' );
			exit;
		}

		$secs_to_execute = 2;
		$time_end = time() + $secs_to_execute;
		$status = Util::status();

		if ( isset( $_REQUEST['wmlf_action'] ) ) {
			$action = $_REQUEST['wmlf_action'];
			if ( $action == 'start' ) {
				$status = array(
					'version' => '1.0',
					'guid' => $_REQUEST['guid'],
					'posts_delete_with_missing_images' =>
						( $_REQUEST['posts_delete_with_missing_images'] == 'true' ),
					'posts_delete_duplicate_url' =>
						$_REQUEST['posts_delete_duplicate_url'],
					'files_thumbnails' => $_REQUEST['files_thumbnails'],
					'files_unreferenced' => $_REQUEST['files_unreferenced'],
					'files_weak_references' =>
						$_REQUEST['files_weak_references'],
					'regenerate_metadata' =>
						( $_REQUEST['regenerate_metadata'] == 'true' ),
					'log_to' => $_REQUEST['log_to'],
					'log_verbose' =>
						( $_REQUEST['log_verbose'] == 'true' ),
					'posts_all' => Process::posts_count(),
					'posts_processed' => 0,
					'unreferenced_files_processed' => 0,
					'errors_count' => 0,
					'last_processed_id' => 0,
					'last_processed_description' => '',
					'used_index_files' => array(),
					'status' => 'working_posts'
				);
			}
		}


		//
		// init processors
		//
		$wp_upload_dir = wp_upload_dir();
		$log = new ProcessLogger(
			( $status['log_to'] == 'file' ),
			$status['log_verbose'],
			$wp_upload_dir
		);

		$process_unreferenced_files = new ProcessUnreferencedFiles( $status,
			$wp_upload_dir, $log );
		$process_post = new ProcessPost( $status, $wp_upload_dir, $log,
			$process_unreferenced_files );

		// on start
		if ( $status['posts_processed'] == 0 ) {
			$log->clear();
			$process_unreferenced_files->clear();
		}



		//
		// run processors
		//
		$last_processed_description = '';

		try {
			if ( $status['status'] == 'working_posts' ) {
				for (;;) {
					$post_id = $process_post->get_post_after( $status['last_processed_id'] );
					$status['posts_processed']++;
					if ( is_null( $post_id ) ) {
						$status['status'] = 'working_index_files';
						$status['posts_processed'] = $status['posts_all'];
						break;
					}

					$process_post->process_post( $post_id );
					$status['last_processed_id'] = $post_id;

					if ( time() >= $time_end ) {
						break;
					}
				}

				$last_processed_description = $process_post->last_processed_description;
			}
			if ( $status['status'] == 'working_index_files' ) {
				for (;;) {
					$filename = $process_unreferenced_files->process_next_file();
					$last_processed_description = $filename;
					$status['unreferenced_files_processed']++;
					if ( is_null( $index_file ) ) {
						$status['status'] = 'done';
						break;
					}

					if ( time() >= $time_end ) {
						break;
					}
				}
			}

			$status['errors_count'] += $process_post->errors_count;
			$status['errors_count'] += $process_unreferenced_files->errors_count;
			$status['used_index_files'] = $process_unreferenced_files->used_index_files;
			Util::status_set($status);
		} catch ( \Exception $e ) {
			die( $e->getMessage() );
		}

		echo json_encode(array(
			'posts_all' => $status['posts_all'],
			'posts_processed' => $status['posts_processed'],
			'errors_count' => $status['errors_count'],
			'last_processed_description' => $last_processed_description,
			'status' => $status['status'],
			'new_notices' => $log->notices
		));
		exit;
	}



	static private function message_saved() {
		if ( !isset( $_REQUEST['message'] ) ) {
			return '';
		}

		return '<div class="updated settings-error notice is-dismissible">' .
			'<p><strong>' .
			'Settings saved.' .
			'</strong></p>' .
			'<button type="button" class="notice-dismiss">' .
			'<span class="screen-reader-text">' .
			'Dismiss this notice.' .
			'</span></button></div>';
	}



	static private function message_errors( $c ) {
		$messages = array();

		if ( empty( $c['google_maps_api_key'] ) ) {
			$messages[] = '<p>' .
				'Google Maps API Key is required for mapping functionaliy. Please fill it.' .
				'</p>';
		}

		if ( empty( $messages ) ) {
			return '';
		}

		return '<div class="error fade"><p><strong>' .
			'Next problems found:' .
			'</strong></p>' . implode( $messages ) . '</div>';
	}
}

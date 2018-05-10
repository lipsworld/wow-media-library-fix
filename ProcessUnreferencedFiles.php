<?php

namespace WowMediaLibraryFix;

class ProcessUnreferencedFiles {
	private $c_files_unreferenced;
	public $used_index_files;
	public $errors_count = 0;


	public function __construct( $c_files_unreferenced, $used_index_files ) {
		$this->c_files_unreferenced = $c_files_unreferenced;

		if ( is_array( $used_index_files ) ) {
			$this->used_index_files = $used_index_files;
		} else {
			$this->used_index_files = array();
		}
	}



	public function clear() {
		foreach ( $this->used_index_files as $filename => $value ) {
			if ( file_exists( $filename ) ) {
				unlink( $filename );
			}
		}

		$used_index_files = array();
	}



	public function mark_referenced_by_metadata( $wp_upload_dir, $filename,
		$meta ) {
		if ( empty( $this->c_files_unreferenced ) ) {
			return;
		}

		$primary_filename = null;
		if ( isset( $meta['file'] ) ) {
			$primary_filename = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR .
				$meta['file'];
			$path = dirname( $primary_filename );

			$index_filename = $path . DIRECTORY_SEPARATOR . '.media-library-fix';
			$this->used_index_files[$index_filename] = '*';

			$content = array( basename( $primary_filename ) );

			if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $i ) {
					if ( isset( $i['file'] ) ) {
						$content[] = $i['file'];
					}
				}
			}

			file_put_contents( $index_filename,
				implode("\n", $content ) . "\n",
				FILE_APPEND );
		}

		// attachment filename may be present while meta not for non-images
		if ( $primary_filename != $filename ) {
			$path = dirname( $filename );

			$index_filename = $path . DIRECTORY_SEPARATOR . '.media-library-fix';
			$this->used_index_files[$index_filename] = '*';

			file_put_contents( $index_filename,
				basename( $filename ) . "\n",
				FILE_APPEND );
		}
	}



	public function process_used_index_file( $log, $wp_upload_dir ) {
		if ( empty( $this->c_files_unreferenced ) ) {
			return null;
		}

		// pop first key without array_keys
		$index_filename = null;
		foreach ( $this->used_index_files as $filename => $key ) {
			if ( file_exists( $filename ) ) {
				$index_filename = $filename;
			}
			break;
		}

		if ( is_null( $index_filename ) ) {
			return null;
		}

		unset( $this->used_index_files[$index_filename] );
		$path = dirname( $index_filename );

		$h = fopen( $index_filename, 'r' );
		if ( !$h ) {
			throw new \Exception( 'Faied to open ' . $index_filename );
		}

    	$used_basenames = array();
    	while ( ($line = fgets( $h ) ) !== false ) {
    		$line = trim( $line );
    		if ( !empty( $line ) ) {
        		$used_basenames[ $line ] = '*';
        	}
    	}

    	fclose( $h );

    	$used_basenames = apply_filters( 'wow_mlf_referenced_files',
    		$used_basenames, $path );

		$existing_basenames = scandir( $path );

		foreach ( $existing_basenames as $existing_basename ) {
			if ( $existing_basename == '.media-library-fix' ) {
			} elseif ( !is_dir( $path . DIRECTORY_SEPARATOR . $existing_basename ) ) {
				if ( !isset( $used_basenames[$existing_basename] ) ) {
					$this->process_unreferenced_file( $log, $wp_upload_dir,
						$path, $existing_basename );
				}
			}
		}

    	unlink( $index_filename );
    	return $index_filename;
	}



	public function process_unreferenced_file( $log, $wp_upload_dir,
			$path, $basename ) {
		$filename = $path . DIRECTORY_SEPARATOR . $basename;
		$filename_for_log = str_replace( ABSPATH, '', $filename );
		$this->errors_count++;

		if ( $this->c_files_unreferenced == 'log' ) {
			$log->log( null, 'Found unreferenced media library file ' .
				$filename_for_log );
		} elseif ( $this->c_files_unreferenced == 'move' ) {
			if ( $log->verbose ) {
				$log->log(null,
					'Move unreferenced media library file ' . $filename_for_log );
			}

			$path_postfix = substr( $path,
				strlen( $wp_upload_dir['basedir'] ) + 1 );
			$new_path = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR .
				'unreferenced' . DIRECTORY_SEPARATOR . $path_postfix;

			if ( !file_exists( $new_path ) ) {
				if ( !@mkdir( $new_path, 0777, true ) ) {
					$log->log( null,
						'Failed to create folder  ' . $new_path );
				}
			}

			if ( !@rename( $filename,
				$new_path . DIRECTORY_SEPARATOR . $basename ) ) {
				$log->log(null,
					'Failed to move unreferenced media library file ' .
					$filename_for_log );
			}
		} elseif ( $this->c_files_unreferenced == 'delete' ) {
			if ( $log->verbose ) {
				$log->log(null,
					'Delete unreferenced media library file ' .
					$filename_for_log );
			}

			if (!@unlink( $filename ) ) {
				$log->log(null,
					'Failed to delete unreferenced media library file ' .
					$filename_for_log );
			}
		}
	}
}

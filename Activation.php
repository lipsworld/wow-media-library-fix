<?php

namespace WowMediaLibraryFix;

class Activation {
	static public function deactivate() {
	    delete_option( 'wow_media_status' );

	    $wp_upload_dir = wp_upload_dir();
	    $log_to_file_filename = $wp_upload_dir['basedir'] .
			DIRECTORY_SEPARATOR . 'media-library.log';
		@unlink( $log_to_file_filename );
	}
}

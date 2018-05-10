<?php

namespace WowMediaLibraryFix;

class Util {
	static public function starts_with( $s, $prefix ) {
		return substr( $s, 0, strlen( $prefix ) ) == $prefix;
	}



	static public function config_key_to_id( $key ) {
		return 'wow_media_library_fix_option_' . str_replace( '.', '__', $key );
	}



	static public function status() {
		$v = get_option( 'wow_media_library_fix_status' );

		if ( !empty( $v ) ) {
			try {
				return json_decode( $v, true );
			} catch ( \Exception $error ) {
			}
		}

		return array(
			'version' => '1.0'
		);
	}



	static public function status_set( $v ) {
		update_option( 'wow_media_library_fix_status', json_encode( $v ), false );
	}
}

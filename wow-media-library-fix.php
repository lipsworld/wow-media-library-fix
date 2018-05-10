<?php
/*
Plugin Name: Fix Media Library
Plugin URI: https://wowpress.host/plugins/wow-
Description: Fixes Media Library
Version: 1.0
Author: WowPress.host
Author URI: https://wowpress.host
License: GPL2
*/

//TODO show image sizes
// allow to deregister
// overwrite post_name for non-unique
// https://wordpress.org/plugins/image-sizes/
// https://wordpress.org/plugins/fix-my-posts/
// check missing images
// report about gif and convert to png

if ( !defined( 'ABSPATH' ) ) {
	die();
}



/*
 * PSR-4 class autoloader
 */
function wow_media_library_fix_spl_autoload( $class ) {
	$class = rtrim( $class, '\\' );
	if ( substr( $class, 0, 19 ) == 'WowMediaLibraryFix\\' ) {
		$filename = __DIR__ . DIRECTORY_SEPARATOR .
			substr( $class, 19 ) . '.php';

		if ( file_exists( $filename ) ) {
			require $filename;
		}
	}
}

spl_autoload_register( 'wow_media_library_fix_spl_autoload' );



register_deactivation_hook( __FILE__,
	array( 'WowMediaLibraryFix\Activation', 'deactivate' ) );

add_action( 'admin_init', array( 'WowMediaLibraryFix\AdminInit', 'admin_init' ) );
add_action( 'admin_menu', array( 'WowMediaLibraryFix\AdminInit', 'admin_menu' ) );

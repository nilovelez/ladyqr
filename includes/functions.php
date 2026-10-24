<?php
/**
 * Small helpers shared by the editor and the admin.
 */

defined( 'LADYQR' ) || exit;

/**
 * Returns an asset path with its modification time, so browsers fetch a new
 * copy when the file changes. It is escaped for use in an HTML attribute.
 *
 * @param string $path   Path from the web root, such as assets/css/app.css.
 * @param string $prefix What to put in front of the path to reach the web root
 *                       from the current page, such as ../ in the admin.
 */
function ladyqr_asset( $path, $prefix = '' ) {
	$file = dirname( __DIR__ ) . '/' . $path;
	return htmlspecialchars( $prefix . $path . ( is_file( $file ) ? '?v=' . filemtime( $file ) : '' ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Tells whether the request came over HTTPS, also behind a proxy that ends the
 * TLS connection and says so in a header.
 */
function ladyqr_is_https() {
	if ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) ) {
		return true;
	}
	if ( isset( $_SERVER['SERVER_PORT'] ) && 443 === (int) $_SERVER['SERVER_PORT'] ) {
		return true;
	}
	return isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] );
}

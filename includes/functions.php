<?php
/**
 * Small helpers shared by the editor and the admin.
 */

defined( 'LADYQR' ) || exit;

/**
 * Returns an asset path with its modification time, so browsers fetch a new
 * copy when the file changes. It is escaped for use in an HTML attribute.
 *
 * @param string $path   Path from the web root, such as assets/app.css.
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

/**
 * Returns the path of the site on its host, such as /qr when LadyQR lives in a
 * folder, or an empty string at the root of the domain.
 *
 * @param int $levels How many folders the running script is below the web
 *                    root: 0 for index.php and 404.php, 1 for the admin.
 */
function ladyqr_base_path( $levels = 0 ) {
	$path = isset( $_SERVER['SCRIPT_NAME'] ) ? str_replace( '\\', '/',(string) $_SERVER['SCRIPT_NAME'] ) : '';
	for ( $i = 0; $i <= $levels; $i++ ) {
		$path = substr( $path, 0, (int) strrpos( $path, '/' ) );
	}
	return $path;
}

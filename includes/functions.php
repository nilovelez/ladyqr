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

/**
 * Returns the address that was asked for, relative to the site's folder and
 * without the query: '' for the root (and for index.php itself), 'menu' for
 * /menu. Only index.php uses it.
 */
function ladyqr_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '/';
	$base = ladyqr_base_path();
	if ( 0 !== strpos( $uri, $base . '/' ) ) {
		return '';
	}
	$rest = (string) substr( $uri, strlen( $base ) + 1 );
	return 'index.php' === $rest ? '' : $rest;
}

/**
 * Puts text in a file in one move: a temporary file next to it, then a rename.
 * If the folder does not allow that, the file itself is written. The temporary
 * file ends with the name of the target, so it is just as safe if requested.
 *
 * @param string $file The target.
 * @param string $text The new contents.
 */
function ladyqr_file_replace( $file, $text ) {
	$dir = dirname( $file );
	if ( is_writable( $dir ) ) {
		$tmp = $dir . '/.ladyqr-tmp-' . bin2hex( random_bytes( 4 ) ) . '-' . basename( $file );
		if ( false !== file_put_contents( $tmp, $text ) ) {
			@chmod( $tmp, is_file( $file ) ? fileperms( $file ) & 0777 : 0644 );
			if ( @rename( $tmp, $file ) ) {
				clearstatcache( true, $file );
				return true;
			}
		}
		@unlink( $tmp );
	}
	if ( is_file( $file ) && ! is_writable( $file ) ) {
		return false;
	}
	$done = false !== @file_put_contents( $file, $text );
	clearstatcache( true, $file );
	return $done;
}

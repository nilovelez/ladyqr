<?php
/**
 * Router for PHP's built-in server, standing in for Apache in the tests.
 *
 * Real files and folders are served as they are. Any other address goes to
 * index.php only when the site's .htaccess holds LadyQR's block, which is what
 * the block's rewrite rule does on Apache; otherwise it is a plain 404. The
 * address rules (https and the host) are not imitated.
 */

$ladyqr_root = $_SERVER['DOCUMENT_ROOT'];
$ladyqr_path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

if ( file_exists( $ladyqr_root . $ladyqr_path ) ) {
	return false;
}

$ladyqr_htaccess = $ladyqr_root . '/.htaccess';
if ( ! is_file( $ladyqr_htaccess ) || false === strpos( (string) file_get_contents( $ladyqr_htaccess ), '# BEGIN LadyQR' ) ) {
	http_response_code( 404 );
	echo 'Not Found';
	return true;
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $ladyqr_root . '/index.php';
chdir( $ladyqr_root );
require $ladyqr_root . '/index.php';

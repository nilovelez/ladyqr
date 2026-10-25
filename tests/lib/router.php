<?php
/**
 * Router for PHP's built-in server, standing in for Apache in the tests.
 *
 * Real files and folders are served as they are. Any other address goes to
 * the index.php of the nearest folder above it whose .htaccess holds LadyQR's
 * block, which is what the block's rewrite rule does on Apache; without one it
 * is a plain 404. The address rules (https and the host) are not imitated.
 */

$ladyqr_root = $_SERVER['DOCUMENT_ROOT'];
$ladyqr_path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

if ( file_exists( $ladyqr_root . $ladyqr_path ) ) {
	return false;
}

$ladyqr_folder = substr( $ladyqr_path, 0, (int) strrpos( $ladyqr_path, '/' ) );
while ( true ) {
	$ladyqr_htaccess = $ladyqr_root . $ladyqr_folder . '/.htaccess';
	if ( is_file( $ladyqr_htaccess ) && false !== strpos( (string) file_get_contents( $ladyqr_htaccess ), '# BEGIN LadyQR' ) ) {
		$_SERVER['SCRIPT_NAME']     = $ladyqr_folder . '/index.php';
		$_SERVER['PHP_SELF']        = $ladyqr_folder . '/index.php';
		$_SERVER['SCRIPT_FILENAME'] = $ladyqr_root . $ladyqr_folder . '/index.php';
		chdir( $ladyqr_root . $ladyqr_folder );
		require $ladyqr_root . $ladyqr_folder . '/index.php';
		return true;
	}
	if ( '' === $ladyqr_folder ) {
		break;
	}
	$ladyqr_folder = substr( $ladyqr_folder, 0, (int) strrpos( $ladyqr_folder, '/' ) );
}

http_response_code( 404 );
echo 'Not Found';
return true;

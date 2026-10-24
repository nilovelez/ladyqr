<?php
/**
 * Reads and writes the root `.htaccess` safely.
 *
 * A bad `.htaccess` makes the whole site answer with an error, so every change
 * goes through the same steps: a copy of the current file is kept, the new
 * file is built aside and put in place in one move, the site is requested to
 * see that it still answers, and if it does not the old file is put back.
 */

defined( 'LADYQR' ) || exit;

/**
 * The path of the `.htaccess` in the web root.
 */
function ladyqr_htaccess_path() {
	return dirname( __DIR__ ) . '/.htaccess';
}

/**
 * Asks the site for a page that must exist, to learn whether it still works.
 *
 * @return int|null The HTTP status, or null when the request could not be made
 *                  (so the answer is unknown, not bad).
 */
function ladyqr_site_status() {
	// PHP's built-in server ignores .htaccess and handles one request at a time:
	// asking it for a page from inside a request would wait for itself.
	if ( 'cli-server' === PHP_SAPI ) {
		return null;
	}
	$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	if ( ! preg_match( '/^[A-Za-z0-9.-]+(:[0-9]{1,5})?$/D', $host ) ) {
		return null;
	}
	$url = ( ladyqr_is_https() ? 'https://' : 'http://' ) . $host . ladyqr_base_path( 1 ) . '/assets/index.html';

	if ( function_exists( 'curl_init' ) ) {
		$curl = curl_init( $url );
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_NOBODY         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 4,
				CURLOPT_TIMEOUT        => 6,
			)
		);
		curl_exec( $curl );
		$status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		curl_close( $curl );
		return $status > 0 ? $status : null;
	}

	if ( ini_get( 'allow_url_fopen' ) ) {
		$context = stream_context_create(
			array(
				'http' => array(
					'method'          => 'HEAD',
					'timeout'         => 6,
					'follow_location' => 0,
					'ignore_errors'   => true,
				),
			)
		);
		@file_get_contents( $url, false, $context );
		// PHP 8.4 deprecates the magic variable in favor of a function, and
		// even naming the variable triggers the notice, so it is read indirectly.
		$locals  = get_defined_vars();
		$headers = function_exists( 'http_get_last_response_headers' ) ? http_get_last_response_headers() : ( isset( $locals['http_response_header'] ) ? $locals['http_response_header'] : null );
		if ( isset( $headers[0] ) && preg_match( '~^HTTP/\S+\s+(\d{3})~', $headers[0], $m ) ) {
			return (int) $m[1];
		}
	}
	return null;
}

/**
 * Puts text in a file in one move: a temporary file next to it, then a rename.
 * If the folder does not allow that, the file itself is written.
 *
 * @param string $file The target.
 * @param string $text The new contents.
 */
function ladyqr_file_replace( $file, $text ) {
	$dir = dirname( $file );
	if ( is_writable( $dir ) ) {
		$tmp = $dir . '/.ladyqr-tmp-' . bin2hex( random_bytes( 4 ) );
		if ( false !== file_put_contents( $tmp, $text ) ) {
			@chmod( $tmp, is_file( $file ) ? fileperms( $file ) & 0777 : 0644 );
			if ( @rename( $tmp, $file ) ) {
				clearstatcache( true, $file );
				return true;
			}
		}
		@unlink( $tmp );
	}
	$done = false !== file_put_contents( $file, $text );
	clearstatcache( true, $file );
	return $done;
}

/**
 * Replaces the `.htaccess` with new text, checking that the site survives.
 *
 * @param string $new The whole new file.
 * @return string 'saved' (done and the site answers), 'unverified' (done, but
 *                the site could not be asked), 'rolled_back' (the site did not
 *                answer, so the old file is back), 'not_writable' or 'failed'
 *                (nothing was changed).
 */
function ladyqr_htaccess_write( $new ) {
	$file = ladyqr_htaccess_path();
	if ( ! is_file( $file ) || ! is_readable( $file ) ) {
		return 'failed';
	}
	if ( ! is_writable( $file ) ) {
		return 'not_writable';
	}
	$old = file_get_contents( $file );
	if ( false === $old ) {
		return 'failed';
	}

	// A copy for a person to fall back on if this script dies half way. It holds
	// only redirects, nothing secret.
	@file_put_contents( $file . '.bak', $old );

	if ( ! ladyqr_file_replace( $file, $new ) ) {
		ladyqr_file_replace( $file, $old );
		return 'failed';
	}
	if ( file_get_contents( $file ) !== $new ) {
		ladyqr_file_replace( $file, $old );
		return 'failed';
	}

	$status = ladyqr_site_status();
	if ( null === $status ) {
		return 'unverified';
	}
	if ( $status >= 500 ) {
		ladyqr_file_replace( $file, $old );
		return 'rolled_back';
	}
	return 'saved';
}

/**
 * Reads the short links from the `.htaccess`.
 *
 * @return array With 'state' ('ok', 'missing' when there is no .htaccess,
 *               'unreadable' or 'damaged' when the markers are broken) and,
 *               when ok, 'links', 'other' and 'parts'.
 */
function ladyqr_links_load() {
	$file = ladyqr_htaccess_path();
	if ( ! is_file( $file ) ) {
		return array( 'state' => 'missing' );
	}
	$text = is_readable( $file ) ? file_get_contents( $file ) : false;
	if ( false === $text ) {
		return array( 'state' => 'unreadable' );
	}
	$parts = ladyqr_block_split( $text );
	if ( null === $parts ) {
		return array( 'state' => 'damaged' );
	}
	list( $links, $other ) = ladyqr_links_parse( $parts['inside'], ladyqr_base_path( 1 ) );
	return array(
		'state' => 'ok',
		'links' => $links,
		'other' => $other,
		'parts' => $parts,
	);
}

/**
 * Saves a new set of links.
 *
 * @param array $loaded The result of ladyqr_links_load(), with state 'ok'.
 * @param array $links  The links to store.
 * @return string The result of ladyqr_htaccess_write().
 */
function ladyqr_links_save( array $loaded, array $links ) {
	$block = ladyqr_links_build( $links, $loaded['other'], ladyqr_base_path( 1 ) );
	return ladyqr_htaccess_write( ladyqr_block_join( $loaded['parts'], $block ) );
}

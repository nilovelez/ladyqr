<?php
/**
 * The `.htaccess` of the web root: LadyQR's block in it, and writing it safely.
 *
 * LadyQR works and is secure without `.htaccess`. The file only adds the
 * routing that lets short links reach index.php, and, when the installer
 * creates it, the rules that force the chosen address. LadyQR's part sits
 * between `# BEGIN LadyQR` and `# END LadyQR`; the rest of the file is the
 * owner's and is left alone.
 *
 * A bad `.htaccess` makes the whole site answer with an error, so every change
 * goes through the same steps: a copy of the current file is kept, the new
 * file is built aside and put in place in one move, the site is requested to
 * see that it still answers, and if it does not the old file is put back.
 */

defined( 'LADYQR' ) || exit;

const LADYQR_BLOCK_BEGIN      = '# BEGIN LadyQR';
const LADYQR_BLOCK_END        = '# END LadyQR';
const LADYQR_HTACCESS_VERSION = 1;

/**
 * The path of the `.htaccess` in the web root.
 */
function ladyqr_htaccess_path() {
	return dirname( __DIR__ ) . '/.htaccess';
}

/**
 * Returns LadyQR's block: everything that is not a file or a folder goes to
 * index.php, which answers the short links and the not-found page.
 *
 * @param string $base The path of the site on its host, '' at the root of the domain.
 */
function ladyqr_htaccess_block( $base ) {
	return implode(
		"\n",
		array(
			LADYQR_BLOCK_BEGIN,
			'# Version ' . LADYQR_HTACCESS_VERSION . '. Written by LadyQR, which rewrites this block when it needs to.',
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_FILENAME} !-f',
			'RewriteCond %{REQUEST_FILENAME} !-d',
			'RewriteRule ^ ' . $base . '/index.php [L]',
			'</IfModule>',
			LADYQR_BLOCK_END,
		)
	);
}

/**
 * Returns the rules that go at the top of an `.htaccess` the installer creates:
 * they send every visit to the chosen address, with or without www, and to
 * https when it is chosen.
 *
 * The https rule is only written when this server reports https itself. Behind
 * a proxy that ends the TLS connection, the server sees plain http on every
 * request and the rule would send visitors round in a loop.
 *
 * @param string $url The address of the site.
 */
function ladyqr_htaccess_generic( $url ) {
	$parts  = parse_url( $url );
	$scheme = $parts['scheme'];
	$host   = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$lines  = array(
		'# The address of this site. Written by the LadyQR installer; yours to change.',
		'<IfModule mod_rewrite.c>',
		'RewriteEngine On',
	);
	if ( 'https' === $scheme && ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) ) {
		$lines[] = 'RewriteCond %{HTTPS} !=on';
		$lines[] = 'RewriteRule ^ https://' . $host . '%{REQUEST_URI} [R=301,L]';
	}
	$lines[] = 'RewriteCond %{HTTP_HOST} !=' . $host . ' [NC]';
	$lines[] = 'RewriteRule ^ ' . $scheme . '://' . $host . '%{REQUEST_URI} [R=301,L]';
	$lines[] = '</IfModule>';
	return implode( "\n", $lines );
}

/**
 * Splits the text of an `.htaccess` around LadyQR's block.
 *
 * @param string $text The whole file.
 * @return array|null Null when the markers are damaged (one without the other,
 *                    repeated, or in the wrong order); otherwise the text
 *                    before the block, the lines inside it, the text after it,
 *                    and whether there was a block.
 */
function ladyqr_block_split( $text ) {
	$lines = preg_split( '/\R/', $text );
	$begin = array();
	$end   = array();
	foreach ( $lines as $i => $line ) {
		$trim = trim( $line );
		if ( LADYQR_BLOCK_BEGIN === $trim ) {
			$begin[] = $i;
		} elseif ( LADYQR_BLOCK_END === $trim ) {
			$end[] = $i;
		}
	}
	if ( array() === $begin && array() === $end ) {
		return array(
			'before' => $text,
			'inside' => array(),
			'after'  => '',
			'found'  => false,
		);
	}
	if ( 1 !== count( $begin ) || 1 !== count( $end ) || $begin[0] > $end[0] ) {
		return null;
	}
	return array(
		'before' => implode( "\n", array_slice( $lines, 0, $begin[0] ) ),
		'inside' => array_slice( $lines, $begin[0] + 1, $end[0] - $begin[0] - 1 ),
		'after'  => implode( "\n", array_slice( $lines, $end[0] + 1 ) ),
		'found'  => true,
	);
}

/**
 * Puts a block into the text of an `.htaccess`, in place of the old one, or at
 * the end when there was none.
 *
 * @param array  $parts The result of ladyqr_block_split() on the current text.
 * @param string $block The new block.
 * @return string
 */
function ladyqr_block_join( array $parts, $block ) {
	if ( ! $parts['found'] ) {
		$before = rtrim( $parts['before'] );
		return ( '' === $before ? '' : $before . "\n\n" ) . $block . "\n";
	}
	$after = ltrim( $parts['after'], "\n" );
	return $parts['before'] . ( '' === $parts['before'] ? '' : "\n" ) . $block . "\n" . $after;
}

/**
 * Says whether the `.htaccess` has LadyQR's block as this version writes it.
 *
 * @return string 'ok', 'missing' (no file or no block), 'outdated' (a block
 *                from another version), 'damaged' (broken markers, so it must
 *                not be touched) or 'unreadable'.
 */
function ladyqr_htaccess_state() {
	$file = ladyqr_htaccess_path();
	if ( ! is_file( $file ) ) {
		return 'missing';
	}
	$text = is_readable( $file ) ? file_get_contents( $file ) : false;
	if ( false === $text ) {
		return 'unreadable';
	}
	$parts = ladyqr_block_split( $text );
	if ( null === $parts ) {
		return 'damaged';
	}
	if ( ! $parts['found'] ) {
		return 'missing';
	}
	$version = isset( $parts['inside'][0] ) && preg_match( '/^# Version (\d+)\./', trim( $parts['inside'][0] ), $m ) ? (int) $m[1] : 0;
	return LADYQR_HTACCESS_VERSION === $version ? 'ok' : 'outdated';
}

/**
 * Returns the whole new text of the `.htaccess` with LadyQR's block as this
 * version writes it.
 *
 * @param string|null $current The current text, or null when there is no file.
 * @param string      $url     The address of the site.
 * @param bool        $generic Whether a new file gets the address rules on top.
 * @return string|null Null when the current markers are damaged.
 */
function ladyqr_htaccess_text( $current, $url, $generic = false ) {
	$path  = (string) parse_url( $url, PHP_URL_PATH );
	$block = ladyqr_htaccess_block( rtrim( $path, '/' ) );
	if ( null === $current ) {
		return ( $generic ? ladyqr_htaccess_generic( $url ) . "\n\n" : '' ) . $block . "\n";
	}
	$parts = ladyqr_block_split( $current );
	return null === $parts ? null : ladyqr_block_join( $parts, $block );
}

/**
 * Asks a page of this site for its status and headers, without following redirects.
 *
 * @param string $url The address.
 * @return array|null With 'status' and 'headers' (lowercase names), or null when
 *                    the request could not be made (the answer is unknown, not bad).
 */
function ladyqr_http_head( $url ) {
	// PHP's built-in server ignores .htaccess and handles one request at a time:
	// asking it for a page from inside a request would wait for itself.
	if ( 'cli-server' === PHP_SAPI ) {
		return null;
	}
	$raw = null;
	if ( function_exists( 'curl_init' ) ) {
		$curl = curl_init( $url );
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_NOBODY         => true,
				CURLOPT_HEADER         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 4,
				CURLOPT_TIMEOUT        => 6,
			)
		);
		$out = curl_exec( $curl );
		curl_close( $curl );
		if ( is_string( $out ) && '' !== $out ) {
			$raw = preg_split( '/\R/', trim( $out ) );
		}
	} elseif ( ini_get( 'allow_url_fopen' ) ) {
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
		$locals = get_defined_vars();
		$raw    = function_exists( 'http_get_last_response_headers' ) ? http_get_last_response_headers() : ( isset( $locals['http_response_header'] ) ? $locals['http_response_header'] : null );
	}
	if ( ! is_array( $raw ) || ! isset( $raw[0] ) || ! preg_match( '~^HTTP/\S+\s+(\d{3})~', $raw[0], $m ) ) {
		return null;
	}
	$headers = array();
	foreach ( array_slice( $raw, 1 ) as $line ) {
		$colon = strpos( $line, ':' );
		if ( false !== $colon ) {
			$headers[ strtolower( trim( substr( $line, 0, $colon ) ) ) ] = trim( substr( $line, $colon + 1 ) );
		}
	}
	return array( 'status' => (int) $m[1], 'headers' => $headers );
}

/**
 * Tells whether `.htaccess` sends an address that is not a file to index.php.
 *
 * @param string $url The address of the site.
 * @return bool|null Null when the site could not be asked.
 */
function ladyqr_route_works( $url ) {
	$answer = ladyqr_http_head( $url . '/ladyqr-route-test' );
	if ( null === $answer ) {
		return null;
	}
	return isset( $answer['headers']['x-ladyqr-route'] ) && 'ok' === $answer['headers']['x-ladyqr-route'];
}

/**
 * Writes the `.htaccess`, creating it if needed, and checks that the site survives.
 *
 * @param string $new The whole new file.
 * @param string $url The address of the site, to check that it still answers.
 * @return string 'saved' (done and the site answers), 'unverified' (done, but
 *                the site could not be asked), 'rolled_back' (the site did not
 *                answer, so the old file is back), 'not_writable' or 'failed'
 *                (nothing was changed).
 */
function ladyqr_htaccess_write( $new, $url ) {
	$file   = ladyqr_htaccess_path();
	$exists = is_file( $file );
	if ( $exists ? ! is_writable( $file ) : ! is_writable( dirname( $file ) ) ) {
		return 'not_writable';
	}
	$old = $exists ? file_get_contents( $file ) : null;
	if ( false === $old ) {
		return 'failed';
	}

	$restore = function () use ( $file, $old ) {
		if ( null === $old ) {
			@unlink( $file );
			clearstatcache( true, $file );
		} else {
			ladyqr_file_replace( $file, $old );
		}
	};

	// A copy for a person to fall back on if this script dies half way. Apache
	// never serves files whose names start with .ht, and it holds nothing secret.
	if ( null !== $old ) {
		@file_put_contents( $file . '.bak', $old );
	}

	if ( ! ladyqr_file_replace( $file, $new ) || file_get_contents( $file ) !== $new ) {
		$restore();
		return 'failed';
	}

	$answer = ladyqr_http_head( $url . '/assets/index.html' );
	if ( null === $answer ) {
		return 'unverified';
	}
	if ( $answer['status'] >= 500 ) {
		$restore();
		return 'rolled_back';
	}
	return 'saved';
}

/**
 * Makes sure LadyQR's block is in the `.htaccess` as this version writes it:
 * puts it back when it is missing and rewrites it when it is from another version.
 *
 * @param string $url The address of the site.
 * @return array With 'state' (as ladyqr_htaccess_state() said before), 'result'
 *               ('' when nothing was needed, or what ladyqr_htaccess_write()
 *               returned) and 'text' (the text the file needs, for a person to
 *               paste when it cannot be written).
 */
function ladyqr_htaccess_ensure( $url ) {
	$state = ladyqr_htaccess_state();
	if ( 'ok' === $state || 'damaged' === $state || 'unreadable' === $state ) {
		return array( 'state' => $state, 'result' => '', 'text' => '' );
	}
	$file    = ladyqr_htaccess_path();
	$current = is_file( $file ) ? file_get_contents( $file ) : null;
	$text    = ladyqr_htaccess_text( false === $current ? null : $current, $url );
	if ( null === $text ) {
		return array( 'state' => 'damaged', 'result' => '', 'text' => '' );
	}
	return array( 'state' => $state, 'result' => ladyqr_htaccess_write( $text, $url ), 'text' => $text );
}

<?php
/**
 * Configuration loader.
 *
 * The configuration file is `ladyqr-config.php`, a PHP file that returns an
 * array. It is looked up in the web root first and, if it is not there, one
 * level above it, like wp-config.php. If both exist, the one in the web root
 * wins; they are never merged.
 */

defined( 'LADYQR' ) || exit;

/**
 * Returns the path of the configuration file in use, or null if there is none.
 *
 * @return string|null
 */
function ladyqr_config_file() {
	static $file = false;
	if ( false !== $file ) {
		return $file;
	}

	$file = null;
	$root = dirname( __DIR__ );
	foreach ( array( $root, dirname( $root ) ) as $dir ) {
		$candidate = $dir . '/ladyqr-config.php';
		if ( is_file( $candidate ) ) {
			$file = $candidate;
			break;
		}
	}
	return $file;
}

/**
 * Returns the finished configuration: the defaults plus whatever the
 * configuration file sets, with every value checked.
 *
 * @return array<string, mixed>
 */
function ladyqr_config() {
	static $config = null;
	if ( null !== $config ) {
		return $config;
	}

	$config = array(
		// Interface language, as a WordPress-style locale. English needs no file.
		'language'        => 'en_US',
		// The address of the site, such as https://1qr.es or https://example.com/qr,
		// without a final slash. Empty means the address of the current request.
		'url'             => '',
		// Whether the short links are on. Only an explicit true turns them on.
		'shortener'       => false,
		// The admin's user and password. The password may be written in plain
		// text: the first successful login replaces it with a hash.
		'user'            => '',
		'password'        => '',
		// Only an explicit false opens the admin without a login.
		'require_login'   => true,
		// Minutes without activity after which the admin asks for the password again.
		'session_timeout' => 15,
	);

	$file = ladyqr_config_file();
	if ( null !== $file ) {
		$loaded = include $file;
		if ( is_array( $loaded ) ) {
			$config = array_merge( $config, $loaded );
		}
	}

	if ( ! is_string( $config['language'] ) || ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $config['language'] ) ) {
		$config['language'] = 'en_US';
	}
	foreach ( array( 'user', 'password' ) as $key ) {
		if ( ! is_string( $config[ $key ] ) ) {
			$config[ $key ] = '';
		}
	}
	$config['url'] = ladyqr_clean_url( $config['url'] );
	if ( null === $config['url'] ) {
		$config['url'] = '';
	}
	$config['shortener']     = true === $config['shortener'];
	$config['require_login'] = false !== $config['require_login'];
	if ( ! is_int( $config['session_timeout'] ) || $config['session_timeout'] < 1 || $config['session_timeout'] > 1440 ) {
		$config['session_timeout'] = 15;
	}

	return $config;
}

/**
 * Checks the address of a site and returns it without a final slash: http or
 * https, a host, an optional port and an optional path.
 *
 * @param mixed $url The address.
 * @return string|null Null when it is not a valid address.
 */
function ladyqr_clean_url( $url ) {
	if ( ! is_string( $url ) ) {
		return null;
	}
	$url = rtrim( trim( $url ), '/' );
	if ( ! preg_match( '#^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[A-Za-z0-9._~%-]+)*$#D', $url ) ) {
		return null;
	}
	return $url;
}

/**
 * Returns the address of the site, without a final slash: the configured one,
 * or the one the current request came in through.
 *
 * @param int $levels How many folders the running script is below the web root.
 */
function ladyqr_site_url( $levels = 0 ) {
	$config = ladyqr_config();
	if ( '' !== $config['url'] ) {
		return $config['url'];
	}
	return ladyqr_request_origin() . ladyqr_base_path( $levels );
}

/**
 * Returns the scheme and the host of the current request, such as https://1qr.es.
 * A host that does not look like one is left out.
 */
function ladyqr_request_origin() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	if ( ! preg_match( '/^[A-Za-z0-9.-]+(:[0-9]{1,5})?$/D', $host ) ) {
		$host = 'localhost';
	}
	return ( ladyqr_is_https() ? 'https://' : 'http://' ) . $host;
}

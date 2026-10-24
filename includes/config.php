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
	$config['require_login'] = false !== $config['require_login'];
	if ( ! is_int( $config['session_timeout'] ) || $config['session_timeout'] < 1 || $config['session_timeout'] > 1440 ) {
		$config['session_timeout'] = 15;
	}

	return $config;
}

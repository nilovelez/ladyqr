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
 * Returns the finished configuration: the defaults plus whatever the
 * configuration file sets.
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
		'language' => 'en_US',
	);

	$root = dirname( __DIR__ );
	foreach ( array( $root, dirname( $root ) ) as $dir ) {
		$file = $dir . '/ladyqr-config.php';
		if ( is_file( $file ) ) {
			$loaded = include $file;
			if ( is_array( $loaded ) ) {
				$config = array_merge( $config, $loaded );
			}
			break;
		}
	}

	if ( ! is_string( $config['language'] ) || ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $config['language'] ) ) {
		$config['language'] = 'en_US';
	}

	return $config;
}

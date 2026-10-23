<?php
// Test 0: what PHP and the server look like. Returns JSON for the dashboard.
error_reporting( 0 );
ini_set( 'display_errors', '0' );
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

$doc    = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( (string) $_SERVER['DOCUMENT_ROOT'], '/\\' ) : '';
$parent = '' !== $doc ? dirname( $doc ) : '';

/**
 * Returns an ini value, or null if it cannot be read.
 */
function spike_ini( $key ) {
	$value = ini_get( $key );
	return false === $value ? null : $value;
}

$env = array(
	'php_version'       => PHP_VERSION,
	'php_sapi'          => PHP_SAPI,
	'os'                => PHP_OS . ' ' . php_uname( 'r' ),
	'server_software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null,
	'document_root'     => $doc,
	'script_filename'   => isset( $_SERVER['SCRIPT_FILENAME'] ) ? $_SERVER['SCRIPT_FILENAME'] : null,
	'https_indicators'  => array(
		'HTTPS'                  => isset( $_SERVER['HTTPS'] ) ? $_SERVER['HTTPS'] : null,
		'REQUEST_SCHEME'         => isset( $_SERVER['REQUEST_SCHEME'] ) ? $_SERVER['REQUEST_SCHEME'] : null,
		'SERVER_PORT'            => isset( $_SERVER['SERVER_PORT'] ) ? $_SERVER['SERVER_PORT'] : null,
		'HTTP_X_FORWARDED_PROTO' => isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : null,
		'HTTP_X_FORWARDED_SSL'   => isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ? $_SERVER['HTTP_X_FORWARDED_SSL'] : null,
	),
	'host'              => isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null,
	'paths'             => array(
		'docroot_writable' => '' !== $doc ? is_writable( $doc ) : null,
		'spike_writable'   => is_writable( dirname( __FILE__ ) ),
		'parent'           => $parent,
		'parent_readable'  => '' !== $parent ? is_readable( $parent ) : null,
		'parent_writable'  => '' !== $parent ? is_writable( $parent ) : null,
	),
	'ini'               => array(
		'open_basedir'        => spike_ini( 'open_basedir' ),
		'disable_functions'   => spike_ini( 'disable_functions' ),
		'allow_url_fopen'     => spike_ini( 'allow_url_fopen' ),
		'memory_limit'        => spike_ini( 'memory_limit' ),
		'max_execution_time'  => spike_ini( 'max_execution_time' ),
		'upload_max_filesize' => spike_ini( 'upload_max_filesize' ),
		'post_max_size'       => spike_ini( 'post_max_size' ),
		'default_charset'     => spike_ini( 'default_charset' ),
		'date.timezone'       => spike_ini( 'date.timezone' ),
		'opcache.enable'      => spike_ini( 'opcache.enable' ),
		'session.save_path'   => spike_ini( 'session.save_path' ),
	),
	'extensions'        => array(
		'json'     => extension_loaded( 'json' ),
		'session'  => extension_loaded( 'session' ),
		'mbstring' => extension_loaded( 'mbstring' ),
		'openssl'  => extension_loaded( 'openssl' ),
		'curl'     => extension_loaded( 'curl' ),
		'gettext'  => extension_loaded( 'gettext' ),
		'posix'    => extension_loaded( 'posix' ),
	),
	'functions'         => array(),
	'disk'              => array(
		'free_mb'  => '' !== $doc && function_exists( 'disk_free_space' ) ? round( (float) @disk_free_space( $doc ) / 1048576 ) : null,
		'total_mb' => '' !== $doc && function_exists( 'disk_total_space' ) ? round( (float) @disk_total_space( $doc ) / 1048576 ) : null,
	),
	'ovhconfig'         => null,
	'docroot_htaccess'  => '' !== $doc && is_file( $doc . '/.htaccess' ) ? filesize( $doc . '/.htaccess' ) : false,
	'parent_probe'      => 'not present',
	'password'          => array(),
);

foreach ( array( 'password_hash', 'password_verify', 'random_bytes', 'hash_equals', 'flock', 'rename', 'tempnam', 'file_put_contents', 'session_start', 'curl_init', 'gettext' ) as $fn ) {
	$env['functions'][ $fn ] = function_exists( $fn );
}

// OVH shared hosting may choose the PHP version with a .ovhconfig file.
if ( '' !== $doc && is_file( $doc . '/.ovhconfig' ) ) {
	$env['ovhconfig'] = substr( (string) @file_get_contents( $doc . '/.ovhconfig' ), 0, 600 );
}

// Test 11: a file one level above the web root.
if ( '' !== $parent ) {
	$probe = $parent . '/ladyqr-parent-probe.php';
	if ( @is_file( $probe ) ) {
		$loaded               = @include $probe;
		$env['parent_probe'] = is_array( $loaded ) && isset( $loaded['probe'] ) ? 'included: ' . $loaded['probe'] : 'file exists but did not include';
	} else {
		$last                = error_get_last();
		$env['parent_probe'] = 'not present' . ( $last && false !== stripos( $last['message'], 'open_basedir' ) ? ' (open_basedir blocked the check)' : '' );
	}
}

// Test 10: password hashing.
if ( function_exists( 'password_hash' ) ) {
	$start                          = microtime( true );
	$hash                           = password_hash( 'spike-test', PASSWORD_DEFAULT );
	$env['password']['hash_ms']     = round( ( microtime( true ) - $start ) * 1000 );
	$start                          = microtime( true );
	$env['password']['verify_ok']   = password_verify( 'spike-test', $hash );
	$env['password']['verify_ms']   = round( ( microtime( true ) - $start ) * 1000 );
	$env['password']['default_algo'] = defined( 'PASSWORD_DEFAULT' ) ? (string) PASSWORD_DEFAULT : null;
	$env['password']['hash_prefix'] = substr( $hash, 0, 7 );
	$env['password']['argon2id']    = defined( 'PASSWORD_ARGON2ID' );
}
if ( function_exists( 'random_bytes' ) ) {
	try {
		$env['password']['random_bytes'] = 16 === strlen( random_bytes( 16 ) );
	} catch ( Exception $e ) {
		$env['password']['random_bytes'] = false;
	}
}

echo json_encode( $env );

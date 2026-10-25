<?php
/**
 * The short links, stored in `ladyqr-data.php`.
 *
 * The file is looked up like the configuration: in the web root and, if it is
 * not there, one level above. It is a PHP file that returns an array of the
 * links, written by LadyQR with var_export(). Like the configuration, it starts
 * with `defined( 'LADYQR' ) || exit;`, so a direct request prints nothing,
 * with or without .htaccess.
 */

defined( 'LADYQR' ) || exit;

/**
 * Returns the path of the data file: the one that exists, or the one in the web
 * root when there is none yet.
 */
function ladyqr_data_file() {
	$root = dirname( __DIR__ );
	foreach ( array( $root, dirname( $root ) ) as $dir ) {
		if ( is_file( $dir . '/ladyqr-data.php' ) ) {
			return $dir . '/ladyqr-data.php';
		}
	}
	return $root . '/ladyqr-data.php';
}

/**
 * Returns the text of a data file holding these links.
 *
 * @param array $links slug => array( 'status' => 301|302, 'target' => url ).
 */
function ladyqr_data_text( array $links ) {
	ksort( $links, SORT_NATURAL | SORT_FLAG_CASE );
	return "<?php\n/**\n * LadyQR short links. Written by LadyQR: change them in the admin.\n */\n"
		. "defined( 'LADYQR' ) || exit;\n\nreturn " . var_export( $links, true ) . ";\n";
}

/**
 * Reads the links.
 *
 * @return array With 'state': 'ok' (with 'links'; a missing file is no links
 *               yet) or 'damaged' (the file is there but cannot be read or
 *               understood, so it must not be overwritten).
 */
function ladyqr_links_load() {
	$file = ladyqr_data_file();
	if ( ! is_file( $file ) ) {
		return array( 'state' => 'ok', 'links' => array() );
	}
	try {
		$data = is_readable( $file ) ? include $file : null;
	} catch ( Throwable $e ) {
		$data = null;
	}
	if ( ! is_array( $data ) ) {
		return array( 'state' => 'damaged' );
	}
	$links = array();
	foreach ( $data as $slug => $link ) {
		$slug = (string) $slug;
		if ( '' === ladyqr_link_check_slug_format( $slug ) && is_array( $link )
			&& isset( $link['status'], $link['target'] ) && in_array( $link['status'], array( 301, 302 ), true )
			&& is_string( $link['target'] ) && '' === ladyqr_link_check_target( $link['target'] ) ) {
			$links[ $slug ] = array( 'status' => $link['status'], 'target' => $link['target'] );
		}
	}
	return array( 'state' => 'ok', 'links' => $links );
}

/**
 * Saves the links, in one move, and reads them back.
 *
 * @param array $links The links to store.
 * @return string 'saved', 'not_writable' or 'failed' (nothing was changed).
 */
function ladyqr_links_save( array $links ) {
	$file = ladyqr_data_file();
	if ( is_file( $file ) ? ! is_writable( $file ) : ! is_writable( dirname( $file ) ) ) {
		return 'not_writable';
	}
	$old  = is_file( $file ) ? file_get_contents( $file ) : null;
	$text = ladyqr_data_text( $links );
	if ( ! ladyqr_file_replace( $file, $text ) || file_get_contents( $file ) !== $text ) {
		if ( null !== $old ) {
			ladyqr_file_replace( $file, $old );
		}
		return 'failed';
	}
	// PHP keeps compiled copies of files and only checks them for changes every
	// few seconds, so the next request could still see the old links.
	if ( function_exists( 'opcache_invalidate' ) ) {
		@opcache_invalidate( $file, true );
	}
	return 'saved';
}

/**
 * Looks up the link for a slug, exactly as written.
 *
 * @param string $slug The name.
 * @return array|null
 */
function ladyqr_link_find( $slug ) {
	$loaded = ladyqr_links_load();
	if ( 'ok' !== $loaded['state'] || ! isset( $loaded['links'][ $slug ] ) ) {
		return null;
	}
	return $loaded['links'][ $slug ];
}

<?php
/**
 * Short links: validation, and reading and writing the managed block.
 *
 * The links live in one block of the root `.htaccess`, one `RedirectMatch`
 * line each, and that block is the only place they are stored. Apache answers
 * a short link by itself, without starting PHP. Nothing here touches the disk:
 * see htaccess.php for that.
 */

defined( 'LADYQR' ) || exit;

const LADYQR_BLOCK_BEGIN = '# BEGIN LadyQR short links';
const LADYQR_BLOCK_END   = '# END LadyQR short links';

/**
 * Names that cannot be short links, because they are files and folders of the
 * program itself. The real contents of the web root are checked too.
 *
 * @return string[]
 */
function ladyqr_reserved_slugs() {
	return array( 'admin', 'assets', 'includes', 'languages', 'old', 'index', 'docs', 'site', 'tools', 'ladyqr-config' );
}

/**
 * Checks a slug: letters, digits and hyphens, up to 64 characters.
 *
 * @param string      $slug     The slug as typed.
 * @param string[]    $taken    Slugs already in use.
 * @param string      $root     The web root on disk, to spot names of real files and folders.
 * @param string|null $ignore   A slug to leave out of the duplicate check (the one being edited).
 * @return string Empty when valid, otherwise 'slug_invalid', 'slug_reserved' or 'slug_taken'.
 */
function ladyqr_link_check_slug( $slug, array $taken, $root, $ignore = null ) {
	if ( ! preg_match( '/^[A-Za-z0-9-]{1,64}$/D', $slug ) ) {
		return 'slug_invalid';
	}
	$lower = strtolower( $slug );
	if ( in_array( $lower, ladyqr_reserved_slugs(), true ) || file_exists( $root . '/' . $slug ) ) {
		return 'slug_reserved';
	}
	foreach ( $taken as $other ) {
		if ( null !== $ignore && $other === $ignore ) {
			continue;
		}
		if ( strtolower( $other ) === $lower ) {
			return 'slug_taken';
		}
	}
	return '';
}

/**
 * Checks a target: an http or https address with a host, in plain ASCII and
 * with nothing that could end the line or the argument in the `.htaccess`.
 *
 * @param string      $url       The address as typed.
 * @param string|null $own_host  The host of this site, to refuse a link that points to itself.
 * @param string|null $own_path  The path this link answers on.
 * @return string Empty when valid, otherwise 'target_invalid' or 'target_self'.
 */
function ladyqr_link_check_target( $url, $own_host = null, $own_path = null ) {
	if ( strlen( $url ) > 2000 || ! preg_match( '~^https?://[^\s\x00-\x1f\x7f-\xff"\'\\\\<>`]+$~iD', $url ) ) {
		return 'target_invalid';
	}
	$parts = parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
		return 'target_invalid';
	}
	if ( null !== $own_host && 0 === strcasecmp( $parts['host'], preg_replace( '/:\d+$/', '', $own_host ) )
		&& ( isset( $parts['path'] ) ? $parts['path'] : '/' ) === $own_path ) {
		return 'target_self';
	}
	return '';
}

/**
 * Returns the text of a target as it goes in the `.htaccess`. The dollar sign
 * is the only character that Apache treats as special in the target of a
 * RedirectMatch, so it is written percent-encoded.
 */
function ladyqr_link_target_for_file( $url ) {
	return str_replace( '$', '%24', $url );
}

/**
 * Splits the text of an `.htaccess` around the managed block.
 *
 * @param string $text The whole file.
 * @return array|null Null when the markers are damaged (one without the other,
 *                    repeated, or in the wrong order); otherwise the text
 *                    before the block, the lines inside it, and the text after
 *                    it. When there is no block, the lines are empty and the
 *                    text after it too.
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
 * Reads the links out of the lines inside the managed block.
 *
 * @param string[] $lines The lines between the markers.
 * @param string   $base  The path of the site on the host, '' for the root.
 * @return array The links, as slug => array( 'status' => 301|302, 'target' => url ),
 *               and the lines that are not links, kept so that they survive.
 */
function ladyqr_links_parse( array $lines, $base ) {
	$prefix = '^' . preg_quote( $base, '~' ) . '/';
	$links  = array();
	$other  = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] ) {
			continue; // The comments are written again every time.
		}
		$pattern = '~^RedirectMatch\s+(301|302)\s+' . preg_quote( $prefix, '~' ) . '([A-Za-z0-9-]{1,64})\$\s+(\S+)$~D';
		if ( preg_match( $pattern, $line, $m ) && ! isset( $links[ $m[2] ] ) ) {
			$links[ $m[2] ] = array(
				'status' => (int) $m[1],
				'target' => str_replace( '%24', '$', $m[3] ),
			);
		} else {
			$other[] = $line;
		}
	}
	return array( $links, $other );
}

/**
 * Builds the managed block, markers included.
 *
 * @param array    $links As returned by ladyqr_links_parse().
 * @param string[] $other Lines that are not links, kept as they were.
 * @param string   $base  The path of the site on the host.
 * @return string
 */
function ladyqr_links_build( array $links, array $other, $base ) {
	$out   = array();
	$out[] = LADYQR_BLOCK_BEGIN;
	$out[] = '# Managed by LadyQR. Change the links in the admin: edits made here by hand may be lost.';
	ksort( $links, SORT_NATURAL | SORT_FLAG_CASE );
	foreach ( $links as $slug => $link ) {
		$out[] = 'RedirectMatch ' . (int) $link['status'] . ' ^' . preg_quote( $base, '~' ) . '/' . $slug . '$ ' . ladyqr_link_target_for_file( $link['target'] );
	}
	foreach ( $other as $line ) {
		$out[] = $line;
	}
	$out[] = LADYQR_BLOCK_END;
	return implode( "\n", $out );
}

/**
 * Puts a new block into the text of an `.htaccess`, in place of the old one,
 * or at the end when there was none.
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
	return $parts['before'] . ( '' === $parts['before'] ? '' : "\n" ) . $block . "\n" . ( '' === $after ? '' : $after );
}

/**
 * Takes the managed block out of the text of an `.htaccess`, so that a file
 * whose last link was deleted is left as it was before the first one.
 *
 * @param array $parts The result of ladyqr_block_split() on the current text.
 * @return string
 */
function ladyqr_block_remove( array $parts ) {
	$before = rtrim( $parts['before'] );
	$after  = ltrim( $parts['after'], "\n" );
	if ( '' === $before ) {
		return $after;
	}
	return $before . "\n" . ( '' === $after ? '' : "\n" . $after );
}

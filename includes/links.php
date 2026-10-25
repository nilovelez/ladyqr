<?php
/**
 * Short links: what a valid name and a valid target are.
 *
 * The links are stored by data.php and answered by index.php.
 */

defined( 'LADYQR' ) || exit;

/**
 * Names that cannot be short links: files and folders of the program itself,
 * and the address the installer uses to test `.htaccess`. The real contents of
 * the web root are checked too.
 *
 * @return string[]
 */
function ladyqr_reserved_slugs() {
	return array( 'admin', 'assets', 'includes', 'languages', 'old', 'index', 'docs', 'site', 'tools', 'ladyqr-config', 'ladyqr-data', 'ladyqr-route-test' );
}

/**
 * Checks only the form of a slug: letters, digits and hyphens, up to 64 characters.
 *
 * @param string $slug The slug.
 * @return string Empty when valid, otherwise 'slug_invalid'.
 */
function ladyqr_link_check_slug_format( $slug ) {
	return preg_match( '/^[A-Za-z0-9-]{1,64}$/D', $slug ) ? '' : 'slug_invalid';
}

/**
 * Checks a slug: its form, that it is not reserved and that it is not taken.
 *
 * @param string      $slug     The slug as typed.
 * @param string[]    $taken    Slugs already in use.
 * @param string      $root     The web root on disk, to spot names of real files and folders.
 * @param string|null $ignore   A slug to leave out of the duplicate check (the one being edited).
 * @return string Empty when valid, otherwise 'slug_invalid', 'slug_reserved' or 'slug_taken'.
 */
function ladyqr_link_check_slug( $slug, array $taken, $root, $ignore = null ) {
	if ( '' !== ladyqr_link_check_slug_format( $slug ) ) {
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
 * Checks a target: an http or https address with a host, in plain ASCII, with
 * no spaces, quotes or angle brackets.
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

<?php
/**
 * Translations, WordPress style.
 *
 * All text is written in English in the code with __() and friends, and a
 * translation is looked up in languages/ladyqr-<locale>.mo. A string with no
 * translation stays in English. Plurals are handled by __plural(), not by
 * gettext plural forms.
 */

defined( 'LADYQR' ) || exit;

/**
 * Loads the translations for a locale. English needs no file.
 *
 * @param string $locale A locale such as es_ES.
 */
function ladyqr_load_textdomain( $locale ) {
	global $ladyqr_translations;
	$ladyqr_translations = array();

	if ( ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $locale ) || 0 === strpos( $locale, 'en' ) ) {
		return;
	}
	$file = dirname( __DIR__ ) . '/languages/ladyqr-' . $locale . '.mo';
	if ( is_file( $file ) ) {
		$ladyqr_translations = ladyqr_read_mo( $file );
	}
}

/**
 * Reads a compiled .mo file into an array of original => translation.
 *
 * Entries with a context or with plural forms are skipped, because this
 * project uses neither.
 *
 * @param string $file Path to the .mo file.
 * @return array<string, string>
 */
function ladyqr_read_mo( $file ) {
	$data = file_get_contents( $file );
	if ( false === $data || strlen( $data ) < 28 ) {
		return array();
	}

	$magic = unpack( 'V', substr( $data, 0, 4 ) );
	if ( 0x950412de === $magic[1] ) {
		$format = 'V';
	} elseif ( 0xde120495 === $magic[1] ) {
		$format = 'N';
	} else {
		return array();
	}

	$header = unpack( "{$format}revision/{$format}count/{$format}originals/{$format}translations", substr( $data, 4, 16 ) );
	$length = strlen( $data );
	$result = array();

	for ( $i = 0; $i < $header['count']; $i++ ) {
		$o = unpack( "{$format}length/{$format}offset", substr( $data, $header['originals'] + 8 * $i, 8 ) );
		$t = unpack( "{$format}length/{$format}offset", substr( $data, $header['translations'] + 8 * $i, 8 ) );
		if ( $o['offset'] + $o['length'] > $length || $t['offset'] + $t['length'] > $length ) {
			continue;
		}
		$original    = substr( $data, $o['offset'], $o['length'] );
		$translation = substr( $data, $t['offset'], $t['length'] );
		if ( '' === $original || '' === $translation || false !== strpos( $original, "\0" ) || false !== strpos( $original, "\4" ) ) {
			continue;
		}
		$result[ $original ] = $translation;
	}

	return $result;
}

/**
 * Returns the translation of a string, or the string itself if there is none.
 *
 * @param string $text   English text.
 * @param string $domain Text domain. Always 'ladyqr'.
 * @return string
 */
function __( $text, $domain = 'ladyqr' ) {
	global $ladyqr_translations;
	return isset( $ladyqr_translations[ $text ] ) ? $ladyqr_translations[ $text ] : $text;
}

/**
 * Echoes a translated string. The string is trusted markup, not escaped.
 */
function _e( $text, $domain = 'ladyqr' ) {
	echo __( $text, $domain ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Returns a translated string escaped for HTML.
 */
function esc_html__( $text, $domain = 'ladyqr' ) {
	return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Echoes a translated string escaped for HTML.
 */
function esc_html_e( $text, $domain = 'ladyqr' ) {
	echo esc_html__( $text, $domain );
}

/**
 * Returns a translated string escaped for an HTML attribute.
 */
function esc_attr__( $text, $domain = 'ladyqr' ) {
	return esc_html__( $text, $domain );
}

/**
 * Picks the singular for exactly 1 and the plural for anything else.
 *
 * Pass two already translated strings, so translation tools see only the
 * __() calls. The caller applies sprintf() to put the number in.
 *
 * @param string $singular Translated singular string.
 * @param string $plural   Translated plural string.
 * @param int    $number   The count.
 * @return string
 */
function __plural( $singular, $plural, $number ) {
	return 1 === (int) $number ? $singular : $plural;
}

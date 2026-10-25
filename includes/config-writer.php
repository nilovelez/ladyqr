<?php
/**
 * Rewrites the password in the configuration file without breaking it.
 *
 * Used to replace the plain text password with its hash. The file is the
 * user's own, with comments and settings that must survive, so only the text of
 * one string is replaced, and the result is checked before it is put in place.
 */

defined( 'LADYQR' ) || exit;

/**
 * Finds the value of a constant defined with a plain string, such as the
 * 'text' in `define( 'LADYQR_PASSWORD', 'text' );`, skipping comments.
 *
 * It does not run the code. It walks the source, jumping over comments and
 * reading each quoted string, and returns null if the value is not a plain
 * string literal (for example a concatenation or a variable).
 *
 * @param string $src  Source of the configuration file.
 * @param string $name Name of the constant.
 * @return array|null The start and the end (exclusive) of the value, with its
 *                    quotes, and the value itself.
 */
function ladyqr_config_define_span( $src, $name ) {
	$len     = strlen( $src );
	$strings = array();
	$i       = 0;

	while ( $i < $len ) {
		$c = $src[ $i ];
		$n = $i + 1 < $len ? $src[ $i + 1 ] : '';

		if ( ( '/' === $c && '/' === $n ) || '#' === $c ) {
			$newline = strpos( $src, "\n", $i );
			$i       = false === $newline ? $len : $newline + 1;
		} elseif ( '/' === $c && '*' === $n ) {
			$end = strpos( $src, '*/', $i + 2 );
			$i   = false === $end ? $len : $end + 2;
		} elseif ( "'" === $c || '"' === $c ) {
			$start = $i;
			$value = '';
			++$i;
			while ( $i < $len && $src[ $i ] !== $c ) {
				if ( '\\' === $src[ $i ] && $i + 1 < $len ) {
					$next   = $src[ $i + 1 ];
					$value .= ( $c === $next || '\\' === $next ) ? $next : '\\' . $next;
					$i     += 2;
				} else {
					$value .= $src[ $i ];
					++$i;
				}
			}
			$strings[] = array( $start, $i + 1, $value, $c );
			++$i;
		} else {
			++$i;
		}
	}

	$count = count( $strings );
	for ( $k = 0; $k + 1 < $count; $k++ ) {
		if ( $name !== $strings[ $k ][2] ) {
			continue;
		}
		// define( 'NAME', 'value' ): the call before the name, a comma between
		// the two strings, and the end of the call after the value.
		$before  = substr( $src, max( 0, $strings[ $k ][0] - 40 ), min( 40, $strings[ $k ][0] ) );
		$between = substr( $src, $strings[ $k ][1], $strings[ $k + 1 ][0] - $strings[ $k ][1] );
		$after   = substr( $src, $strings[ $k + 1 ][1], 20 );
		if ( ! preg_match( '/define\s*\(\s*$/i', $before ) || ! preg_match( '/^\s*,\s*$/', $between ) || ! preg_match( '/^\s*\)/', $after ) ) {
			continue;
		}
		// A double-quoted value may hold a variable, so only plain single quotes count.
		if ( "'" !== $strings[ $k + 1 ][3] ) {
			return null;
		}
		return array( $strings[ $k + 1 ][0], $strings[ $k + 1 ][1], $strings[ $k + 1 ][2] );
	}
	return null;
}

/**
 * Replaces the value of LADYQR_PASSWORD in the configuration file.
 *
 * Only the text of the value changes: one single-quoted string for another, so
 * the file stays valid PHP. The new text is checked before it is put in place
 * and read back afterwards. No backup is left, because it would keep the plain
 * password. If the final check fails, the original text is written back.
 *
 * @param string $hash The value to store.
 * @return string 'secured' when done; 'not_writable', 'unsupported' (the value
 *                is not a plain string the file can be edited around) or
 *                'failed' (the safety check did not pass; nothing was changed).
 */
function ladyqr_config_set_password( $hash ) {
	$file = ladyqr_config_file();
	if ( null === $file || ! is_readable( $file ) ) {
		return 'unsupported';
	}

	// A file that is not writable was made so on purpose: leave it alone, even
	// if the folder would allow replacing it.
	if ( ! is_writable( $file ) ) {
		return 'not_writable';
	}

	$src = file_get_contents( $file );
	if ( false === $src ) {
		return 'failed';
	}
	$span = ladyqr_config_define_span( $src, 'LADYQR_PASSWORD' );
	if ( null === $span ) {
		return 'unsupported';
	}

	$literal = "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $hash ) . "'";
	$new     = substr( $src, 0, $span[0] ) . $literal . substr( $src, $span[1] );
	$check   = ladyqr_config_define_span( $new, 'LADYQR_PASSWORD' );
	if ( null === $check || $hash !== $check[2] ) {
		return 'failed';
	}

	if ( ladyqr_file_replace( $file, $new ) && file_get_contents( $file ) === $new ) {
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $file, true );
		}
		return 'secured';
	}

	// Something went wrong: put the original text back.
	ladyqr_file_replace( $file, $src );
	return 'failed';
}

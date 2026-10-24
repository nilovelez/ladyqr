<?php
/**
 * Rewrites one value in the configuration file without breaking it.
 *
 * Used to replace the plain text password with its hash. The file is the
 * user's own, with comments and settings that must survive, so only the text of
 * one string is replaced, and the result is checked before it is put in place.
 */

defined( 'LADYQR' ) || exit;

/**
 * Includes a configuration file and returns its array, or null if it cannot be
 * loaded or does not return an array. A syntax error is caught, not fatal.
 *
 * @param string $path File to include.
 * @return array|null
 */
function ladyqr_config_include( $path ) {
	try {
		$value = include $path;
	} catch ( Throwable $e ) {
		$value = null;
	}
	if ( function_exists( 'opcache_invalidate' ) ) {
		@opcache_invalidate( $path, true );
	}
	return is_array( $value ) ? $value : null;
}

/**
 * Finds the text of the string value that follows a quoted array key, such as
 * the 'text' in `'password' => 'text'`, skipping comments.
 *
 * It does not run the code. It walks the source, jumping over comments and
 * reading each quoted string, and returns null if the value is not a plain
 * string literal (for example a concatenation or a variable).
 *
 * @param string $src Source of the configuration file.
 * @param string $key Array key to look for.
 * @return array|null The start and the end (exclusive) of the value, with its quotes.
 */
function ladyqr_config_string_span( $src, $key ) {
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
			$strings[] = array( $start, $i + 1, $value );
			++$i;
		} else {
			++$i;
		}
	}

	$count = count( $strings );
	for ( $k = 0; $k + 1 < $count; $k++ ) {
		if ( $key !== $strings[ $k ][2] ) {
			continue;
		}
		$between = substr( $src, $strings[ $k ][1], $strings[ $k + 1 ][0] - $strings[ $k ][1] );
		if ( ! preg_match( '/^\s*=>\s*$/', $between ) ) {
			continue;
		}
		// What follows the value must end it, not continue an expression.
		$after = ltrim( substr( $src, $strings[ $k + 1 ][1], 60 ) );
		if ( '' !== $after && false === strpos( ",)]/#\n", $after[0] ) ) {
			return null;
		}
		return array( $strings[ $k + 1 ][0], $strings[ $k + 1 ][1] );
	}
	return null;
}

/**
 * Replaces the `password` value in the configuration file.
 *
 * The new version is built in a temporary file and loaded to check that it is
 * valid and gives the same settings except the password, and only then it is
 * put in place. No backup is left, because it would keep the plain password.
 * If the final check fails, the original text is written back.
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
	$dir         = dirname( $file );
	$dir_ok      = is_writable( $dir );
	$can_rename  = $dir_ok;
	$can_inplace = true;

	$src = file_get_contents( $file );
	if ( false === $src ) {
		return 'failed';
	}
	$span = ladyqr_config_string_span( $src, 'password' );
	if ( null === $span ) {
		return 'unsupported';
	}
	$original = ladyqr_config_include( $file );
	if ( null === $original ) {
		return 'failed';
	}

	$literal  = "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $hash ) . "'";
	$new      = substr( $src, 0, $span[0] ) . $literal . substr( $src, $span[1] );
	$expected = $original;

	$expected['password'] = $hash;

	// Build the new version aside and make sure it loads to what is expected.
	$tmp = ( $dir_ok ? $dir : sys_get_temp_dir() ) . '/ladyqr-config.tmp.' . bin2hex( random_bytes( 4 ) );
	if ( false === file_put_contents( $tmp, $new ) ) {
		return 'failed';
	}
	if ( ladyqr_config_include( $tmp ) !== $expected ) {
		@unlink( $tmp );
		return 'failed';
	}

	$done = false;
	if ( $can_rename ) {
		@chmod( $tmp, fileperms( $file ) & 0777 );
		$done = @rename( $tmp, $file );
	}
	if ( ! $done ) {
		// The folder is not writable or the rename failed: write the file itself.
		$done = $can_inplace && false !== file_put_contents( $file, $new );
	}
	@unlink( $tmp );
	clearstatcache( true, $file );

	if ( $done && ladyqr_config_include( $file ) === $expected ) {
		return 'secured';
	}

	// Something went wrong after the check: put the original text back.
	if ( $can_inplace ) {
		file_put_contents( $file, $src );
		clearstatcache( true, $file );
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $file, true );
		}
	}
	return 'failed';
}

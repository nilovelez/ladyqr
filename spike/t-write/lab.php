<?php
/**
 * Test 8: the .htaccess writer lab.
 *
 * It rehearses what the URL shortener will do: rewrite only a delimited block
 * of .htaccess, atomically, under a lock, keep a backup, check that the site
 * still answers, and roll back if it does not. It only ever touches
 * t-write/.htaccess, and only on POST requests.
 *
 * Delete the whole spike folder when you have finished testing.
 */

error_reporting( 0 );
ini_set( 'display_errors', '0' );
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

const SPIKE_BEGIN = '# BEGIN spike-redirects';
const SPIKE_END   = '# END spike-redirects';

$dir      = dirname( __FILE__ );
$htaccess = $dir . '/.htaccess';
$backup   = $htaccess . '.bak';
$lockfile = $dir . '/.lock';

$action = isset( $_GET['action'] ) ? (string) $_GET['action'] : 'info';
if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
	http_response_code( 405 );
	echo json_encode( array( 'error' => 'Use POST.' ) );
	exit;
}

/**
 * Returns the content with the managed block replaced, or removed when $rules
 * is null. Everything outside the block is kept exactly as it is.
 */
function spike_with_block( $content, $rules ) {
	$pattern = '/(?:\r?\n)?' . preg_quote( SPIKE_BEGIN, '/' ) . '.*?' . preg_quote( SPIKE_END, '/' ) . '(?:\r?\n)?/s';
	$content = preg_replace( $pattern, "\n", $content );
	$content = rtrim( $content ) . "\n";
	if ( null !== $rules ) {
		$content .= SPIKE_BEGIN . "\n" . $rules . "\n" . SPIKE_END . "\n";
	}
	return $content;
}

/**
 * Writes a file atomically: lock, backup, temporary file, rename.
 *
 * @return array Steps taken, with timings and the outcome.
 */
function spike_write_atomic( $path, $content, $backup, $lockfile ) {
	$report = array( 'ok' => false );
	$t0     = microtime( true );

	$handle = fopen( $lockfile, 'c' );
	if ( ! $handle ) {
		$report['error'] = 'Cannot open the lock file.';
		return $report;
	}
	$locked = false;
	for ( $i = 0; $i < 30 && ! $locked; $i++ ) {
		$locked = flock( $handle, LOCK_EX | LOCK_NB );
		if ( ! $locked ) {
			usleep( 100000 );
		}
	}
	$report['locked'] = $locked;
	if ( ! $locked ) {
		fclose( $handle );
		$report['error'] = 'Could not get the lock in 3 seconds.';
		return $report;
	}

	if ( is_file( $path ) ) {
		$report['backup_made'] = copy( $path, $backup );
	}
	$tmp = $path . '.tmp.' . bin2hex( random_bytes( 4 ) );
	if ( false === file_put_contents( $tmp, $content ) ) {
		$report['error'] = 'Cannot write the temporary file.';
	} else {
		chmod( $tmp, 0644 );
		$report['renamed'] = rename( $tmp, $path );
		if ( ! $report['renamed'] ) {
			@unlink( $tmp );
			$report['error'] = 'rename() over the existing file failed.';
		}
	}
	flock( $handle, LOCK_UN );
	fclose( $handle );

	clearstatcache();
	$report['perms'] = is_file( $path ) ? substr( sprintf( '%o', fileperms( $path ) ), -4 ) : null;
	$report['ms']    = round( ( microtime( true ) - $t0 ) * 1000 );
	$report['ok']    = ! isset( $report['error'] );
	return $report;
}

/**
 * Returns the URL of a file in this folder, as this server sees itself.
 */
function spike_self_url( $name ) {
	$https  = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] )
		|| ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] );
	$folder = rtrim( dirname( $_SERVER['SCRIPT_NAME'] ), '/' );
	return ( $https ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $folder . '/' . $name;
}

/**
 * Requests a URL from the server itself and returns the status code.
 *
 * @return array status (int or null), ms, via, error.
 */
function spike_status( $url ) {
	$t0  = microtime( true );
	$out = array( 'status' => null, 'via' => null, 'error' => null );

	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_TIMEOUT        => 6,
				CURLOPT_SSL_VERIFYPEER => false, // This is only a health check of the same server.
				CURLOPT_SSL_VERIFYHOST => 0,
			)
		);
		curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( $code > 0 ) {
			$out['status'] = $code;
			$out['via']    = 'curl';
		} else {
			$out['error'] = 'curl: ' . curl_error( $ch );
		}
		curl_close( $ch );
	}

	if ( null === $out['status'] && ini_get( 'allow_url_fopen' ) ) {
		$context = stream_context_create(
			array(
				'http' => array( 'timeout' => 6, 'ignore_errors' => true, 'follow_location' => 0 ),
				'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			)
		);
		$body = @file_get_contents( $url, false, $context );
		if ( isset( $http_response_header[0] ) && preg_match( '#\s(\d{3})\s#', $http_response_header[0], $m ) ) {
			$out['status'] = (int) $m[1];
			$out['via']    = 'streams';
		} elseif ( false === $body ) {
			$out['error'] = trim( ( null === $out['error'] ? '' : $out['error'] . '; ' ) . 'streams: no response' );
		}
	}

	$out['ms'] = round( ( microtime( true ) - $t0 ) * 1000 );
	return $out;
}

$current = is_file( $htaccess ) ? (string) file_get_contents( $htaccess ) : '';
$rules   = 'Redirect 302 /spike/t-write/dyn-test /spike/target.php?via=lab';

switch ( $action ) {
	case 'info':
		$health = spike_status( spike_self_url( 'ping.txt' ) );
		echo json_encode(
			array(
				'htaccess_exists'       => is_file( $htaccess ),
				'htaccess_writable'     => is_writable( $htaccess ),
				'dir_writable'          => is_writable( $dir ),
				'perms'                 => is_file( $htaccess ) ? substr( sprintf( '%o', fileperms( $htaccess ) ), -4 ) : null,
				'static_line_present'   => false !== strpos( $current, 'This line must survive every write.' ),
				'flock_available'       => function_exists( 'flock' ),
				'loopback'              => $health,
				'loopback_works'        => 200 === $health['status'],
				'curl'                  => function_exists( 'curl_init' ),
				'allow_url_fopen'       => (bool) ini_get( 'allow_url_fopen' ),
				'backup_exists'         => is_file( $backup ),
			)
		);
		break;

	case 'write':
	case 'remove':
		$new    = spike_with_block( $current, 'write' === $action ? $rules : null );
		$result = array( 'action' => $action, 'write' => spike_write_atomic( $htaccess, $new, $backup, $lockfile ) );
		$health = spike_status( spike_self_url( 'ping.txt' ) );
		$result['health'] = $health;
		if ( null !== $health['status'] && 200 !== $health['status'] && is_file( $backup ) ) {
			// The site stopped answering after the write: roll back.
			$result['rolled_back']   = spike_write_atomic( $htaccess, (string) file_get_contents( $backup ), $backup . '.2', $lockfile );
			$result['health_after']  = spike_status( spike_self_url( 'ping.txt' ) );
		}
		$after                     = (string) file_get_contents( $htaccess );
		$result['static_line_kept'] = false !== strpos( $after, 'This line must survive every write.' );
		$result['has_block']        = false !== strpos( $after, SPIKE_BEGIN ) && false !== strpos( $after, $rules );
		echo json_encode( $result );
		break;

	case 'break':
		// Writes an invalid directive on purpose, to see what the host does and
		// whether the rollback works. It only runs when the server can check itself.
		$before = spike_status( spike_self_url( 'ping.txt' ) );
		if ( 200 !== $before['status'] ) {
			echo json_encode( array( 'skipped' => 'The server cannot request itself, so there would be no way to detect a broken .htaccess.', 'before' => $before ) );
			break;
		}
		$result = array( 'before' => $before );
		$result['write']  = spike_write_atomic( $htaccess, spike_with_block( $current, 'InvalidDirectiveFromSpike on' ), $backup, $lockfile );
		$during           = spike_status( spike_self_url( 'ping.txt' ) );
		$result['during'] = $during;
		$result['detected_failure'] = 200 !== $during['status'];
		// Roll back from the backup taken just before the invalid write.
		$result['rolled_back']  = spike_write_atomic( $htaccess, (string) file_get_contents( $backup ), $backup . '.2', $lockfile );
		$result['health_after'] = spike_status( spike_self_url( 'ping.txt' ) );
		$result['healthy_after'] = 200 === $result['health_after']['status'];
		echo json_encode( $result );
		break;

	case 'hold':
		// Holds the lock for 1.5 seconds, so another request can try to take it.
		$handle = fopen( $lockfile, 'c' );
		$got    = $handle && flock( $handle, LOCK_EX );
		usleep( 1500000 );
		if ( $handle ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
		echo json_encode( array( 'held' => (bool) $got ) );
		break;

	case 'trylock':
		$handle = fopen( $lockfile, 'c' );
		$block  = 0;
		$got    = $handle && flock( $handle, LOCK_EX | LOCK_NB, $block );
		if ( $got ) {
			flock( $handle, LOCK_UN );
		}
		if ( $handle ) {
			fclose( $handle );
		}
		echo json_encode( array( 'acquired' => (bool) $got, 'wouldblock' => (bool) $block ) );
		break;

	default:
		http_response_code( 400 );
		echo json_encode( array( 'error' => 'Unknown action.' ) );
}

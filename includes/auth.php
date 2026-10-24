<?php
/**
 * Admin authentication.
 *
 * It uses PHP's native sessions and sets nothing that a shared host may
 * forbid, such as where sessions are stored. The only hardening is best
 * effort and can never break the login: the cookie flags that PHP exposes
 * through its own functions, and Secure only when the request is over HTTPS.
 */

defined( 'LADYQR' ) || exit;

/**
 * Says what the admin should do: 'locked' (no user and password, so nobody
 * gets in), 'open' (the owner turned the login off on purpose) or 'login'.
 */
function ladyqr_auth_mode() {
	$config = ladyqr_config();
	if ( ! $config['require_login'] ) {
		return 'open';
	}
	if ( '' === $config['user'] || '' === $config['password'] ) {
		return 'locked';
	}
	return 'login';
}

/**
 * Starts the session. Returns false when the server cannot keep sessions.
 */
function ladyqr_session_start() {
	if ( PHP_SESSION_ACTIVE === session_status() ) {
		return true;
	}
	@ini_set( 'session.use_only_cookies', '1' );
	@ini_set( 'session.use_strict_mode', '1' );
	if ( ! headers_sent() ) {
		try {
			@session_set_cookie_params(
				array(
					'lifetime' => 0,
					'secure'   => ladyqr_is_https(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		} catch ( Throwable $e ) {
			// An old PHP without the array form: carry on with the defaults.
			unset( $e );
		}
	}
	return @session_start();
}

/**
 * Ends the session and clears its cookie.
 */
function ladyqr_session_end() {
	if ( PHP_SESSION_ACTIVE !== session_status() ) {
		return;
	}
	$_SESSION = array();
	if ( ! headers_sent() ) {
		$params = session_get_cookie_params();
		setcookie( session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly'] );
	}
	session_destroy();
}

/**
 * Tells whether the visitor is logged in, and closes the session when it has
 * been idle for longer than the configured time. The time is kept inside the
 * session, so it needs no files and no cookies of our own.
 *
 * @param bool $expired Set to true when the session was just closed for being idle.
 */
function ladyqr_is_logged_in( &$expired = false ) {
	$expired = false;
	if ( empty( $_SESSION['ladyqr_auth'] ) ) {
		return false;
	}
	$config = ladyqr_config();
	$last   = isset( $_SESSION['ladyqr_last'] ) ? (int) $_SESSION['ladyqr_last'] : 0;
	if ( time() - $last > $config['session_timeout'] * 60 ) {
		ladyqr_session_end();
		$expired = true;
		return false;
	}
	$_SESSION['ladyqr_last'] = time();
	return true;
}

/**
 * Returns the token that forms must send back.
 */
function ladyqr_csrf_token() {
	if ( empty( $_SESSION['ladyqr_csrf'] ) ) {
		$_SESSION['ladyqr_csrf'] = bin2hex( random_bytes( 32 ) );
	}
	return $_SESSION['ladyqr_csrf'];
}

/**
 * Checks the token a form sent back.
 *
 * @param mixed $token What the form sent.
 */
function ladyqr_csrf_valid( $token ) {
	return ! empty( $_SESSION['ladyqr_csrf'] ) && is_string( $token ) && hash_equals( $_SESSION['ladyqr_csrf'], $token );
}

/**
 * Tells whether a stored password is already a hash. A hash is something that
 * password_get_info() recognizes, and a plain password never is.
 *
 * @param string $value The stored password.
 */
function ladyqr_password_is_hashed( $value ) {
	$info = password_get_info( (string) $value );
	return ! empty( $info['algo'] );
}

/**
 * Checks the user and the password against the configuration, in constant
 * time. The stored password may be a hash or still plain text.
 *
 * @param string $user     User the visitor typed.
 * @param string $password Password the visitor typed.
 */
function ladyqr_check_login( $user, $password ) {
	$config  = ladyqr_config();
	$stored  = $config['password'];
	$user_ok = hash_equals( $config['user'], (string) $user );
	if ( ladyqr_password_is_hashed( $stored ) ) {
		$pass_ok = password_verify( (string) $password, $stored );
	} else {
		$pass_ok = hash_equals( $stored, (string) $password );
	}
	return $user_ok && $pass_ok;
}

/**
 * After a successful login, stores the password as a hash if it is still plain
 * text, or refreshes the hash if PHP now prefers a stronger one.
 *
 * @param string $password The password the visitor typed, which has just been checked.
 * @return string 'none' (nothing to do), or what ladyqr_config_set_password() returns.
 */
function ladyqr_secure_password( $password ) {
	$stored = ladyqr_config()['password'];
	if ( ladyqr_password_is_hashed( $stored ) && ! password_needs_rehash( $stored, PASSWORD_DEFAULT ) ) {
		return 'none';
	}
	if ( ! function_exists( 'password_hash' ) ) {
		return 'failed';
	}
	$hash = password_hash( (string) $password, PASSWORD_DEFAULT );
	if ( ! is_string( $hash ) || '' === $hash ) {
		return 'failed';
	}
	return ladyqr_config_set_password( $hash );
}

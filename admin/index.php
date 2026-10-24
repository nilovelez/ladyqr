<?php
/**
 * LadyQR admin.
 *
 * A single page with four states: locked (no user and password set), open (the
 * owner turned the login off on purpose, with a permanent warning), the login
 * form, and the panel once logged in. The short links will be managed here in
 * a later version.
 */

define( 'LADYQR', true );
$ladyqr_root = dirname( __DIR__ );
require $ladyqr_root . '/includes/functions.php';
require $ladyqr_root . '/includes/config.php';
require $ladyqr_root . '/includes/i18n.php';
require $ladyqr_root . '/includes/auth.php';
require $ladyqr_root . '/includes/config-writer.php';

$ladyqr_config = ladyqr_config();
ladyqr_load_textdomain( $ladyqr_config['language'] );

header( 'Content-Type: text/html; charset=utf-8' );
header( "Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
header( 'X-Content-Type-Options: nosniff' );
header( 'Referrer-Policy: no-referrer' );
header( 'Cache-Control: no-store' );
header( 'X-Robots-Tag: noindex, nofollow' );

$ladyqr_mode      = ladyqr_auth_mode();
$ladyqr_view      = $ladyqr_mode; // locked, open or login.
$ladyqr_error     = '';           // login, csrf or session.
$ladyqr_expired   = false;
$ladyqr_secured   = '';           // What happened to the plain password after login.
$ladyqr_self      = strtok( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', '?' );

if ( 'login' === $ladyqr_mode ) {
	if ( ! ladyqr_session_start() ) {
		$ladyqr_error = 'session';
	} else {
		$ladyqr_logged_in = ladyqr_is_logged_in( $ladyqr_expired );
		if ( $ladyqr_expired ) {
			// The old session is gone: start a new one so the form has its token.
			ladyqr_session_start();
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$ladyqr_action = isset( $_POST['action'] ) ? (string) $_POST['action'] : '';
			if ( ! ladyqr_csrf_valid( isset( $_POST['csrf'] ) ? $_POST['csrf'] : '' ) ) {
				$ladyqr_error = 'csrf';
			} elseif ( 'logout' === $ladyqr_action && $ladyqr_logged_in ) {
				ladyqr_session_end();
				header( 'Location: ' . $ladyqr_self, true, 303 );
				exit;
			} elseif ( 'login' === $ladyqr_action && ! $ladyqr_logged_in ) {
				$ladyqr_user     = isset( $_POST['user'] ) ? (string) $_POST['user'] : '';
				$ladyqr_password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
				if ( ladyqr_check_login( $ladyqr_user, $ladyqr_password ) ) {
					session_regenerate_id( true );
					unset( $_SESSION['ladyqr_csrf'] );
					$_SESSION['ladyqr_auth']  = true;
					$_SESSION['ladyqr_last']  = time();
					$_SESSION['ladyqr_flash'] = ladyqr_secure_password( $ladyqr_password );
					header( 'Location: ' . $ladyqr_self, true, 303 );
					exit;
				}
				usleep( 1000000 ); // A pause after a wrong attempt slows down guessing.
				$ladyqr_error = 'login';
			}
		}

		if ( $ladyqr_logged_in ) {
			$ladyqr_view = 'panel';
			if ( isset( $_SESSION['ladyqr_flash'] ) ) {
				$ladyqr_secured = (string) $_SESSION['ladyqr_flash'];
				unset( $_SESSION['ladyqr_flash'] );
			}
		}
	}
}
$ladyqr_csrf = ( 'login' === $ladyqr_mode && PHP_SESSION_ACTIVE === session_status() ) ? ladyqr_csrf_token() : '';

/**
 * Prints a small warning triangle.
 */
function ladyqr_icon_warning() {
	echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars( str_replace( '_', '-', $ladyqr_config['language'] ), ENT_QUOTES, 'UTF-8' ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="color-scheme" content="light dark">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'LadyQR admin', 'ladyqr' ); ?></title>
	<link rel="icon" type="image/png" href="<?php echo ladyqr_asset( 'assets/icon-128.png', '../' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/app.css', '../' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/admin.css', '../' ); ?>">
</head>
<body class="admin">

<main>
	<?php if ( 'open' === $ladyqr_view ) : ?>
		<div class="banner" role="alert">
			<strong><?php esc_html_e( 'No login is required', 'ladyqr' ); ?></strong>
			<span><?php esc_html_e( 'Anyone who can reach this page can change your short links. To protect it, set a user and a password in the configuration file and remove the require_login setting.', 'ladyqr' ); ?></span>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<div class="admin-head">
				<img src="<?php echo ladyqr_asset( 'assets/icon-128.png', '../' ); ?>" alt="" width="44" height="44">
				<h1><?php esc_html_e( 'LadyQR admin', 'ladyqr' ); ?></h1>
			</div>

			<?php if ( 'locked' === $ladyqr_view ) : ?>

				<h2><?php esc_html_e( 'The admin is locked', 'ladyqr' ); ?></h2>
				<p><?php esc_html_e( 'No user and password are set yet, so nobody can log in. To set them, add these two lines to the configuration file, ladyqr-config.php, which goes in the web root or one level above it:', 'ladyqr' ); ?></p>
				<pre class="code"><code>'user'     => 'your-user',
'password' => 'your-password',</code></pre>
				<p><?php esc_html_e( 'Write the password in plain text. The first time you log in, LadyQR replaces it with a secure hash.', 'ladyqr' ); ?></p>
				<p><?php esc_html_e( 'If you do not have a configuration file yet, copy ladyqr-config.sample.php to ladyqr-config.php.', 'ladyqr' ); ?></p>

			<?php elseif ( 'login' === $ladyqr_view && 'session' === $ladyqr_error ) : ?>

				<p class="notice error" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Sessions do not work on this server, so the admin cannot keep you logged in.', 'ladyqr' ); ?></span></p>

			<?php elseif ( 'login' === $ladyqr_view ) : ?>

				<h2><?php esc_html_e( 'Log in', 'ladyqr' ); ?></h2>
				<?php if ( $ladyqr_expired ) : ?>
					<p class="notice info" role="status"><span><?php esc_html_e( 'Your session expired. Log in again.', 'ladyqr' ); ?></span></p>
				<?php endif; ?>
				<?php if ( 'login' === $ladyqr_error ) : ?>
					<p class="notice error" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Wrong user or password.', 'ladyqr' ); ?></span></p>
				<?php elseif ( 'csrf' === $ladyqr_error ) : ?>
					<p class="notice error" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'The form expired. Try again.', 'ladyqr' ); ?></span></p>
				<?php endif; ?>
				<form class="form" method="post" action="<?php echo htmlspecialchars( $ladyqr_self, ENT_QUOTES, 'UTF-8' ); ?>">
					<input type="hidden" name="action" value="login">
					<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $ladyqr_csrf, ENT_QUOTES, 'UTF-8' ); ?>">
					<div>
						<label class="label" for="user"><?php esc_html_e( 'User', 'ladyqr' ); ?></label>
						<input class="input" id="user" name="user" type="text" autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus>
					</div>
					<div>
						<label class="label" for="password"><?php esc_html_e( 'Password', 'ladyqr' ); ?></label>
						<input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
					</div>
					<?php if ( ! ladyqr_is_https() ) : ?>
						<p class="notice"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'This connection is not secure: your password would travel unencrypted.', 'ladyqr' ); ?></span></p>
					<?php endif; ?>
					<button class="btn" type="submit"><?php esc_html_e( 'Log in', 'ladyqr' ); ?></button>
				</form>

			<?php else : // The panel, logged in or open. ?>

				<h2><?php echo 'open' === $ladyqr_view ? esc_html__( 'Short links', 'ladyqr' ) : esc_html__( 'You are logged in', 'ladyqr' ); ?></h2>
				<?php if ( 'secured' === $ladyqr_secured ) : ?>
					<p class="notice info" role="status"><span><?php esc_html_e( 'Your password is now stored as a secure hash in the configuration file.', 'ladyqr' ); ?></span></p>
				<?php elseif ( 'not_writable' === $ladyqr_secured ) : ?>
					<p class="notice" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Your password is still stored in plain text, because LadyQR cannot write to the configuration file. Make the file writable and log in again, and it will be secured.', 'ladyqr' ); ?></span></p>
				<?php elseif ( 'unsupported' === $ladyqr_secured ) : ?>
					<p class="notice" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Your password is still stored in plain text, because it is not written in a form that LadyQR can replace by itself. Write it as \'password\' => \'your-password\', with plain quotes, and log in again.', 'ladyqr' ); ?></span></p>
				<?php elseif ( 'failed' === $ladyqr_secured ) : ?>
					<p class="notice" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Your password could not be secured, because the new configuration file did not pass its safety check. The file was not changed.', 'ladyqr' ); ?></span></p>
				<?php endif; ?>
				<p><?php esc_html_e( 'The short links will be managed here in a future version.', 'ladyqr' ); ?></p>
				<?php if ( 'login' === $ladyqr_mode ) : ?>
					<form method="post" action="<?php echo htmlspecialchars( $ladyqr_self, ENT_QUOTES, 'UTF-8' ); ?>">
						<input type="hidden" name="action" value="logout">
						<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $ladyqr_csrf, ENT_QUOTES, 'UTF-8' ); ?>">
						<button class="btn secondary" type="submit"><?php esc_html_e( 'Log out', 'ladyqr' ); ?></button>
					</form>
				<?php endif; ?>

			<?php endif; ?>
		</div>
	</div>
</main>

<footer>
	<a href="../"><?php esc_html_e( 'Back to the QR code editor', 'ladyqr' ); ?></a>
</footer>

</body>
</html>

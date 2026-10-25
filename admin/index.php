<?php
/**
 * LadyQR admin.
 *
 * A single page with four states: locked (no user and password set), open (the
 * owner turned the login off on purpose, with a permanent warning), the login
 * form, and the panel once logged in. The panel lists, adds, edits and deletes
 * the short links, which live in the managed block of the root .htaccess.
 */

define( 'LADYQR', true );
$ladyqr_root = dirname( __DIR__ );
require $ladyqr_root . '/includes/functions.php';
require $ladyqr_root . '/includes/config.php';
require $ladyqr_root . '/includes/i18n.php';
require $ladyqr_root . '/includes/auth.php';
require $ladyqr_root . '/includes/config-writer.php';
require $ladyqr_root . '/includes/links.php';
require $ladyqr_root . '/includes/htaccess.php';

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
$ladyqr_link_action = ''; // add, edit or delete, once the request is known to be allowed.

if ( 'login' === $ladyqr_mode ) {
	if ( ! ladyqr_session_start() ) {
		$ladyqr_error = 'session';
	} else {
		$ladyqr_logged_in = ladyqr_is_logged_in( $ladyqr_expired );
		if ( $ladyqr_expired ) {
			// The old session is gone: start a new one so the form has its token.
			ladyqr_session_start();
		}

		// Logging out is a link, so it carries the session's token in the
		// address: another site cannot log you out without knowing it.
		if ( $ladyqr_logged_in && isset( $_GET['logout'] ) && ladyqr_csrf_valid( (string) $_GET['logout'] ) ) {
			ladyqr_session_end();
			header( 'Location: ' . $ladyqr_self, true, 303 );
			exit;
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$ladyqr_action = isset( $_POST['action'] ) ? (string) $_POST['action'] : '';
			if ( ! ladyqr_csrf_valid( isset( $_POST['csrf'] ) ? $_POST['csrf'] : '' ) ) {
				$ladyqr_error = 'csrf';
			} elseif ( $ladyqr_logged_in && in_array( $ladyqr_action, array( 'add', 'edit', 'delete' ), true ) ) {
				$ladyqr_link_action = $ladyqr_action;
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
if ( 'open' === $ladyqr_mode && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'add', 'edit', 'delete' ), true ) ) {
	$ladyqr_link_action = $_POST['action']; // Nobody is logged in, so there is nothing to protect with a token.
}

// The short links.
$ladyqr_links_state = null;
$ladyqr_links       = array();
$ladyqr_screen      = 'list'; // list, edit or delete.
$ladyqr_form        = array( 'original' => '', 'slug' => '', 'target' => '', 'status' => 302 );
$ladyqr_problems    = array();
$ladyqr_save        = '';     // What went wrong when saving: not_writable, rolled_back or failed.
$ladyqr_done        = '';
$ladyqr_done_check  = false;
$ladyqr_host        = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
$ladyqr_base        = ladyqr_base_path( 1 );
$ladyqr_origin      = ( ladyqr_is_https() ? 'https://' : 'http://' ) . $ladyqr_host . $ladyqr_base . '/';

if ( in_array( $ladyqr_view, array( 'panel', 'open' ), true ) ) {
	$ladyqr_links_state = ladyqr_links_load();
	if ( 'ok' === $ladyqr_links_state['state'] ) {
		$ladyqr_links = $ladyqr_links_state['links'];

		if ( '' !== $ladyqr_link_action ) {
			$ladyqr_form = array(
				'original' => isset( $_POST['original'] ) ? (string) $_POST['original'] : '',
				'slug'     => isset( $_POST['slug'] ) ? trim( (string) $_POST['slug'] ) : '',
				'target'   => isset( $_POST['target'] ) ? trim( (string) $_POST['target'] ) : '',
				'status'   => isset( $_POST['status'] ) && '301' === $_POST['status'] ? 301 : 302,
			);
			$ladyqr_new  = $ladyqr_links;
			$ladyqr_word = 'added';
			$ladyqr_screen = 'list';

			if ( 'delete' === $ladyqr_link_action ) {
				$ladyqr_screen = 'delete';
				$ladyqr_word   = 'deleted';
				if ( isset( $ladyqr_new[ $ladyqr_form['original'] ] ) ) {
					unset( $ladyqr_new[ $ladyqr_form['original'] ] );
				} else {
					$ladyqr_problems[] = 'missing';
				}
			} else {
				$ladyqr_editing = 'edit' === $ladyqr_link_action;
				if ( $ladyqr_editing ) {
					$ladyqr_screen = 'edit';
					$ladyqr_word   = 'updated';
					if ( ! isset( $ladyqr_new[ $ladyqr_form['original'] ] ) ) {
						$ladyqr_problems[] = 'missing';
					}
				}
				$ladyqr_code = ladyqr_link_check_slug( $ladyqr_form['slug'], array_keys( $ladyqr_links ), $ladyqr_root, $ladyqr_editing ? $ladyqr_form['original'] : null );
				if ( '' !== $ladyqr_code ) {
					$ladyqr_problems[] = $ladyqr_code;
				}
				$ladyqr_code = ladyqr_link_check_target( $ladyqr_form['target'], $ladyqr_host, $ladyqr_base . '/' . $ladyqr_form['slug'] );
				if ( '' !== $ladyqr_code ) {
					$ladyqr_problems[] = $ladyqr_code;
				}
				if ( array() === $ladyqr_problems ) {
					if ( $ladyqr_editing ) {
						unset( $ladyqr_new[ $ladyqr_form['original'] ] );
					}
					$ladyqr_new[ $ladyqr_form['slug'] ] = array( 'status' => $ladyqr_form['status'], 'target' => $ladyqr_form['target'] );
				}
			}

			if ( array() === $ladyqr_problems ) {
				$ladyqr_result = ladyqr_links_save( $ladyqr_links_state, $ladyqr_new );
				if ( 'saved' === $ladyqr_result || 'unverified' === $ladyqr_result ) {
					header( 'Location: ' . $ladyqr_self . '?done=' . $ladyqr_word . ( 'unverified' === $ladyqr_result ? '&check=none' : '' ), true, 303 );
					exit;
				}
				$ladyqr_save = $ladyqr_result;
			}
		} elseif ( isset( $_GET['edit'] ) && isset( $ladyqr_links[ (string) $_GET['edit'] ] ) ) {
			$ladyqr_screen = 'edit';
			$ladyqr_slug   = (string) $_GET['edit'];
			$ladyqr_form   = array( 'original' => $ladyqr_slug, 'slug' => $ladyqr_slug, 'target' => $ladyqr_links[ $ladyqr_slug ]['target'], 'status' => $ladyqr_links[ $ladyqr_slug ]['status'] );
		} elseif ( isset( $_GET['delete'] ) && isset( $ladyqr_links[ (string) $_GET['delete'] ] ) ) {
			$ladyqr_screen = 'delete';
			$ladyqr_slug   = (string) $_GET['delete'];
			$ladyqr_form   = array( 'original' => $ladyqr_slug, 'slug' => $ladyqr_slug, 'target' => $ladyqr_links[ $ladyqr_slug ]['target'], 'status' => $ladyqr_links[ $ladyqr_slug ]['status'] );
		}

		if ( isset( $_GET['done'] ) && in_array( $_GET['done'], array( 'added', 'updated', 'deleted' ), true ) ) {
			$ladyqr_done       = $_GET['done'];
			$ladyqr_done_check = isset( $_GET['check'] ) && 'none' === $_GET['check'];
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

/**
 * Returns the message for a problem found in a form.
 */
function ladyqr_problem_text( $code ) {
	switch ( $code ) {
		case 'slug_invalid':
			return esc_html__( 'The short name can only have letters, digits and hyphens, up to 64 characters.', 'ladyqr' );
		case 'slug_reserved':
			return esc_html__( 'That name is reserved, because it is used by a file or a folder of the site.', 'ladyqr' );
		case 'slug_taken':
			return esc_html__( 'There is already a short link with that name.', 'ladyqr' );
		case 'target_invalid':
			return esc_html__( 'The address must start with http:// or https://, have no spaces, and use only plain ASCII characters (write other characters percent-encoded).', 'ladyqr' );
		case 'target_self':
			return esc_html__( 'A short link cannot point to itself.', 'ladyqr' );
		default:
			return esc_html__( 'That short link no longer exists. Reload the list.', 'ladyqr' );
	}
}

/**
 * Prints the fields shared by the add and edit forms.
 *
 * @param array  $form     The values.
 * @param array  $problems The problem codes found.
 * @param string $origin   The address of the site, shown in front of the name.
 * @param string $id       A prefix to keep the ids of two forms apart.
 */
function ladyqr_link_fields( array $form, array $problems, $origin, $id ) {
	$slug_problems   = array_values( array_intersect( $problems, array( 'slug_invalid', 'slug_reserved', 'slug_taken', 'missing' ) ) );
	$target_problems = array_values( array_intersect( $problems, array( 'target_invalid', 'target_self' ) ) );
	?>
	<div>
		<label class="label" for="<?php echo $id; ?>-slug"><?php esc_html_e( 'Short name', 'ladyqr' ); ?></label>
		<div class="input-group">
			<span class="addon"><?php echo htmlspecialchars( preg_replace( '~^https?://~', '', $origin ), ENT_QUOTES, 'UTF-8' ); ?></span>
			<input class="input" id="<?php echo $id; ?>-slug" name="slug" type="text" value="<?php echo htmlspecialchars( $form['slug'], ENT_QUOTES, 'UTF-8' ); ?>" maxlength="64" autocapitalize="off" autocomplete="off" spellcheck="false" required<?php echo $slug_problems ? ' aria-invalid="true" aria-describedby="' . $id . '-slug-error"' : ''; ?>>
		</div>
		<?php if ( $slug_problems ) : ?>
			<p class="field-error" id="<?php echo $id; ?>-slug-error"><?php echo ladyqr_problem_text( $slug_problems[0] ); ?></p>
		<?php endif; ?>
	</div>
	<div>
		<label class="label" for="<?php echo $id; ?>-target"><?php esc_html_e( 'Goes to', 'ladyqr' ); ?></label>
		<input class="input" id="<?php echo $id; ?>-target" name="target" type="text" inputmode="url" value="<?php echo htmlspecialchars( $form['target'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://example.com/a/long/address" autocapitalize="off" autocomplete="off" spellcheck="false" required<?php echo $target_problems ? ' aria-invalid="true" aria-describedby="' . $id . '-target-error"' : ''; ?>>
		<?php if ( $target_problems ) : ?>
			<p class="field-error" id="<?php echo $id; ?>-target-error"><?php echo ladyqr_problem_text( $target_problems[0] ); ?></p>
		<?php endif; ?>
	</div>
	<div>
		<label class="label" for="<?php echo $id; ?>-status"><?php esc_html_e( 'Type', 'ladyqr' ); ?></label>
		<select class="input" id="<?php echo $id; ?>-status" name="status" aria-describedby="<?php echo $id; ?>-status-help">
			<option value="301"<?php echo 301 === $form['status'] ? ' selected' : ''; ?>><?php esc_html_e( '301 – Permanent redirect', 'ladyqr' ); ?></option>
			<option value="302"<?php echo 302 === $form['status'] ? ' selected' : ''; ?>><?php esc_html_e( '302 – Temporary redirect', 'ladyqr' ); ?></option>
		</select>
		<p class="hint" id="<?php echo $id; ?>-status-help"><?php esc_html_e( 'Browsers remember permanent redirects, so a later change may take a long time to show. Use 302 unless the address will never change.', 'ladyqr' ); ?></p>
	</div>
	<?php
}

/**
 * Prints the message for a failed save.
 */
function ladyqr_save_notice( $result ) {
	if ( '' === $result ) {
		return;
	}
	if ( 'not_writable' === $result ) {
		$text = esc_html__( 'LadyQR cannot write to the .htaccess file. Make it writable (for example, permissions 644 and owned by the web server\'s user) and try again.', 'ladyqr' );
	} elseif ( 'rolled_back' === $result ) {
		$text = esc_html__( 'The change made the site stop answering, so it was undone. Nothing was changed.', 'ladyqr' );
	} else {
		$text = esc_html__( 'The .htaccess file could not be saved. Nothing was changed.', 'ladyqr' );
	}
	echo '<p class="notice error" role="alert">';
	ladyqr_icon_warning();
	echo '<span>' . $text . '</span></p>';
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars( str_replace( '_', '-', $ladyqr_config['language'] ), ENT_QUOTES, 'UTF-8' ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="color-scheme" content="light">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'LadyQR admin', 'ladyqr' ); ?></title>
	<link rel="icon" type="image/png" href="<?php echo ladyqr_asset( 'assets/icon-128.png', '../' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/app.css', '../' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/admin.css', '../' ); ?>">
</head>
<body class="admin<?php echo in_array( $ladyqr_view, array( 'panel', 'open' ), true ) ? ' admin-panel' : ''; ?>">

<main>
	<?php if ( 'open' === $ladyqr_view ) : ?>
		<div class="banner" role="alert">
			<strong><?php esc_html_e( 'No login is required', 'ladyqr' ); ?></strong>
			<span><?php esc_html_e( 'Anyone who can reach this page can change your short links. To protect it, set a user and a password in the configuration file and remove the require_login setting.', 'ladyqr' ); ?></span>
		</div>
	<?php elseif ( 'panel' === $ladyqr_view ) : ?>
		<p class="account">
			<?php
			/* translators: %s: the user's name. */
			printf( esc_html__( 'Signed in as %s', 'ladyqr' ), '<strong>' . htmlspecialchars( $ladyqr_config['user'], ENT_QUOTES, 'UTF-8' ) . '</strong>' );
			?>
			&ndash; <a href="<?php echo htmlspecialchars( $ladyqr_self . '?logout=' . rawurlencode( $ladyqr_csrf ), ENT_QUOTES, 'UTF-8' ); ?>"><?php esc_html_e( 'Log out', 'ladyqr' ); ?></a>
		</p>
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
					<button class="btn block" type="submit"><?php esc_html_e( 'Log in', 'ladyqr' ); ?></button>
				</form>

			<?php else : // The panel, logged in or open. ?>

				<?php if ( 'edit' === $ladyqr_screen ) : ?>
					<h2><?php esc_html_e( 'Edit short link', 'ladyqr' ); ?></h2>
				<?php elseif ( 'delete' === $ladyqr_screen ) : ?>
					<h2><?php esc_html_e( 'Delete short link', 'ladyqr' ); ?></h2>
				<?php else : ?>
					<h2><?php esc_html_e( 'Short links', 'ladyqr' ); ?></h2>
				<?php endif; ?>
				<?php if ( 'secured' === $ladyqr_secured ) : ?>
					<p class="notice info" role="status"><span><?php esc_html_e( 'Your password is now stored as a secure hash in the configuration file.', 'ladyqr' ); ?></span></p>
				<?php elseif ( 'not_writable' === $ladyqr_secured ) : ?>
					<p class="notice" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Your password is still stored in plain text, because LadyQR cannot write to the configuration file. Make the file writable and log in again, and it will be secured.', 'ladyqr' ); ?></span></p>
				<?php elseif ( 'unsupported' === $ladyqr_secured ) : ?>
					<p class="notice" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Your password is still stored in plain text, because it is not written in a form that LadyQR can replace by itself. Write it as \'password\' => \'your-password\', with plain quotes, and log in again.', 'ladyqr' ); ?></span></p>
				<?php elseif ( 'failed' === $ladyqr_secured ) : ?>
					<p class="notice" role="alert"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Your password could not be secured, because the new configuration file did not pass its safety check. The file was not changed.', 'ladyqr' ); ?></span></p>
				<?php endif; ?>
<?php
				$ladyqr_csrf_field = '<input type="hidden" name="csrf" value="' . htmlspecialchars( $ladyqr_csrf, ENT_QUOTES, 'UTF-8' ) . '">';
				$ladyqr_action_url = htmlspecialchars( $ladyqr_self, ENT_QUOTES, 'UTF-8' );
				?>

				<?php if ( 'ok' !== $ladyqr_links_state['state'] ) : ?>
					<p class="notice error" role="alert"><?php ladyqr_icon_warning(); ?><span>
						<?php if ( 'missing' === $ladyqr_links_state['state'] ) : ?>
							<?php esc_html_e( 'There is no .htaccess file in the site\'s folder, and the short links are stored in it. Upload the .htaccess file that comes with LadyQR.', 'ladyqr' ); ?>
						<?php elseif ( 'damaged' === $ladyqr_links_state['state'] ) : ?>
							<?php esc_html_e( 'The block of short links in the .htaccess file is damaged (a "BEGIN LadyQR short links" or "END LadyQR short links" line is missing or repeated), so LadyQR will not touch it. Fix it by hand or remove the block.', 'ladyqr' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'The .htaccess file cannot be read.', 'ladyqr' ); ?>
						<?php endif; ?>
					</span></p>

				<?php elseif ( 'edit' === $ladyqr_screen ) : ?>

					<?php ladyqr_save_notice( $ladyqr_save ); ?>
					<form class="form" method="post" action="<?php echo $ladyqr_action_url; ?>">
						<input type="hidden" name="action" value="edit">
						<?php echo $ladyqr_csrf_field; ?>
						<input type="hidden" name="original" value="<?php echo htmlspecialchars( $ladyqr_form['original'], ENT_QUOTES, 'UTF-8' ); ?>">
						<?php ladyqr_link_fields( $ladyqr_form, $ladyqr_problems, $ladyqr_origin, 'edit' ); ?>
						<div class="form-actions">
							<button class="btn" type="submit"><?php esc_html_e( 'Save changes', 'ladyqr' ); ?></button>
							<a class="btn secondary" href="<?php echo $ladyqr_action_url; ?>"><?php esc_html_e( 'Cancel', 'ladyqr' ); ?></a>
						</div>
					</form>

				<?php elseif ( 'delete' === $ladyqr_screen ) : ?>

					<?php ladyqr_save_notice( $ladyqr_save ); ?>
					<?php if ( in_array( 'missing', $ladyqr_problems, true ) ) : ?>
						<p class="notice error" role="alert"><?php ladyqr_icon_warning(); ?><span><?php echo ladyqr_problem_text( 'missing' ); ?></span></p>
						<div class="form-actions">
							<a class="btn secondary" href="<?php echo $ladyqr_action_url; ?>"><?php esc_html_e( 'Back to the list', 'ladyqr' ); ?></a>
						</div>
					<?php else : ?>
						<p><?php esc_html_e( 'Delete this short link? Anyone who uses it will no longer be redirected.', 'ladyqr' ); ?></p>
						<p class="doomed"><strong>/<?php echo htmlspecialchars( $ladyqr_form['slug'], ENT_QUOTES, 'UTF-8' ); ?></strong> <span class="code-tag"><?php echo (int) $ladyqr_form['status']; ?></span><span class="target"><?php echo htmlspecialchars( $ladyqr_form['target'], ENT_QUOTES, 'UTF-8' ); ?></span></p>
						<form class="form" method="post" action="<?php echo $ladyqr_action_url; ?>">
							<input type="hidden" name="action" value="delete">
							<?php echo $ladyqr_csrf_field; ?>
							<input type="hidden" name="original" value="<?php echo htmlspecialchars( $ladyqr_form['original'], ENT_QUOTES, 'UTF-8' ); ?>">
							<div class="form-actions">
								<button class="btn" type="submit"><?php esc_html_e( 'Delete', 'ladyqr' ); ?></button>
								<a class="btn secondary" href="<?php echo $ladyqr_action_url; ?>"><?php esc_html_e( 'Cancel', 'ladyqr' ); ?></a>
							</div>
						</form>
					<?php endif; ?>

				<?php else : ?>

					<?php if ( 'added' === $ladyqr_done ) : ?>
						<p class="notice info" role="status"><span><?php esc_html_e( 'The short link was added.', 'ladyqr' ); ?></span></p>
					<?php elseif ( 'updated' === $ladyqr_done ) : ?>
						<p class="notice info" role="status"><span><?php esc_html_e( 'The short link was updated.', 'ladyqr' ); ?></span></p>
					<?php elseif ( 'deleted' === $ladyqr_done ) : ?>
						<p class="notice info" role="status"><span><?php esc_html_e( 'The short link was deleted.', 'ladyqr' ); ?></span></p>
					<?php endif; ?>
					<?php if ( $ladyqr_done_check ) : ?>
						<p class="notice" role="status"><?php ladyqr_icon_warning(); ?><span><?php esc_html_e( 'Saved, but LadyQR could not check that the site still answers. Open your site to make sure it works.', 'ladyqr' ); ?></span></p>
					<?php endif; ?>
					<?php ladyqr_save_notice( $ladyqr_save ); ?>

					<?php if ( array() === $ladyqr_links ) : ?>
						<p class="empty"><?php esc_html_e( 'You have no short links yet.', 'ladyqr' ); ?></p>
					<?php else : ?>
						<div class="table-wrap">
							<table class="links">
								<thead>
									<tr>
										<th class="col-short" scope="col"><?php esc_html_e( 'Short link', 'ladyqr' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Goes to', 'ladyqr' ); ?></th>
										<th class="col-code" scope="col"><?php esc_html_e( 'Code', 'ladyqr' ); ?></th>
										<th class="col-actions" scope="col"><span class="visually-hidden"><?php esc_html_e( 'Actions', 'ladyqr' ); ?></span></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $ladyqr_links as $ladyqr_slug => $ladyqr_link ) : ?>
										<tr>
											<td class="col-short"><a class="short" href="<?php echo htmlspecialchars( $ladyqr_origin . $ladyqr_slug, ENT_QUOTES, 'UTF-8' ); ?>" rel="noopener">/<?php echo htmlspecialchars( $ladyqr_slug, ENT_QUOTES, 'UTF-8' ); ?></a></td>
											<td class="target" title="<?php echo htmlspecialchars( $ladyqr_link['target'], ENT_QUOTES, 'UTF-8' ); ?>"><?php echo htmlspecialchars( $ladyqr_link['target'], ENT_QUOTES, 'UTF-8' ); ?></td>
											<td class="col-code"><span class="code-tag"><?php echo (int) $ladyqr_link['status']; ?></span></td>
											<td class="col-actions">
												<span class="row-actions">
													<a href="<?php echo $ladyqr_action_url . '?edit=' . rawurlencode( $ladyqr_slug ); ?>"><?php esc_html_e( 'Edit', 'ladyqr' ); ?></a>
													<a href="<?php echo $ladyqr_action_url . '?delete=' . rawurlencode( $ladyqr_slug ); ?>"><?php esc_html_e( 'Delete', 'ladyqr' ); ?></a>
												</span>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>

					<h3><?php esc_html_e( 'Add a short link', 'ladyqr' ); ?></h3>
					<form class="form" method="post" action="<?php echo $ladyqr_action_url; ?>">
						<input type="hidden" name="action" value="add">
						<?php echo $ladyqr_csrf_field; ?>
						<?php ladyqr_link_fields( 'add' === $ladyqr_link_action ? $ladyqr_form : array( 'slug' => '', 'target' => '', 'status' => 302 ), 'add' === $ladyqr_link_action ? $ladyqr_problems : array(), $ladyqr_origin, 'add' ); ?>
						<div class="form-actions">
							<button class="btn" type="submit"><?php esc_html_e( 'Add short link', 'ladyqr' ); ?></button>
						</div>
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

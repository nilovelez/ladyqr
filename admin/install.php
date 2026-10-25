<?php
/**
 * LadyQR installer.
 *
 * Every visit goes here while there is no configuration file. Four screens:
 *
 * 1. The language of the installer, English by default.
 * 2. A short welcome and how LadyQR will be used: the QR code generator alone,
 *    or with the link shortener.
 * 3. The server checks for that choice and its settings, starting with the
 *    language of the site, which can differ from the installer's.
 * 4. The result: the files to create by hand if LadyQR could not write them,
 *    the user and password once, and where to go next.
 *
 * Nothing is written until the settings of screen 3 are sent, so going back is
 * always free. The installer's language travels in the address, not in the
 * session, which has not been checked yet. Once a configuration file exists the
 * installer does nothing.
 */

define( 'LADYQR', true );
$ladyqr_root = dirname( __DIR__ );
require $ladyqr_root . '/includes/functions.php';
require $ladyqr_root . '/includes/config.php';
require $ladyqr_root . '/includes/i18n.php';
require $ladyqr_root . '/includes/auth.php';
require $ladyqr_root . '/includes/config-writer.php';
require $ladyqr_root . '/includes/links.php';
require $ladyqr_root . '/includes/data.php';
require $ladyqr_root . '/includes/htaccess.php';

header( 'Content-Type: text/html; charset=utf-8' );
header( "Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
header( 'X-Content-Type-Options: nosniff' );
header( 'Referrer-Policy: no-referrer' );
header( 'Cache-Control: no-store' );
header( 'X-Robots-Tag: noindex, nofollow' );

/**
 * Escapes text for HTML.
 *
 * @param string $text The text.
 */
function ladyqr_h( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Returns a random password of 20 letters and digits, leaving out the ones that
 * are easy to mistake for each other (0 O o 1 l I).
 */
function ladyqr_suggest_password() {
	$chars    = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$password = '';
	for ( $i = 0; $i < 20; $i++ ) {
		$password .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $password;
}

/**
 * Returns the languages there are translations for, as locale => name, each
 * name written in its own language.
 */
function ladyqr_install_languages() {
	$names     = array(
		'en_US' => 'English',
		'es_ES' => 'Español',
	);
	$languages = array( 'en_US' => 'English' );
	foreach ( (array) glob( dirname( __DIR__ ) . '/languages/ladyqr-*.mo' ) as $file ) {
		if ( preg_match( '/ladyqr-([a-z]{2,3}(_[A-Z]{2})?)\.mo$/', $file, $m ) ) {
			$languages[ $m[1] ] = isset( $names[ $m[1] ] ) ? $names[ $m[1] ] : $m[1];
		}
	}
	return $languages;
}

/**
 * Tells whether `.htaccess` can send an address that is not a file to PHP on
 * this server, with a throwaway folder that holds its own `.htaccess` and a
 * small script. The folder is removed afterwards.
 *
 * @param string $origin The scheme and host of the site.
 * @param string $base   The path of the site on its host.
 * @return bool|null Null when it could not be tested.
 */
function ladyqr_install_route_check( $origin, $base ) {
	$root = dirname( __DIR__ );
	if ( ! is_writable( $root ) ) {
		return null;
	}
	$name = '.ladyqr-route-check-' . bin2hex( random_bytes( 4 ) );
	$dir  = $root . '/' . $name;
	if ( ! @mkdir( $dir ) ) {
		return null;
	}
	$rules = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ " . $base . '/' . $name . "/check.php [L]\n</IfModule>\n";
	$ok    = false !== @file_put_contents( $dir . '/.htaccess', $rules )
		&& false !== @file_put_contents( $dir . '/check.php', "<?php\nheader( 'X-LadyQR-Route: ok' );\nhttp_response_code( 204 );\n" );
	$works = null;
	if ( $ok ) {
		$answer = ladyqr_http_head( $origin . $base . '/' . $name . '/anything' );
		if ( null !== $answer ) {
			$works = isset( $answer['headers']['x-ladyqr-route'] ) && 'ok' === $answer['headers']['x-ladyqr-route'];
		}
	}
	@unlink( $dir . '/.htaccess' );
	@unlink( $dir . '/check.php' );
	@rmdir( $dir );
	return $works;
}

/**
 * Returns the text of the configuration file. It is always in English.
 *
 * @param array $values language and shortener; url, user and password (the hash) when there are short links.
 */
function ladyqr_install_config_text( array $values ) {
	$lines   = array();
	$lines[] = '<?php';
	$lines[] = '/**';
	$lines[] = ' * LadyQR configuration, written by the installer on ' . gmdate( 'Y-m-d' ) . '.';
	$lines[] = ' *';
	$lines[] = ' * Keep the line below: if someone requests this file directly, it stops and';
	$lines[] = ' * prints nothing.';
	$lines[] = ' */';
	$lines[] = "defined( 'LADYQR' ) || exit;";
	$lines[] = '';
	$lines[] = '// Interface language, as a WordPress-style locale.';
	$lines[] = "define( 'LADYQR_LANGUAGE', " . var_export( $values['language'], true ) . ' );';
	$lines[] = '';
	if ( isset( $values['url'] ) ) {
		$lines[] = '// The address of the site, without a final slash.';
		$lines[] = "define( 'LADYQR_URL', " . var_export( $values['url'], true ) . ' );';
		$lines[] = '';
	}
	$lines[] = '// Short links. They need .htaccess to send the addresses that are not files';
	$lines[] = '// to index.php.';
	$lines[] = "define( 'LADYQR_SHORTENER', " . ( $values['shortener'] ? 'true' : 'false' ) . ' );';
	if ( isset( $values['user'] ) ) {
		$lines[] = '';
		$lines[] = '// The admin\'s user and password. The password is stored as a hash. To change';
		$lines[] = '// it, write the new one here in plain text: LadyQR secures it at the next login.';
		$lines[] = "define( 'LADYQR_USER', " . var_export( $values['user'], true ) . ' );';
		$lines[] = "define( 'LADYQR_PASSWORD', " . var_export( $values['password'], true ) . ' );';
	}
	return implode( "\n", $lines ) . "\n";
}

/**
 * Prints the page around the content.
 *
 * @param string   $title   The heading.
 * @param callable $content Prints the content.
 */
function ladyqr_install_page( $title, $content ) {
	global $ladyqr_lang;
	?>
<!doctype html>
<html lang="<?php echo ladyqr_h( str_replace( '_', '-', $ladyqr_lang ) ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="color-scheme" content="light">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo ladyqr_h( $title ); ?> – LadyQR</title>
	<link rel="icon" type="image/png" href="<?php echo ladyqr_asset( 'assets/icon-128.png', '../' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/app.css', '../' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/admin.css', '../' ); ?>">
</head>
<body class="admin admin-panel">

<main>
	<div class="admin-bar">
		<div class="admin-head">
			<img src="<?php echo ladyqr_asset( 'assets/icon-128.png', '../' ); ?>" alt="" width="36" height="36">
			<p class="admin-name">LadyQR</p>
		</div>
	</div>
	<div class="card">
		<div class="card-body">
			<h1><?php echo ladyqr_h( $title ); ?></h1>
			<?php $content(); ?>
		</div>
	</div>
</main>

</body>
</html>
	<?php
}

/**
 * Prints a small warning triangle.
 */
function ladyqr_install_icon() {
	echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';
}

/**
 * Prints the error under a field, if there is one.
 *
 * @param array  $errors The errors.
 * @param string $key    The field.
 */
function ladyqr_install_error( array $errors, $key ) {
	if ( isset( $errors[ $key ] ) ) {
		echo '<p class="field-error" id="' . ladyqr_h( $key ) . '-error">' . ladyqr_h( $errors[ $key ] ) . '</p>';
	}
}

/**
 * Returns the attributes that mark a field with an error.
 *
 * @param array  $errors The errors.
 * @param string $key    The field.
 */
function ladyqr_install_invalid( array $errors, $key ) {
	return isset( $errors[ $key ] ) ? ' aria-invalid="true" aria-describedby="' . ladyqr_h( $key ) . '-error"' : '';
}

/**
 * Prints the server checks.
 *
 * @param array $checks Each with a label, a status (ok, warn or fail) and a text.
 */
function ladyqr_install_checks( array $checks ) {
	?>
	<h2><?php esc_html_e( 'Server checks', 'ladyqr' ); ?></h2>
	<ul class="checks">
		<?php foreach ( $checks as $check ) : ?>
			<li class="check-<?php echo ladyqr_h( $check['status'] ); ?>">
				<span class="check-mark" aria-hidden="true"><?php echo 'ok' === $check['status'] ? '✓' : ( 'warn' === $check['status'] ? '!' : '✕' ); ?></span>
				<span>
					<strong><?php echo ladyqr_h( $check['label'] ); ?></strong>
					<span class="visually-hidden"><?php echo 'ok' === $check['status'] ? esc_html__( '(fine)', 'ladyqr' ) : ( 'warn' === $check['status'] ? esc_html__( '(warning)', 'ladyqr' ) : esc_html__( '(problem)', 'ladyqr' ) ); ?></span><br>
					<?php echo ladyqr_h( $check['text'] ); ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Prints the language of the site, preselected with the installer's.
 *
 * @param array  $languages The languages.
 * @param string $selected  The chosen one.
 */
function ladyqr_install_language_field( array $languages, $selected ) {
	?>
	<div class="field-row">
		<label class="label" for="language"><?php esc_html_e( 'Language of the site', 'ladyqr' ); ?></label>
		<select class="input" id="language" name="language" aria-describedby="language-help">
			<?php foreach ( $languages as $code => $name ) : ?>
				<option value="<?php echo ladyqr_h( $code ); ?>"<?php echo $code === $selected ? ' selected' : ''; ?>><?php echo ladyqr_h( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="hint" id="language-help"><?php esc_html_e( 'The language your visitors will see. It can differ from this installer\'s.', 'ladyqr' ); ?></p>
	</div>
	<?php
}

/**
 * Prints the files a person has to create, if any.
 *
 * @param array $manual File name => contents.
 */
function ladyqr_install_manual( array $manual ) {
	if ( array() === $manual ) {
		return;
	}
	?>
	<p><?php esc_html_e( 'LadyQR could not write these files. Create each one in the site\'s folder, next to index.php, with exactly this content:', 'ladyqr' ); ?></p>
	<?php foreach ( $manual as $name => $text ) : ?>
		<h3><?php echo ladyqr_h( $name ); ?></h3>
		<pre class="code"><code><?php echo ladyqr_h( $text ); ?></code></pre>
	<?php endforeach; ?>
	<?php
}

$ladyqr_languages = ladyqr_install_languages();
$ladyqr_lang      = isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) && isset( $ladyqr_languages[ $_GET['lang'] ] ) ? $_GET['lang'] : 'en_US';
ladyqr_load_textdomain( $ladyqr_lang );

// Installed already: say so and stop.
if ( null !== ladyqr_config_file() ) {
	ladyqr_install_page(
		__( 'LadyQR is installed', 'ladyqr' ),
		function () {
			?>
			<p><?php esc_html_e( 'LadyQR is already installed.', 'ladyqr' ); ?></p>
			<div class="form-actions">
				<a class="btn" href="../"><?php esc_html_e( 'Open the QR code generator', 'ladyqr' ); ?></a>
			</div>
			<?php
		}
	);
	exit;
}

$ladyqr_self     = strtok( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', '?' );
$ladyqr_origin   = ladyqr_request_origin();
$ladyqr_base     = ladyqr_base_path( 1 );
$ladyqr_writable = is_writable( $ladyqr_root );
$ladyqr_step     = isset( $_GET['step'] ) && in_array( $_GET['step'], array( 'welcome', 'generator', 'shortener' ), true ) ? $_GET['step'] : 'language';

/**
 * Returns the address of a screen of the installer, in the chosen language.
 *
 * @param string $step The screen; '' for the first one.
 */
function ladyqr_install_url( $step ) {
	global $ladyqr_self, $ladyqr_lang;
	return $ladyqr_self . '?lang=' . rawurlencode( $ladyqr_lang ) . ( '' === $step ? '' : '&step=' . $step );
}

// 1. The language of the installer.
if ( 'language' === $ladyqr_step ) {
	ladyqr_install_page(
		__( 'Choose a language', 'ladyqr' ),
		function () use ( $ladyqr_languages, $ladyqr_lang, $ladyqr_self ) {
			?>
			<form class="form" method="get" action="<?php echo ladyqr_h( $ladyqr_self ); ?>">
				<input type="hidden" name="step" value="welcome">
				<div>
					<label class="label" for="lang"><?php esc_html_e( 'Language of the installer', 'ladyqr' ); ?></label>
					<select class="input" id="lang" name="lang">
						<?php foreach ( $ladyqr_languages as $code => $name ) : ?>
							<option value="<?php echo ladyqr_h( $code ); ?>"<?php echo $code === $ladyqr_lang ? ' selected' : ''; ?>><?php echo ladyqr_h( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-actions">
					<button class="btn" type="submit"><?php esc_html_e( 'Continue', 'ladyqr' ); ?></button>
				</div>
			</form>
			<?php
		}
	);
	exit;
}

// 2. Welcome, and how LadyQR will be used.
if ( 'welcome' === $ladyqr_step ) {
	ladyqr_install_page(
		__( 'Welcome to LadyQR', 'ladyqr' ),
		function () {
			?>
			<p><?php esc_html_e( 'Hi! I\'m LadyQR. I make QR codes right in your browser, and what you type never leaves it. I can also keep short links on your own domain, so a QR code you print today can take people somewhere new tomorrow.', 'ladyqr' ); ?></p>
			<h2><?php esc_html_e( 'How will you use LadyQR?', 'ladyqr' ); ?></h2>
			<div class="start-choices">
				<a class="start-choice" href="<?php echo ladyqr_h( ladyqr_install_url( 'shortener' ) ); ?>"><?php esc_html_e( 'QR code generator + link shortener', 'ladyqr' ); ?></a>
				<a class="start-choice" href="<?php echo ladyqr_h( ladyqr_install_url( 'generator' ) ); ?>"><?php esc_html_e( 'QR code generator only', 'ladyqr' ); ?></a>
			</div>
			<p><a class="back" href="<?php echo ladyqr_h( ladyqr_install_url( '' ) ); ?>"><?php esc_html_e( 'Back', 'ladyqr' ); ?></a></p>
			<?php
		}
	);
	exit;
}

// 3. The checks and the settings for the choice made.
$ladyqr_shortener = 'shortener' === $ladyqr_step;
$ladyqr_session   = ladyqr_session_start();
$ladyqr_php_ok    = version_compare( PHP_VERSION, '7.4', '>=' );
$ladyqr_checks    = array(
	array(
		'label'  => 'PHP ' . PHP_VERSION,
		'status' => $ladyqr_php_ok ? 'ok' : 'fail',
		'text'   => $ladyqr_php_ok ? __( 'PHP 7.4 or later is needed.', 'ladyqr' ) : __( 'LadyQR needs PHP 7.4 or later. Ask your host to change the PHP version.', 'ladyqr' ),
	),
	array(
		'label'  => __( 'Writing in the site\'s folder', 'ladyqr' ),
		'status' => $ladyqr_writable ? 'ok' : 'warn',
		'text'   => $ladyqr_writable ? __( 'LadyQR can create its files.', 'ladyqr' ) : __( 'LadyQR cannot write in the site\'s folder. You can still go on: at the end it shows the files to create and upload yourself.', 'ladyqr' ),
	),
);
if ( $ladyqr_shortener ) {
	$ladyqr_crypto = function_exists( 'password_hash' ) && function_exists( 'random_bytes' );
	$ladyqr_route  = ladyqr_install_route_check( $ladyqr_origin, $ladyqr_base );
	if ( true === $ladyqr_route ) {
		$ladyqr_route_text = __( 'This server lets .htaccess send short links to LadyQR.', 'ladyqr' );
	} elseif ( false === $ladyqr_route ) {
		$ladyqr_route_text = __( 'This server does not let .htaccess send addresses to PHP, so short links will be off. The QR code generator works anyway.', 'ladyqr' );
	} elseif ( $ladyqr_writable ) {
		$ladyqr_route_text = __( 'It could not be tested now. It will be tested when you install.', 'ladyqr' );
	} else {
		$ladyqr_route_text = __( 'It cannot be tested, because LadyQR cannot write in the site\'s folder.', 'ladyqr' );
	}
	$ladyqr_checks = array_merge(
		$ladyqr_checks,
		array(
			array(
				'label'  => __( 'Password hashing and random numbers', 'ladyqr' ),
				'status' => $ladyqr_crypto ? 'ok' : 'fail',
				'text'   => $ladyqr_crypto ? __( 'Available.', 'ladyqr' ) : __( 'This PHP lacks password_hash() or random_bytes().', 'ladyqr' ),
			),
			array(
				'label'  => __( 'Sessions', 'ladyqr' ),
				'status' => $ladyqr_session ? 'ok' : 'fail',
				'text'   => $ladyqr_session ? __( 'They work, so the admin can keep you logged in.', 'ladyqr' ) : __( 'Sessions do not work on this server, so the admin could not keep you logged in.', 'ladyqr' ),
			),
			array(
				'label'  => __( 'Short links (.htaccess)', 'ladyqr' ),
				'status' => true === $ladyqr_route ? 'ok' : 'warn',
				'text'   => $ladyqr_route_text,
			),
			array(
				'label'  => 'HTTPS',
				'status' => ladyqr_is_https() ? 'ok' : 'warn',
				'text'   => ladyqr_is_https() ? __( 'This connection is secure.', 'ladyqr' ) : __( 'This connection is not secure: the admin\'s password would travel unencrypted. Use https if your host offers it.', 'ladyqr' ),
			),
		)
	);
}
$ladyqr_blocked = false;
foreach ( $ladyqr_checks as $ladyqr_check ) {
	$ladyqr_blocked = $ladyqr_blocked || 'fail' === $ladyqr_check['status'];
}

$ladyqr_form   = array(
	'site'     => $ladyqr_origin,
	'language' => $ladyqr_lang,
	'user'     => '',
	'password' => $ladyqr_shortener && $ladyqr_session && function_exists( 'random_int' ) ? ladyqr_suggest_password() : '',
);
$ladyqr_errors = array();
$ladyqr_done   = null;

if ( ! $ladyqr_blocked && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	foreach ( array_keys( $ladyqr_form ) as $ladyqr_key ) {
		if ( isset( $_POST[ $ladyqr_key ] ) && is_string( $_POST[ $ladyqr_key ] ) ) {
			$ladyqr_form[ $ladyqr_key ] = trim( $_POST[ $ladyqr_key ] );
		}
	}

	if ( $ladyqr_session && ! ladyqr_csrf_valid( isset( $_POST['csrf'] ) ? $_POST['csrf'] : '' ) ) {
		$ladyqr_errors['form'] = __( 'The form expired. Check the values and send it again.', 'ladyqr' );
	}
	if ( ! isset( $ladyqr_languages[ $ladyqr_form['language'] ] ) ) {
		$ladyqr_errors['language'] = __( 'Choose a language from the list.', 'ladyqr' );
	}
	if ( $ladyqr_shortener ) {
		$ladyqr_site = ladyqr_clean_url( $ladyqr_form['site'] );
		if ( null === $ladyqr_site || '' !== (string) parse_url( $ladyqr_site, PHP_URL_PATH ) ) {
			$ladyqr_errors['site'] = __( 'Write the address as https://example.com or http://example.com, with no folder after it.', 'ladyqr' );
		}
		if ( '' === $ladyqr_form['user'] || strlen( $ladyqr_form['user'] ) > 60 || preg_match( '/[\x00-\x20\x7f]/', $ladyqr_form['user'] ) ) {
			$ladyqr_errors['user'] = __( 'Write a user of up to 60 characters, with no spaces.', 'ladyqr' );
		}
		if ( preg_match_all( '/./us', $ladyqr_form['password'] ) < 12 ) {
			$ladyqr_errors['password'] = __( 'The password needs at least 12 characters.', 'ladyqr' );
		} elseif ( strlen( $ladyqr_form['password'] ) > 72 ) {
			$ladyqr_errors['password'] = __( 'The password is too long: it can have up to 72 bytes (72 plain letters and digits, fewer with accents or symbols).', 'ladyqr' );
		}
	}

	if ( array() === $ladyqr_errors ) {
		$ladyqr_config_file = $ladyqr_root . '/ladyqr-config.php';
		$ladyqr_done        = array(
			'shortener' => $ladyqr_shortener,
			'language'  => $ladyqr_languages[ $ladyqr_form['language'] ],
			'url'       => $ladyqr_base,
			'manual'    => array(),
		);

		if ( ! $ladyqr_shortener ) {
			// The QR code generator alone: only the configuration file.
			$ladyqr_config_text = ladyqr_install_config_text(
				array(
					'language'  => $ladyqr_form['language'],
					'shortener' => false,
				)
			);
			if ( ! $ladyqr_writable || ! ladyqr_file_replace( $ladyqr_config_file, $ladyqr_config_text ) || file_get_contents( $ladyqr_config_file ) !== $ladyqr_config_text ) {
				@unlink( $ladyqr_config_file );
				$ladyqr_done['manual']['ladyqr-config.php'] = $ladyqr_config_text;
			}
		} else {
			$ladyqr_url  = $ladyqr_site . $ladyqr_base;
			$ladyqr_file = $ladyqr_root . '/.htaccess';
			$ladyqr_old  = is_file( $ladyqr_file ) ? file_get_contents( $ladyqr_file ) : null;
			if ( false === $ladyqr_old ) {
				$ladyqr_old = null;
			}
			// A new .htaccess gets the address rules on top. An existing one only gets LadyQR's block.
			$ladyqr_htaccess = ladyqr_htaccess_text( $ladyqr_old, $ladyqr_url, true );

			$ladyqr_done   = array_merge(
				$ladyqr_done,
				array(
					'url'      => $ladyqr_url,
					'user'     => $ladyqr_form['user'],
					'password' => $ladyqr_form['password'],
					'route'    => null,
					'htaccess' => '',
				)
			);
			$ladyqr_values = array(
				'language'  => $ladyqr_form['language'],
				'url'       => $ladyqr_url,
				'shortener' => true,
				'user'      => $ladyqr_form['user'],
				'password'  => password_hash( $ladyqr_form['password'], PASSWORD_DEFAULT ),
			);
			$ladyqr_data_exists = is_file( ladyqr_data_file() );

			if ( $ladyqr_writable ) {
				// 1. The .htaccess, checked and rolled back if the site stops answering.
				if ( null === $ladyqr_htaccess ) {
					$ladyqr_done['htaccess'] = 'damaged';
				} elseif ( $ladyqr_htaccess !== $ladyqr_old ) {
					$ladyqr_done['htaccess'] = ladyqr_htaccess_write( $ladyqr_htaccess, $ladyqr_url );
					if ( 'not_writable' === $ladyqr_done['htaccess'] || 'failed' === $ladyqr_done['htaccess'] ) {
						$ladyqr_done['manual']['.htaccess'] = $ladyqr_htaccess;
					}
				}

				// 2. Whether short links really reach PHP. Only a clear no turns them off.
				// When the .htaccess is left for a person to paste, there is nothing to test yet.
				if ( ! isset( $ladyqr_done['manual']['.htaccess'] ) ) {
					$ladyqr_done['route']       = ladyqr_route_works( $ladyqr_url );
					$ladyqr_values['shortener'] = false !== $ladyqr_done['route'] && 'rolled_back' !== $ladyqr_done['htaccess'] && 'damaged' !== $ladyqr_done['htaccess'];
				}

				// 3. The links, kept if there are some from an earlier install.
				if ( ! $ladyqr_data_exists && ! ladyqr_file_replace( ladyqr_data_file(), ladyqr_data_text( array() ) ) ) {
					$ladyqr_done['manual']['ladyqr-data.php'] = ladyqr_data_text( array() );
				}

				// 4. The configuration, last: once it exists, LadyQR is installed.
				$ladyqr_config_text = ladyqr_install_config_text( $ladyqr_values );
				if ( ! ladyqr_file_replace( $ladyqr_config_file, $ladyqr_config_text ) || file_get_contents( $ladyqr_config_file ) !== $ladyqr_config_text ) {
					@unlink( $ladyqr_config_file );
					$ladyqr_done['manual']['ladyqr-config.php'] = $ladyqr_config_text;
				}
			} else {
				$ladyqr_done['manual']['ladyqr-config.php'] = ladyqr_install_config_text( $ladyqr_values );
				if ( ! $ladyqr_data_exists ) {
					$ladyqr_done['manual']['ladyqr-data.php'] = ladyqr_data_text( array() );
				}
				if ( null !== $ladyqr_htaccess && $ladyqr_htaccess !== $ladyqr_old ) {
					$ladyqr_done['manual']['.htaccess'] = $ladyqr_htaccess;
				}
			}
			$ladyqr_done['shortener_on'] = $ladyqr_values['shortener'];
		}
	}
}

// 4. The result.
if ( null !== $ladyqr_done ) {
	ladyqr_install_page(
		array() === $ladyqr_done['manual'] ? __( 'LadyQR is installed', 'ladyqr' ) : __( 'Almost there', 'ladyqr' ),
		function () use ( $ladyqr_done ) {
			$d = $ladyqr_done;
			ladyqr_install_manual( $d['manual'] );
			if ( ! $d['shortener'] ) {
				?>
				<p><?php esc_html_e( 'LadyQR will only make QR codes.', 'ladyqr' ); ?></p>
				<p><?php esc_html_e( 'If you change your mind, edit the ladyqr-config.php file, or delete it to use this installer again.', 'ladyqr' ); ?></p>
				<div class="form-actions">
					<a class="btn" href="<?php echo ladyqr_h( $d['url'] . '/' ); ?>"><?php esc_html_e( 'Open the QR code generator', 'ladyqr' ); ?></a>
				</div>
				<?php
				return;
			}
			?>
			<table class="summary">
				<tr><th scope="row"><?php esc_html_e( 'Address', 'ladyqr' ); ?></th><td><?php echo ladyqr_h( $d['url'] ); ?>/</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Language of the site', 'ladyqr' ); ?></th><td><?php echo ladyqr_h( $d['language'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Short links', 'ladyqr' ); ?></th><td>
					<?php
					if ( $d['shortener_on'] ) {
						echo true === $d['route'] ? esc_html__( 'On, tested.', 'ladyqr' ) : esc_html__( 'On, but they could not be tested. If they do not work, check that your host allows .htaccess files.', 'ladyqr' );
					} elseif ( 'rolled_back' === $d['htaccess'] ) {
						esc_html_e( 'Off. The .htaccess rules made the site stop answering, so they were undone.', 'ladyqr' );
					} elseif ( 'damaged' === $d['htaccess'] ) {
						esc_html_e( 'Off. The existing .htaccess has a damaged LadyQR block (a "# BEGIN LadyQR" or "# END LadyQR" line is missing or repeated).', 'ladyqr' );
					} else {
						esc_html_e( 'Off. This server does not let .htaccess send addresses to PHP.', 'ladyqr' );
					}
					?>
				</td></tr>
			</table>
			<?php if ( 'unverified' === $d['htaccess'] ) : ?>
				<p class="notice"><?php ladyqr_install_icon(); ?><span><?php esc_html_e( 'The .htaccess file was written, but LadyQR could not check that the site still answers. Open your site to make sure it works.', 'ladyqr' ); ?></span></p>
			<?php endif; ?>
			<div class="credentials">
				<p><strong><?php esc_html_e( 'Save your user and password now.', 'ladyqr' ); ?></strong> <?php esc_html_e( 'The password is stored encrypted and will not be shown again.', 'ladyqr' ); ?></p>
				<table class="summary">
					<tr><th scope="row"><?php esc_html_e( 'User', 'ladyqr' ); ?></th><td><code><?php echo ladyqr_h( $d['user'] ); ?></code></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Password', 'ladyqr' ); ?></th><td><code><?php echo ladyqr_h( $d['password'] ); ?></code></td></tr>
				</table>
			</div>
			<div class="form-actions">
				<a class="btn" href="<?php echo ladyqr_h( $d['url'] . '/' ); ?>"><?php esc_html_e( 'Open the QR code generator', 'ladyqr' ); ?></a>
				<a class="btn secondary" href="<?php echo ladyqr_h( $d['url'] . '/admin/' ); ?>"><?php esc_html_e( 'Go to the link shortener', 'ladyqr' ); ?></a>
			</div>
			<?php
		}
	);
	exit;
}

$ladyqr_csrf = $ladyqr_session ? ladyqr_csrf_token() : '';

ladyqr_install_page(
	$ladyqr_shortener ? __( 'QR code generator + link shortener', 'ladyqr' ) : __( 'QR code generator only', 'ladyqr' ),
	function () use ( $ladyqr_shortener, $ladyqr_checks, $ladyqr_blocked, $ladyqr_form, $ladyqr_errors, $ladyqr_languages, $ladyqr_base, $ladyqr_csrf ) {
		ladyqr_install_checks( $ladyqr_checks );
		if ( $ladyqr_blocked ) {
			?>
			<p class="notice error" role="alert"><?php ladyqr_install_icon(); ?><span><?php esc_html_e( 'LadyQR cannot be installed on this server until the problems above are solved.', 'ladyqr' ); ?></span></p>
			<p><a class="back" href="<?php echo ladyqr_h( ladyqr_install_url( 'welcome' ) ); ?>"><?php esc_html_e( 'Back', 'ladyqr' ); ?></a></p>
			<?php
			return;
		}
		?>
		<h2><?php esc_html_e( 'Settings', 'ladyqr' ); ?></h2>
		<?php if ( isset( $ladyqr_errors['form'] ) ) : ?>
			<p class="notice error" role="alert"><?php ladyqr_install_icon(); ?><span><?php echo ladyqr_h( $ladyqr_errors['form'] ); ?></span></p>
		<?php endif; ?>
		<form class="form side-labels" method="post" action="<?php echo ladyqr_h( ladyqr_install_url( $ladyqr_shortener ? 'shortener' : 'generator' ) ); ?>">
			<input type="hidden" name="csrf" value="<?php echo ladyqr_h( $ladyqr_csrf ); ?>">

			<?php ladyqr_install_language_field( $ladyqr_languages, $ladyqr_form['language'] ); ?>

			<?php if ( $ladyqr_shortener ) : ?>
				<div class="field-row">
					<label class="label" for="site"><?php esc_html_e( 'Site address', 'ladyqr' ); ?></label>
					<div class="input-group">
						<input class="input" id="site" name="site" type="text" inputmode="url" value="<?php echo ladyqr_h( $ladyqr_form['site'] ); ?>" autocapitalize="off" autocomplete="off" spellcheck="false"<?php echo ladyqr_install_invalid( $ladyqr_errors, 'site' ); ?>>
						<?php if ( '' !== $ladyqr_base ) : ?>
							<span class="addon addon-after"><?php echo ladyqr_h( $ladyqr_base ); ?>/</span>
						<?php endif; ?>
					</div>
					<?php ladyqr_install_error( $ladyqr_errors, 'site' ); ?>
					<p class="hint"><?php esc_html_e( 'Where your visitors will find LadyQR. Fix it if it is wrong: with or without www, http or https. Once installed, every visit is sent to this address.', 'ladyqr' ); ?></p>
				</div>

				<div class="field-row">
					<label class="label" for="user"><?php esc_html_e( 'User', 'ladyqr' ); ?></label>
					<input class="input" id="user" name="user" type="text" value="<?php echo ladyqr_h( $ladyqr_form['user'] ); ?>" autocomplete="username" autocapitalize="off" spellcheck="false" maxlength="60"<?php echo ladyqr_install_invalid( $ladyqr_errors, 'user' ); ?>>
					<?php ladyqr_install_error( $ladyqr_errors, 'user' ); ?>
				</div>

				<div class="field-row">
					<label class="label" for="password"><?php esc_html_e( 'Password', 'ladyqr' ); ?></label>
					<input class="input mono" id="password" name="password" type="text" value="<?php echo ladyqr_h( $ladyqr_form['password'] ); ?>" autocomplete="new-password" autocapitalize="off" spellcheck="false"<?php echo ladyqr_install_invalid( $ladyqr_errors, 'password' ); ?>>
					<?php ladyqr_install_error( $ladyqr_errors, 'password' ); ?>
					<p class="hint"><?php esc_html_e( 'A strong password is suggested. You can change it: at least 12 characters. Copy it now; it is shown once more after installing, and then never again.', 'ladyqr' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="form-actions">
				<button class="btn" type="submit"><?php esc_html_e( 'Install LadyQR', 'ladyqr' ); ?></button>
				<a class="btn secondary" href="<?php echo ladyqr_h( ladyqr_install_url( 'welcome' ) ); ?>"><?php esc_html_e( 'Back', 'ladyqr' ); ?></a>
			</div>
		</form>
		<?php
	}
);

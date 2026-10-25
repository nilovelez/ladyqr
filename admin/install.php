<?php
/**
 * LadyQR installer.
 *
 * The admin sends here when there is no configuration file. It checks the
 * server, asks for the few settings LadyQR needs, and writes ladyqr-config.php,
 * ladyqr-data.php and .htaccess. If PHP cannot write in the site's folder, it
 * shows those files for a person to create. Once a configuration file exists it
 * does nothing. It is always in English.
 */

define( 'LADYQR', true );
$ladyqr_root = dirname( __DIR__ );
require $ladyqr_root . '/includes/functions.php';
require $ladyqr_root . '/includes/config.php';
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
 * Returns the languages there are translations for, as locale => name.
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
 * Returns the text of the configuration file.
 *
 * @param array $values language, url, shortener, and user and password (the hash) when there is an admin.
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
	$lines[] = '// The address of the site, without a final slash.';
	$lines[] = "define( 'LADYQR_URL', " . var_export( $values['url'], true ) . ' );';
	$lines[] = '';
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
	?>
<!doctype html>
<html lang="en">
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
	<div class="card">
		<div class="card-body">
			<div class="admin-head">
				<img src="<?php echo ladyqr_asset( 'assets/icon-128.png', '../' ); ?>" alt="" width="44" height="44">
				<h1><?php echo ladyqr_h( $title ); ?></h1>
			</div>
			<?php $content(); ?>
		</div>
	</div>
</main>

</body>
</html>
	<?php
}

// Installed already: say so and stop.
if ( null !== ladyqr_config_file() ) {
	ladyqr_install_page(
		'LadyQR is installed',
		function () {
			?>
			<p>LadyQR is already installed.</p>
			<a class="btn" href="./">Go to the admin</a>
			<?php
		}
	);
	exit;
}

$ladyqr_self      = strtok( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', '?' );
$ladyqr_origin    = ladyqr_request_origin();
$ladyqr_base      = ladyqr_base_path( 1 );
$ladyqr_languages = ladyqr_install_languages();
$ladyqr_writable  = is_writable( $ladyqr_root );

// The server checks. A failed critical one stops the installer.
$ladyqr_session = ladyqr_session_start();
$ladyqr_route   = ladyqr_install_route_check( $ladyqr_origin, $ladyqr_base );
$ladyqr_checks  = array(
	array(
		'label'  => 'PHP ' . PHP_VERSION,
		'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'ok' : 'fail',
		'text'   => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'PHP 7.4 or later is needed.' : 'LadyQR needs PHP 7.4 or later. Ask your host to change the PHP version.',
	),
	array(
		'label'  => 'Password hashing and random numbers',
		'status' => function_exists( 'password_hash' ) && function_exists( 'random_bytes' ) && function_exists( 'json_encode' ) ? 'ok' : 'fail',
		'text'   => function_exists( 'password_hash' ) && function_exists( 'random_bytes' ) && function_exists( 'json_encode' ) ? 'Available.' : 'This PHP lacks password_hash(), random_bytes() or json_encode().',
	),
	array(
		'label'  => 'Sessions',
		'status' => $ladyqr_session ? 'ok' : 'fail',
		'text'   => $ladyqr_session ? 'They work, so the admin can keep you logged in.' : 'Sessions do not work on this server, so the admin could not keep you logged in.',
	),
	array(
		'label'  => 'Writing in the site\'s folder',
		'status' => $ladyqr_writable ? 'ok' : 'warn',
		'text'   => $ladyqr_writable ? 'LadyQR can create its files.' : 'LadyQR cannot write in the site\'s folder. You can still fill in the form: at the end it shows the files to create and upload yourself.',
	),
	array(
		'label'  => 'Short links (.htaccess)',
		'status' => true === $ladyqr_route ? 'ok' : 'warn',
		'text'   => true === $ladyqr_route ? 'This server lets .htaccess send short links to LadyQR.'
			: ( false === $ladyqr_route ? 'This server does not let .htaccess send addresses to PHP, so short links will be off. The QR code editor works anyway.'
			: ( $ladyqr_writable ? 'It could not be tested now. It will be tested when you install.' : 'It cannot be tested, because LadyQR cannot write in the site\'s folder.' ) ),
	),
	array(
		'label'  => 'HTTPS',
		'status' => ladyqr_is_https() ? 'ok' : 'warn',
		'text'   => ladyqr_is_https() ? 'This connection is secure.' : 'This connection is not secure: the admin\'s password would travel unencrypted. Use https if your host offers it.',
	),
);
$ladyqr_blocked = false;
foreach ( $ladyqr_checks as $ladyqr_check ) {
	$ladyqr_blocked = $ladyqr_blocked || 'fail' === $ladyqr_check['status'];
}

// The form, with the recommended options.
$ladyqr_form   = array(
	'site'     => $ladyqr_origin,
	'language' => 'en_US',
	'mode'     => 'full',
	'user'     => '',
	'password' => $ladyqr_session ? ladyqr_suggest_password() : '',
);
$ladyqr_errors = array();
$ladyqr_done   = null;

if ( ! $ladyqr_blocked && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	foreach ( array_keys( $ladyqr_form ) as $ladyqr_key ) {
		if ( isset( $_POST[ $ladyqr_key ] ) && is_string( $_POST[ $ladyqr_key ] ) ) {
			$ladyqr_form[ $ladyqr_key ] = trim( $_POST[ $ladyqr_key ] );
		}
	}
	$ladyqr_full = 'editor' !== $ladyqr_form['mode'];

	if ( ! ladyqr_csrf_valid( isset( $_POST['csrf'] ) ? $_POST['csrf'] : '' ) ) {
		$ladyqr_errors['form'] = 'The form expired. Check the values and send it again.';
	}
	$ladyqr_site = ladyqr_clean_url( $ladyqr_form['site'] );
	if ( null === $ladyqr_site || '' !== (string) parse_url( $ladyqr_site, PHP_URL_PATH ) ) {
		$ladyqr_errors['site'] = 'Write the address as https://example.com or http://example.com, with no folder after it.';
	}
	if ( ! isset( $ladyqr_languages[ $ladyqr_form['language'] ] ) ) {
		$ladyqr_errors['language'] = 'Choose a language from the list.';
	}
	if ( $ladyqr_full ) {
		if ( '' === $ladyqr_form['user'] || strlen( $ladyqr_form['user'] ) > 60 || preg_match( '/[\x00-\x20\x7f]/', $ladyqr_form['user'] ) ) {
			$ladyqr_errors['user'] = 'Write a user of up to 60 characters, with no spaces.';
		}
		if ( preg_match_all( '/./us', $ladyqr_form['password'] ) < 12 ) {
			$ladyqr_errors['password'] = 'The password needs at least 12 characters.';
		} elseif ( strlen( $ladyqr_form['password'] ) > 72 ) {
			$ladyqr_errors['password'] = 'The password is too long: it can have up to 72 bytes (72 plain letters and digits, fewer with accents or symbols).';
		}
	}

	if ( array() === $ladyqr_errors ) {
		$ladyqr_url  = $ladyqr_site . $ladyqr_base;
		$ladyqr_file = $ladyqr_root . '/.htaccess';
		$ladyqr_old  = is_file( $ladyqr_file ) ? file_get_contents( $ladyqr_file ) : null;
		if ( false === $ladyqr_old ) {
			$ladyqr_old = null;
		}

		// The .htaccess: a new one gets the address rules on top. An existing one
		// only gets LadyQR's block, and only when there are short links.
		if ( $ladyqr_full ) {
			$ladyqr_htaccess = ladyqr_htaccess_text( $ladyqr_old, $ladyqr_url, true );
		} else {
			$ladyqr_htaccess = null === $ladyqr_old ? ladyqr_htaccess_generic( $ladyqr_url ) . "\n" : null;
		}

		$ladyqr_done = array(
			'url'       => $ladyqr_url,
			'language'  => $ladyqr_languages[ $ladyqr_form['language'] ],
			'full'      => $ladyqr_full,
			'user'      => $ladyqr_form['user'],
			'password'  => $ladyqr_form['password'],
			'route'     => null,
			'htaccess'  => '',
			'manual'    => array(),
			'error'     => '',
		);
		$ladyqr_values = array(
			'language'  => $ladyqr_form['language'],
			'url'       => $ladyqr_url,
			'shortener' => $ladyqr_full,
		);
		if ( $ladyqr_full ) {
			$ladyqr_values['user']     = $ladyqr_form['user'];
			$ladyqr_values['password'] = password_hash( $ladyqr_form['password'], PASSWORD_DEFAULT );
		}
		$ladyqr_data_exists = is_file( ladyqr_data_file() );

		if ( $ladyqr_writable ) {
			// 1. The .htaccess, checked and rolled back if the site stops answering.
			if ( null === $ladyqr_htaccess && $ladyqr_full ) {
				$ladyqr_done['htaccess'] = 'damaged';
			} elseif ( null !== $ladyqr_htaccess && $ladyqr_htaccess !== $ladyqr_old ) {
				$ladyqr_done['htaccess'] = ladyqr_htaccess_write( $ladyqr_htaccess, $ladyqr_url );
				if ( 'not_writable' === $ladyqr_done['htaccess'] || 'failed' === $ladyqr_done['htaccess'] ) {
					$ladyqr_done['manual']['.htaccess'] = $ladyqr_htaccess;
				}
			}

			// 2. Whether short links really reach PHP. Only a clear no turns them off.
			// When the .htaccess is left for a person to paste, there is nothing to test yet.
			if ( $ladyqr_full && ! isset( $ladyqr_done['manual']['.htaccess'] ) ) {
				$ladyqr_done['route']       = ladyqr_route_works( $ladyqr_url );
				$ladyqr_values['shortener'] = false !== $ladyqr_done['route'] && 'rolled_back' !== $ladyqr_done['htaccess'] && 'damaged' !== $ladyqr_done['htaccess'];
			}

			// 3. The links, kept if there are some from an earlier install.
			if ( $ladyqr_full && ! $ladyqr_data_exists && ! ladyqr_file_replace( ladyqr_data_file(), ladyqr_data_text( array() ) ) ) {
				$ladyqr_done['manual']['ladyqr-data.php'] = ladyqr_data_text( array() );
			}

			// 4. The configuration, last: once it exists, LadyQR is installed.
			$ladyqr_config_text = ladyqr_install_config_text( $ladyqr_values );
			$ladyqr_config_file = $ladyqr_root . '/ladyqr-config.php';
			if ( ! ladyqr_file_replace( $ladyqr_config_file, $ladyqr_config_text ) || file_get_contents( $ladyqr_config_file ) !== $ladyqr_config_text ) {
				@unlink( $ladyqr_config_file );
				$ladyqr_done['manual']['ladyqr-config.php'] = $ladyqr_config_text;
			}
		} else {
			$ladyqr_done['manual']['ladyqr-config.php'] = ladyqr_install_config_text( $ladyqr_values );
			if ( $ladyqr_full && ! $ladyqr_data_exists ) {
				$ladyqr_done['manual']['ladyqr-data.php'] = ladyqr_data_text( array() );
			}
			if ( null !== $ladyqr_htaccess && $ladyqr_htaccess !== $ladyqr_old ) {
				$ladyqr_done['manual']['.htaccess'] = $ladyqr_htaccess;
			}
		}
		$ladyqr_done['shortener'] = $ladyqr_values['shortener'];
	}
}

$ladyqr_csrf = $ladyqr_session ? ladyqr_csrf_token() : '';

if ( null !== $ladyqr_done ) {
	ladyqr_install_page(
		array() === $ladyqr_done['manual'] ? 'LadyQR is installed' : 'Almost there',
		function () use ( $ladyqr_done ) {
			$d = $ladyqr_done;
			if ( array() !== $d['manual'] ) {
				?>
				<p>LadyQR could not write these files. Create each one in the site's folder, next to index.php, with exactly this content, and then open the admin.</p>
				<?php foreach ( $d['manual'] as $name => $text ) : ?>
					<h3><?php echo ladyqr_h( $name ); ?></h3>
					<pre class="code"><code><?php echo ladyqr_h( $text ); ?></code></pre>
				<?php endforeach; ?>
				<?php
			}
			?>
			<table class="summary">
				<tr><th scope="row">Address</th><td><?php echo ladyqr_h( $d['url'] ); ?>/</td></tr>
				<tr><th scope="row">Language</th><td><?php echo ladyqr_h( $d['language'] ); ?></td></tr>
				<tr><th scope="row">Mode</th><td><?php echo $d['full'] ? 'QR code editor and short links' : 'QR code editor only'; ?></td></tr>
				<?php if ( $d['full'] ) : ?>
					<tr><th scope="row">Short links</th><td>
						<?php
						if ( $d['shortener'] ) {
							echo true === $d['route'] ? 'On, tested.' : 'On, but they could not be tested. If they do not work, check that your host allows .htaccess files.';
						} elseif ( 'rolled_back' === $d['htaccess'] ) {
							echo 'Off. The .htaccess rules made the site stop answering, so they were undone.';
						} elseif ( 'damaged' === $d['htaccess'] ) {
							echo 'Off. The existing .htaccess has a damaged LadyQR block (a "# BEGIN LadyQR" or "# END LadyQR" line is missing or repeated).';
						} else {
							echo 'Off. This server does not let .htaccess send addresses to PHP.';
						}
						?>
					</td></tr>
				<?php endif; ?>
			</table>
			<?php if ( 'unverified' === $d['htaccess'] ) : ?>
				<p class="notice"><?php ladyqr_install_icon(); ?><span>The .htaccess file was written, but LadyQR could not check that the site still answers. Open your site to make sure it works.</span></p>
			<?php endif; ?>
			<?php if ( $d['full'] ) : ?>
				<div class="credentials">
					<p><strong>Save your user and password now.</strong> The password is stored encrypted and will not be shown again.</p>
					<table class="summary">
						<tr><th scope="row">User</th><td><code><?php echo ladyqr_h( $d['user'] ); ?></code></td></tr>
						<tr><th scope="row">Password</th><td><code><?php echo ladyqr_h( $d['password'] ); ?></code></td></tr>
					</table>
				</div>
			<?php endif; ?>
			<div class="form-actions">
				<a class="btn" href="<?php echo ladyqr_h( $d['url'] . '/' ); ?>">Open the QR code editor</a>
				<?php if ( $d['full'] ) : ?>
					<a class="btn secondary" href="<?php echo ladyqr_h( $d['url'] . '/admin/' ); ?>">Go to the admin</a>
				<?php endif; ?>
			</div>
			<?php
		}
	);
	exit;
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

ladyqr_install_page(
	'Install LadyQR',
	function () use ( $ladyqr_checks, $ladyqr_blocked, $ladyqr_form, $ladyqr_errors, $ladyqr_languages, $ladyqr_base, $ladyqr_self, $ladyqr_csrf ) {
		?>
		<h2>Server checks</h2>
		<ul class="checks">
			<?php foreach ( $ladyqr_checks as $check ) : ?>
				<li class="check-<?php echo ladyqr_h( $check['status'] ); ?>">
					<span class="check-mark" aria-hidden="true"><?php echo 'ok' === $check['status'] ? '✓' : ( 'warn' === $check['status'] ? '!' : '✕' ); ?></span>
					<span><strong><?php echo ladyqr_h( $check['label'] ); ?></strong> <span class="visually-hidden"><?php echo 'ok' === $check['status'] ? '(fine)' : ( 'warn' === $check['status'] ? '(warning)' : '(problem)' ); ?></span><br><?php echo ladyqr_h( $check['text'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $ladyqr_blocked ) : ?>
			<p class="notice error" role="alert"><?php ladyqr_install_icon(); ?><span>LadyQR cannot be installed on this server until the problems above are solved.</span></p>
			<?php
			return;
		endif;
		?>

		<h2>Settings</h2>
		<?php if ( isset( $ladyqr_errors['form'] ) ) : ?>
			<p class="notice error" role="alert"><?php ladyqr_install_icon(); ?><span><?php echo ladyqr_h( $ladyqr_errors['form'] ); ?></span></p>
		<?php endif; ?>
		<form class="form side-labels" method="post" action="<?php echo ladyqr_h( $ladyqr_self ); ?>">
			<input type="hidden" name="csrf" value="<?php echo ladyqr_h( $ladyqr_csrf ); ?>">

			<div class="field-row">
				<label class="label" for="site">Site address</label>
				<div class="input-group">
					<input class="input" id="site" name="site" type="text" inputmode="url" value="<?php echo ladyqr_h( $ladyqr_form['site'] ); ?>" autocapitalize="off" autocomplete="off" spellcheck="false"<?php echo ladyqr_install_invalid( $ladyqr_errors, 'site' ); ?>>
					<?php if ( '' !== $ladyqr_base ) : ?>
						<span class="addon addon-after"><?php echo ladyqr_h( $ladyqr_base ); ?>/</span>
					<?php endif; ?>
				</div>
				<?php ladyqr_install_error( $ladyqr_errors, 'site' ); ?>
				<p class="hint">Where your visitors will find LadyQR. Fix it if it is wrong: with or without www, http or https. Once installed, every visit is sent to this address.</p>
			</div>

			<div class="field-row">
				<label class="label" for="language">Language</label>
				<select class="input" id="language" name="language"<?php echo ladyqr_install_invalid( $ladyqr_errors, 'language' ); ?>>
					<?php foreach ( $ladyqr_languages as $code => $name ) : ?>
						<option value="<?php echo ladyqr_h( $code ); ?>"<?php echo $code === $ladyqr_form['language'] ? ' selected' : ''; ?>><?php echo ladyqr_h( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php ladyqr_install_error( $ladyqr_errors, 'language' ); ?>
			</div>

			<div class="field-row">
				<span class="label" id="mode-label">Mode</span>
				<div class="choices" role="radiogroup" aria-labelledby="mode-label">
					<label class="choice"><input type="radio" id="mode-full" name="mode" value="full"<?php echo 'editor' !== $ladyqr_form['mode'] ? ' checked' : ''; ?>> <span><strong>QR code editor and short links</strong><br>A private admin to manage your short links, with a user and a password.</span></label>
					<label class="choice"><input type="radio" id="mode-editor" name="mode" value="editor"<?php echo 'editor' === $ladyqr_form['mode'] ? ' checked' : ''; ?>> <span><strong>QR code editor only</strong><br>No admin, no user and no password.</span></label>
				</div>
			</div>

			<div class="field-row full-only">
				<label class="label" for="user">User</label>
				<input class="input" id="user" name="user" type="text" value="<?php echo ladyqr_h( $ladyqr_form['user'] ); ?>" autocomplete="username" autocapitalize="off" spellcheck="false" maxlength="60"<?php echo ladyqr_install_invalid( $ladyqr_errors, 'user' ); ?>>
				<?php ladyqr_install_error( $ladyqr_errors, 'user' ); ?>
			</div>

			<div class="field-row full-only">
				<label class="label" for="password">Password</label>
				<input class="input mono" id="password" name="password" type="text" value="<?php echo ladyqr_h( $ladyqr_form['password'] ); ?>" autocomplete="new-password" autocapitalize="off" spellcheck="false"<?php echo ladyqr_install_invalid( $ladyqr_errors, 'password' ); ?>>
				<?php ladyqr_install_error( $ladyqr_errors, 'password' ); ?>
				<p class="hint">A strong password is suggested. You can change it: at least 12 characters. Copy it now; it is shown once more after installing, and then never again.</p>
			</div>

			<div class="form-actions">
				<button class="btn" type="submit">Install LadyQR</button>
			</div>
		</form>
		<?php
	}
);

<?php
/**
 * LadyQR: the single entry point.
 *
 * The root of the site is the QR code editor. When `.htaccess` sends here an
 * address that is not a file, it is a short link or the not-found page.
 *
 * PHP only generates the editor page, with the interface in the configured
 * language. The QR code itself is made in the visitor's browser, and a strict
 * Content-Security-Policy stops the page from talking to any server.
 */

define( 'LADYQR', true );
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/i18n.php';

// The installer asks for this address to learn whether .htaccess reaches PHP,
// so it is answered before anything else, installed or not.
if ( 'ladyqr-route-test' === ladyqr_request_path() ) {
	header( 'X-LadyQR-Route: ok' );
	http_response_code( 204 );
	exit;
}

// Not installed yet: while the installer is there, every visit goes to it, so
// the owner, who opens the site right after uploading it, installs it before
// anyone else can. Deleting the admin folder leaves only the QR code creator,
// with nothing to install and nothing to attack.
if ( null === ladyqr_config_file() && is_file( __DIR__ . '/admin/install.php' ) ) {
	header( 'Location: ' . ladyqr_base_path() . '/admin/install.php', true, 302 );
	exit;
}

$ladyqr_config = ladyqr_config();
ladyqr_load_textdomain( $ladyqr_config['language'] );

$ladyqr_path = ladyqr_request_path();
if ( '' !== $ladyqr_path ) {
	if ( $ladyqr_config['shortener'] ) {
		require __DIR__ . '/includes/links.php';
		require __DIR__ . '/includes/data.php';
		$ladyqr_link = ladyqr_link_find( $ladyqr_path );
		if ( null !== $ladyqr_link ) {
			header( 'Location: ' . $ladyqr_link['target'], true, $ladyqr_link['status'] );
			exit;
		}
	}
	require __DIR__ . '/404.php';
	exit;
}

header( 'Content-Type: text/html; charset=utf-8' );
$ladyqr_csp = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; " .
	"connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";
header( 'Content-Security-Policy: ' . $ladyqr_csp );
header( 'X-Content-Type-Options: nosniff' );
header( 'Referrer-Policy: no-referrer' );


// Strings the browser-side code needs.
$ladyqr_js_strings = array(
	'empty'    => __( 'Your QR code will appear here', 'ladyqr' ),
	'tooLong'  => __( 'This text is too long for a QR code. Try a shorter one.', 'ladyqr' ),
	'qrAlt'    => __( 'QR code', 'ladyqr' ),
	/* translators: %s: the margin around the QR code, in modules, such as 4. */
	'margin'   => __( 'margin %s', 'ladyqr' ),

	'lowContrast'         => __( 'Low contrast. QR code might be hard to read', 'ladyqr' ),
	'inverted'            => __( 'Inverted colors. QR code might be hard to read', 'ladyqr' ),
	'lowContrastInverted' => __( 'Low contrast and inverted colors. QR code might be hard to read', 'ladyqr' ),
);

/**
 * Returns the path of an idealized QR code for the empty preview: three finder
 * patterns, an alignment pattern and scattered modules. It is a drawing, not a
 * valid code, so nobody can scan it by mistake. The grid is 25 x 25 modules
 * with a 2 module margin, which makes the viewBox 29 x 29.
 */
function ladyqr_skeleton_path() {
	$size = 25;
	$seed = 7;
	$path = 'M2 2h7v7h-7zM3 3v5h5v-5zM4 4h3v3h-3z'
		. 'M20 2h7v7h-7zM21 3v5h5v-5zM22 4h3v3h-3z'
		. 'M2 20h7v7h-7zM3 21v5h5v-5zM4 22h3v3h-3z'
		. 'M18 18h5v5h-5zM19 19v3h3v-3zM20 20h1v1h-1z';

	for ( $y = 0; $y < $size; $y++ ) {
		for ( $x = 0; $x < $size; $x++ ) {
			$finder    = ( $x < 8 && $y < 8 ) || ( $x > $size - 9 && $y < 8 ) || ( $x < 8 && $y > $size - 9 );
			$alignment = $x >= 16 && $x <= 20 && $y >= 16 && $y <= 20;
			$seed      = ( $seed * 75 + 74 ) % 65537; // Small, portable pseudo-random numbers.
			if ( ! $finder && ! $alignment && $seed % 100 < 46 ) {
				$path .= 'M' . ( $x + 2 ) . ' ' . ( $y + 2 ) . 'h1v1h-1z';
			}
		}
	}
	return $path;
}

$ladyqr_lang = str_replace( '_', '-', $ladyqr_config['language'] );
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars( $ladyqr_lang, ENT_QUOTES, 'UTF-8' ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="color-scheme" content="light">
	<title>LadyQR</title>
	<link rel="icon" type="image/png" href="<?php echo ladyqr_asset( 'assets/icon-64.png' ); ?>">
	<link rel="apple-touch-icon" href="<?php echo ladyqr_asset( 'assets/icon-128.png' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/app.css' ); ?>">
</head>
<body>

<button class="help" id="help-open" type="button" aria-label="<?php echo esc_attr__( 'Help', 'ladyqr' ); ?>">
	<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4.5"/><circle cx="12" cy="17.8" r=".6" fill="currentColor"/></svg>
	<span><?php esc_html_e( 'Help', 'ladyqr' ); ?></span>
</button>

<dialog id="help-dialog" aria-labelledby="help-title">
	<div class="dialog-body">
		<div class="dialog-head">
			<img alt="" src="<?php echo ladyqr_asset( 'assets/ladyqr@2X.png' ); ?>" width="48" height="36">
			<h2 id="help-title"><?php esc_html_e( 'About LadyQR', 'ladyqr' ); ?></h2>
			<button class="dialog-close" id="help-close" type="button" aria-label="<?php echo esc_attr__( 'Close', 'ladyqr' ); ?>">&times;</button>
		</div>
		<p><?php esc_html_e( 'LadyQR makes QR codes entirely in your browser. What you type is never sent to a server, and the page loads nothing from outside.', 'ladyqr' ); ?></p>
		<h3><?php esc_html_e( 'How to use it', 'ladyqr' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Type or paste a link or some text in the big field.', 'ladyqr' ); ?></li>
			<li><?php esc_html_e( 'The QR code updates as you type. Scan it with your phone to check it.', 'ladyqr' ); ?></li>
			<li><?php esc_html_e( 'Press Download for a 500 px PNG, or open the arrow for a 1000 px PNG or a vector SVG.', 'ladyqr' ); ?></li>
			<li><?php esc_html_e( 'Open Settings to change the error correction, margin and colors. They are remembered in this browser only.', 'ladyqr' ); ?></li>
		</ol>
		<h3><?php esc_html_e( 'Which format?', 'ladyqr' ); ?></h3>
		<p><?php esc_html_e( 'Use PNG for screens and documents. Use SVG when you will print it large or resize it: it stays sharp at any size.', 'ladyqr' ); ?></p>
		<small><?php esc_html_e( 'Free software, GPL-2.0-or-later', 'ladyqr' ); ?> · github.com/nilovelez/ladyqr</small>
	</div>
</dialog>

<template id="qr-skeleton">
	<svg class="qr-skeleton" viewBox="0 0 29 29" shape-rendering="crispEdges" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="<?php echo ladyqr_skeleton_path(); ?>"/></svg>
</template>

<main>
	<div class="card editor">
		<div class="card-body">
			<input class="field" id="content" type="text" placeholder="<?php echo esc_attr__( 'Type or paste a link or text', 'ladyqr' ); ?>" autocomplete="off" autocapitalize="off" spellcheck="false" autofocus aria-label="<?php echo esc_attr__( 'Text or URL', 'ladyqr' ); ?>">

			<div class="qr" id="qr" role="img" aria-live="polite"></div>

			<p class="warning" id="contrast-warning" role="status" hidden>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
				<span id="contrast-warning-text"></span>
			</p>

			<details class="settings">
				<summary>
					<svg class="settings-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3M14 2v4M8 10v4M16 18v4"/></svg>
					<span class="settings-name"><?php esc_html_e( 'Customize', 'ladyqr' ); ?></span>
					<span class="settings-state">
						<span id="settings-state-text"></span>
						<span class="swatch" id="swatch-fg" aria-hidden="true"></span><span class="swatch" id="swatch-bg" aria-hidden="true"></span>
					</span>
				</summary>
				<div class="settings-body">
					<div>
						<span class="label"><?php esc_html_e( 'Error correction', 'ladyqr' ); ?></span>
						<div class="segmented" id="ecc" role="group" aria-label="<?php echo esc_attr__( 'Error correction', 'ladyqr' ); ?>">
							<button type="button" aria-pressed="false" data-level="L">L<small>7%</small></button>
							<button type="button" aria-pressed="true" data-level="M">M<small>15%</small></button>
							<button type="button" aria-pressed="false" data-level="Q">Q<small>25%</small></button>
							<button type="button" aria-pressed="false" data-level="H">H<small>30%</small></button>
						</div>
					</div>
					<div class="colors">
						<div>
							<label class="label" for="fg"><?php esc_html_e( 'Module color', 'ladyqr' ); ?></label>
							<input id="fg" type="color" value="#1d1a1b">
						</div>
						<div>
							<label class="label" for="bg"><?php esc_html_e( 'Background color', 'ladyqr' ); ?></label>
							<input id="bg" type="color" value="#ffffff">
						</div>
					</div>
					<div>
						<label class="label" for="margin"><?php esc_html_e( 'Margin (modules)', 'ladyqr' ); ?></label>
						<div class="range">
							<input id="margin" type="range" min="0" max="8" step="1" value="4">
							<output id="margin-value" for="margin">4</output>
						</div>
					</div>
					<button class="reset" id="reset" type="button" disabled><?php esc_html_e( 'Reset settings', 'ladyqr' ); ?></button>
				</div>
			</details>

			<div class="download">
				<button class="download-main" id="download-png-500" type="button" disabled>
					<?php esc_html_e( 'Download', 'ladyqr' ); ?> <small>PNG 500px</small>
				</button>
				<button class="download-more" id="download-more" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="download-menu" aria-label="<?php echo esc_attr__( 'More download formats', 'ladyqr' ); ?>" disabled>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
				</button>
				<div class="download-menu" id="download-menu" hidden>
					<button type="button" id="download-png-1000">PNG <span>1000px</span></button>
					<button type="button" id="download-svg">SVG <span><?php esc_html_e( 'vector', 'ladyqr' ); ?></span></button>
				</div>
			</div>
		</div>
	</div>
</main>

<footer>
	<a href="https://github.com/nilovelez/ladyqr" target="_blank" rel="noopener">
		<img alt="" src="<?php echo ladyqr_asset( 'assets/ladyqr.png' ); ?>" width="32" height="24">
		<span>
			<?php
			/* translators: %s: the product name, LadyQR. */
			echo sprintf( esc_html__( 'Made with %s, get yours free', 'ladyqr' ), '<b>LadyQR</b>' );
			?>
		</span>
	</a>
</footer>

<script type="application/json" id="ladyqr-i18n"><?php echo json_encode( $ladyqr_js_strings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ); ?></script>
<script type="module" src="<?php echo ladyqr_asset( 'assets/app.js' ); ?>"></script>
</body>
</html>

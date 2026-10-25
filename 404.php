<?php
/**
 * The not-found page, with a real 404 status. index.php shows it for any
 * address that is neither a file nor a short link.
 */

defined( 'LADYQR' ) || define( 'LADYQR', true );
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

$ladyqr_config = ladyqr_config();
ladyqr_load_textdomain( $ladyqr_config['language'] );

http_response_code( 404 );
header( 'Content-Type: text/html; charset=utf-8' );
header( "Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'" );
header( 'X-Content-Type-Options: nosniff' );
header( 'Referrer-Policy: no-referrer' );
header( 'X-Robots-Tag: noindex' );

// The address that was asked for is not the address of this script, so every
// link goes through absolute paths from the site's own folder.
$ladyqr_base = ladyqr_base_path();
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars( str_replace( '_', '-', $ladyqr_config['language'] ), ENT_QUOTES, 'UTF-8' ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="color-scheme" content="light">
	<meta name="robots" content="noindex">
	<title><?php esc_html_e( 'Page not found', 'ladyqr' ); ?></title>
	<link rel="icon" type="image/png" href="<?php echo ladyqr_asset( 'assets/icon-64.png', $ladyqr_base . '/' ); ?>">
	<link rel="apple-touch-icon" href="<?php echo ladyqr_asset( 'assets/icon-128.png', $ladyqr_base . '/' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/app.css', $ladyqr_base . '/' ); ?>">
	<link rel="stylesheet" href="<?php echo ladyqr_asset( 'assets/admin.css', $ladyqr_base . '/' ); ?>">
</head>
<body class="admin">

<main>
	<div class="card">
		<div class="card-body">
			<div class="admin-head">
				<img src="<?php echo ladyqr_asset( 'assets/ladyqr@2X.png', $ladyqr_base . '/' ); ?>" alt="" width="64" height="48">
				<h1><?php esc_html_e( 'Page not found', 'ladyqr' ); ?></h1>
			</div>
			<p><?php esc_html_e( 'This short link does not exist, or it was removed.', 'ladyqr' ); ?></p>
			<a class="btn" href="<?php echo htmlspecialchars( $ladyqr_base . '/', ENT_QUOTES, 'UTF-8' ); ?>"><?php esc_html_e( 'Make a QR code', 'ladyqr' ); ?></a>
		</div>
	</div>
</main>

</body>
</html>

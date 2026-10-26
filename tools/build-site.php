<?php
/**
 * Builds the public website into _site/.
 *
 * Usage: php tools/build-site.php
 *
 * - site/ is copied as is: the landing page.
 * - The landing page embeds the real editor. PHP renders index.php (in English)
 *   to static HTML, because GitHub Pages cannot run PHP, and the editor's
 *   markup goes where site/index.html has <!-- ladyqr:editor -->. GitHub Pages
 *   cannot send HTTP headers either, so the Content-Security-Policy goes into
 *   a <meta> tag, in place of <!-- ladyqr:csp -->.
 *
 * The GitHub Actions workflow .github/workflows/pages.yml runs this and
 * deploys _site/ to GitHub Pages.
 */

$root = dirname( __DIR__ );
$out  = $root . '/_site';

/**
 * Removes a directory and everything in it.
 */
function ladyqr_remove_dir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $dir );
}

/**
 * Copies a directory, skipping the file names listed in $skip.
 */
function ladyqr_copy_dir( $from, $to, array $skip = array() ) {
	if ( ! is_dir( $to ) && ! mkdir( $to, 0755, true ) ) {
		fwrite( STDERR, "Cannot create $to\n" );
		exit( 1 );
	}
	foreach ( scandir( $from ) as $name ) {
		if ( '.' === $name || '..' === $name || in_array( $name, $skip, true ) ) {
			continue;
		}
		is_dir( "$from/$name" ) ? ladyqr_copy_dir( "$from/$name", "$to/$name", $skip ) : copy( "$from/$name", "$to/$name" );
	}
}

ladyqr_remove_dir( $out );
ladyqr_copy_dir( $root . '/site', $out );

// The editor's files, and the images that only the website uses.
$web_images = array( 'ladyqr-web.webp', 'web-background-kawaii.webp' );
ladyqr_copy_dir( $root . '/assets', $out . '/assets', array_merge( array( 'icon-full.png', 'icon-vector.ai', 'web-background-kawaii.jpg' ), $web_images ) );
foreach ( $web_images as $image ) {
	copy( $root . '/assets/' . $image, $out . '/img/' . $image );
}

// The editor is rendered from a copy of the site with a configuration of its
// own: without one, index.php sends every visit to the installer and stops,
// and a local ladyqr-config.php must not change the page either.
$site = sys_get_temp_dir() . '/ladyqr-site-' . bin2hex( random_bytes( 4 ) );
mkdir( $site, 0755, true );
foreach ( array( 'index.php', 'includes', 'languages' ) as $name ) {
	is_dir( "$root/$name" ) ? ladyqr_copy_dir( "$root/$name", "$site/$name" ) : copy( "$root/$name", "$site/$name" );
}
file_put_contents( "$site/ladyqr-config.php", "<?php\ndefined( 'LADYQR' ) || exit;\ndefine( 'LADYQR_LANGUAGE', 'en_US' );\n" );

// index.php may stop the script with exit; then the build must fail.
$rendered = false;
register_shutdown_function(
	function () use ( &$rendered, $site ) {
		ladyqr_remove_dir( $site );
		if ( ! $rendered ) {
			fwrite( STDERR, "The demo page did not render.\n" );
			exit( 1 );
		}
	}
);

ob_start();
require $site . '/index.php'; // Sets $ladyqr_csp.
$html     = ob_get_clean();
$rendered = true;

// The editor's markup: the card, the template for its empty preview and the
// strings for app.js. The help button and dialog and the footer are not used:
// the landing page has its own. The field is not focused on load, because on
// a phone that would open the keyboard over the welcome.
$parts = array();
if ( ! preg_match( '#<main>\s*(<div class="card editor">.*</div>)\s*</main>#s', $html, $parts['card'] )
	|| ! preg_match( '#<template id="qr-skeleton">.*?</template>#s', $html, $parts['skeleton'] )
	|| ! preg_match( '#<script type="application/json" id="ladyqr-i18n">.*?</script>#s', $html, $parts['i18n'] ) ) {
	fwrite( STDERR, "The editor did not render as expected.\n" );
	exit( 1 );
}
$editor = str_replace( ' autofocus', '', $parts['skeleton'][0] . "\n" . $parts['card'][1] . "\n" . $parts['i18n'][0] );

// A <meta> policy cannot use frame-ancestors, so leave it out.
$meta = '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars( str_replace( "; frame-ancestors 'none'", '', $ladyqr_csp ), ENT_QUOTES, 'UTF-8' ) . '">';

$landing = file_get_contents( $out . '/index.html' );
foreach ( array( '<!-- ladyqr:csp -->' => $meta, '<!-- ladyqr:editor -->' => $editor ) as $marker => $replacement ) {
	if ( 1 !== substr_count( $landing, $marker ) ) {
		fwrite( STDERR, "site/index.html must have $marker exactly once.\n" );
		exit( 1 );
	}
	$landing = str_replace( $marker, $replacement, $landing );
}
file_put_contents( $out . '/index.html', $landing );

echo "Built $out\n";

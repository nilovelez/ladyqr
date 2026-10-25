<?php
/**
 * Builds the public website into _site/.
 *
 * Usage: php tools/build-site.php
 *
 * - site/ is copied as is: the landing page.
 * - try/ is a live demo of the real editor. PHP renders index.php (in English)
 *   to static HTML, because GitHub Pages cannot run PHP and cannot send HTTP
 *   headers, so the Content-Security-Policy goes into a <meta> tag instead.
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

// The live demo.
ladyqr_copy_dir( $root . '/assets', $out . '/try/assets', array( 'icon-full.png', 'icon-vector.ai' ) );

// The page is rendered from a copy of the site with a configuration of its
// own: without one, index.php sends every visit to the installer and stops,
// and a local ladyqr-config.php must not change the demo either.
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

// A <meta> policy cannot use frame-ancestors, so leave it out.
$meta = str_replace( "; frame-ancestors 'none'", '', $ladyqr_csp );
$html = str_replace(
	'<meta charset="utf-8">',
	'<meta charset="utf-8">' . "\n\t" . '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars( $meta, ENT_QUOTES, 'UTF-8' ) . '">',
	$html,
	$count
);
if ( 1 !== $count || false === strpos( $html, 'id="ladyqr-i18n"' ) ) {
	fwrite( STDERR, "The demo page did not render as expected.\n" );
	exit( 1 );
}
file_put_contents( $out . '/try/index.html', $html );

echo "Built $out\n";

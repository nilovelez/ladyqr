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

ob_start();
require $root . '/index.php'; // Sets $ladyqr_csp.
$html = ob_get_clean();

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

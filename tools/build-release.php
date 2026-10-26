<?php
/**
 * Builds the release zips in _release/: ladyqr-latest.zip, with a ladyqr/
 * folder holding only what goes on the server, and theme.zip, an example theme
 * folder.
 *
 * Run it from anywhere: php tools/build-release.php
 *
 * The files are listed one by one, so nothing new ends up in the zip by
 * accident. It stops with an error if a file that must be there is missing, or
 * if the zip would carry a file that belongs to an install (the configuration,
 * the links, an .htaccess) or the full-size and vector icons and the website's
 * images, which are for development only.
 */

$root = dirname( __DIR__ );
$out  = $root . '/_release/ladyqr-latest.zip';

$files   = array( 'index.php', '404.php', 'ladyqr-config.sample.php', 'LICENSE', 'INSTALL.txt' );
$folders = array( 'admin', 'includes', 'assets', 'languages' );
$skip    = array( 'assets/icon-full.png', 'assets/icon-vector.ai', 'assets/ladyqr-web.webp', 'assets/web-background-kawaii.webp', 'assets/web-background-kawaii_1080.png' );

// Never in a release, wherever they are.
$forbidden = array( 'ladyqr-config.php', 'ladyqr-data.php', '.htaccess', '.htaccess.bak', 'icon-full.png', 'icon-vector.ai' );

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "PHP's zip extension is needed.\n" );
	exit( 1 );
}

$list = array();
foreach ( $files as $file ) {
	if ( ! is_file( $root . '/' . $file ) ) {
		fwrite( STDERR, "Missing: $file\n" );
		exit( 1 );
	}
	$list[] = $file;
}
foreach ( $folders as $folder ) {
	if ( ! is_dir( $root . '/' . $folder ) ) {
		fwrite( STDERR, "Missing folder: $folder\n" );
		exit( 1 );
	}
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $folder, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $items as $item ) {
		if ( $item->isFile() ) {
			$path = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $root ) + 1 ) );
			if ( ! in_array( $path, $skip, true ) ) {
				$list[] = $path;
			}
		}
	}
}
sort( $list );

foreach ( $list as $path ) {
	if ( in_array( basename( $path ), $forbidden, true ) ) {
		fwrite( STDERR, "This file must not be in a release: $path\n" );
		exit( 1 );
	}
}

if ( ! is_dir( dirname( $out ) ) ) {
	mkdir( dirname( $out ), 0777, true );
}
if ( is_file( $out ) ) {
	unlink( $out );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $out, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create $out\n" );
	exit( 1 );
}
foreach ( $list as $path ) {
	$zip->addFile( $root . '/' . $path, 'ladyqr/' . $path );
}
$zip->close();

// The example theme (docs/theme/): a complete theme folder to unzip next to
// index.php, so that anyone can try it by uploading it. The blank index.html
// keeps a server that lists folders from listing it. It is a separate zip
// because the theme is the owner's own and a release must never overwrite it.
$theme_out = dirname( $out ) . '/theme.zip';
if ( is_file( $theme_out ) ) {
	unlink( $theme_out );
}
$theme = new ZipArchive();
if ( true !== $theme->open( $theme_out, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create $theme_out\n" );
	exit( 1 );
}
foreach ( array( 'index.html', 'custom.css', 'head.html', 'header.html', 'footer.html', 'logo.png' ) as $name ) {
	if ( ! is_file( $root . '/docs/theme/' . $name ) ) {
		fwrite( STDERR, "Missing: docs/theme/$name\n" );
		exit( 1 );
	}
	$theme->addFile( $root . '/docs/theme/' . $name, 'theme/' . $name );
}
$theme->close();

echo count( $list ) . " files in $out (" . round( filesize( $out ) / 1024 ) . " KB)\n";
foreach ( $list as $path ) {
	echo "  ladyqr/$path\n";
}

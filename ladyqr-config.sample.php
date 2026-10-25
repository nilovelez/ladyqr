<?php
/**
 * LadyQR configuration. The installer writes this file for you: open your site
 * after uploading LadyQR and it starts by itself. To write it by hand instead,
 * copy this file to `ladyqr-config.php`, either in the web root or one level
 * above it, and change what you need. Once it exists, LadyQR is installed.
 *
 * Keep the line below. index.php defines LADYQR before it reads this file, so
 * if someone requests the file directly it stops and prints nothing, and it is
 * safe to leave in the web root.
 */
defined( 'LADYQR' ) || exit;

// Interface language, as a WordPress-style locale. Leave it as 'en_US' for
// English, or use another one for which languages/ladyqr-<locale>.mo exists.
define( 'LADYQR_LANGUAGE', 'en_US' );

// The address of the site, without a final slash, such as https://example.com
// or https://example.com/qr. Short links are built on it. Without it, LadyQR
// uses the address of each visit.
// define( 'LADYQR_URL', 'https://example.com' );

// Short links. Remove the two slashes to turn them on. They need a user and a
// password below, and an .htaccess that sends the addresses that are not files
// to index.php: the admin writes it the first time you open it. The links are
// kept in ladyqr-data.php, next to this file.
// define( 'LADYQR_SHORTENER', true );

// The admin, at /admin/. Remove the two slashes in front of both lines and
// write your user and password. The password goes in plain text: the first
// time you log in, LadyQR replaces it with a secure hash in this file. To
// change it later, write the new one in plain text again.
//
// define( 'LADYQR_USER', 'your-user' );
// define( 'LADYQR_PASSWORD', 'your-password' );

// Minutes without activity after which the admin asks for the password again.
// define( 'LADYQR_SESSION_TIMEOUT', 15 );

// Advanced, and not needed: what the QR codes of short links start with,
// instead of the site's address. Written in uppercase and without https://,
// such as 1QR.ES/, it makes smaller QR codes, because LadyQR then writes the
// short link in uppercase too and the code can use a more compact encoding.
// Most phone cameras still open it as a web address. It must lead to the
// site: test one of the codes before you print it.
// define( 'LADYQR_QR_BASE', '1QR.ES/' );

// Opens the admin without a login, with a permanent warning on every page.
// Only for trying it locally. Anyone who can reach the page could change
// your short links, so do not use this on a public site.
// define( 'LADYQR_REQUIRE_LOGIN', false );

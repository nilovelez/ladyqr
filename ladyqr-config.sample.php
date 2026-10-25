<?php
/**
 * LadyQR configuration. The installer, at /admin/, writes this file for you.
 * To write it by hand instead, copy this file to `ladyqr-config.php`, either in
 * the web root or one level above it, and change what you need.
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
// or https://example.com/qr. Without it, LadyQR uses the address of each visit.
// define( 'LADYQR_URL', 'https://example.com' );

// Short links. Remove the two slashes to turn them on. They need a user and a
// password below, and an .htaccess that sends the addresses that are not files
// to index.php: the admin writes it the first time you log in.
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

// Opens the admin without a login, with a permanent warning on every page.
// Only for trying it locally. Anyone who can reach the page could change
// your short links, so do not use this on a public site.
// define( 'LADYQR_REQUIRE_LOGIN', false );

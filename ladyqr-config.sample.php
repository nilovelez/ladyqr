<?php
/**
 * LadyQR configuration. Copy this file to `ladyqr-config.php`, either in the
 * web root or one level above it, and change what you need.
 *
 * Keep the line below. index.php defines LADYQR before it reads this file, so
 * if someone requests the file directly it stops and prints nothing, and it is
 * safe to leave in the web root.
 */
defined( 'LADYQR' ) || exit;

return array(
	// Interface language, as a WordPress-style locale. Leave it as 'en_US' for
	// English, or use another one for which languages/ladyqr-<locale>.mo exists.
	'language' => 'en_US',

	// The admin, at /admin/. Remove the two slashes in front of both lines and
	// write your user and password. The password goes in plain text: the first
	// time you log in, LadyQR replaces it with a secure hash in this file. To
	// change it later, write the new one in plain text again.
	//
	// 'user'     => 'your-user',
	// 'password' => 'your-password',

	// Minutes without activity after which the admin asks for the password again.
	// 'session_timeout' => 15,

	// Opens the admin without a login, with a permanent warning on every page.
	// Only for trying it locally. Anyone who can reach the page could change
	// your short links, so do not use this on a public site.
	// 'require_login' => false,
);

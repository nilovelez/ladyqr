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
);

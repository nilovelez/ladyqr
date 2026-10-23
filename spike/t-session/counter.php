<?php
// Test 9: sessions. The counter must go up on every request from the same browser.
session_start();
$_SESSION['n'] = ( isset( $_SESSION['n'] ) ? $_SESSION['n'] : 0 ) + 1;
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );
echo json_encode(
	array(
		'n'             => $_SESSION['n'],
		'save_path'     => session_save_path(),
		'cookie_params' => session_get_cookie_params(),
	)
);

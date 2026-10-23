<?php
// Test 3: the 404 handler. It reports what Apache told PHP, and makes sure the
// response keeps the 404 status.
$initial = http_response_code();
if ( 404 !== $initial ) {
	http_response_code( 404 );
}
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
echo "LADYQR-SPIKE-404\n";
echo 'initial_status=' . var_export( $initial, true ) . "\n";
echo 'redirect_status=' . ( isset( $_SERVER['REDIRECT_STATUS'] ) ? $_SERVER['REDIRECT_STATUS'] : 'not set' ) . "\n";
echo 'redirect_url=' . ( isset( $_SERVER['REDIRECT_URL'] ) ? $_SERVER['REDIRECT_URL'] : 'not set' ) . "\n";
echo 'request_uri=' . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'not set' ) . "\n";

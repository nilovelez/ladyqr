<?php
// Where the test redirects end up.
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
echo "LADYQR-SPIKE-TARGET\n";
echo 'via=' . ( isset( $_GET['via'] ) ? preg_replace( '/[^A-Za-z]/', '', $_GET['via'] ) : '' ) . "\n";
echo 'slug=' . ( isset( $_GET['slug'] ) ? preg_replace( '/[^a-z0-9-]/', '', $_GET['slug'] ) : '' ) . "\n";

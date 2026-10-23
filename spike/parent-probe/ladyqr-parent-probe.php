<?php
// Upload this file ONE LEVEL ABOVE the web root (not inside it), for test 11.
// It checks that PHP can read a file there, which is where the configuration
// file will be looked up when it is not in the web root.
return array( 'probe' => 'parent directory include works' );
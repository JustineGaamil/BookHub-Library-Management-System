<?php
// Basic configuration

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_db');

// Session settings
ini_set('session.use_strict_mode', 1);
session_name('LIBSESSID');
session_start();

// Utility for redirects
function redirect(string $path): void {
	header('Location: ' . $path);
	exit;
}



<?php
require_once __DIR__ . '/config.php';

function db_connect(): mysqli {
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	if ($mysqli->connect_errno) {
		http_response_code(500);
		echo 'Database connection failed';
		exit;
	}
	$mysqli->set_charset('utf8mb4');
	return $mysqli;
}

function db(): mysqli {
	static $conn = null;
	if ($conn === null) {
		$conn = db_connect();
	}
	return $conn;
}



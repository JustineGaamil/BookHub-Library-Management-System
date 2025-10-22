<?php
require_once __DIR__ . '/db.php';

function current_user(): ?array {
	return $_SESSION['user'] ?? null;
}

function require_login(): void {
	if (!current_user()) {
		redirect('login.php');
	}
}

function is_admin(): bool {
	$user = current_user();
	return $user && $user['role'] === 'admin';
}

function require_admin(): void {
	require_login();
	if (!is_admin()) {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}

function login(string $username, string $password): bool {
	$mysqli = db();
	// Ensure a default admin exists if the table is empty or missing the admin user
	$exists = $mysqli->query("SELECT COUNT(*) c FROM staff")->fetch_assoc();
	if ($exists && (int)$exists['c'] === 0) {
		$hash = password_hash('admin123', PASSWORD_DEFAULT);
		$seed = $mysqli->prepare('INSERT INTO staff (username, password_hash, full_name, role) VALUES (\'admin\', ?, \'System Administrator\', \'admin\')');
		$seed->bind_param('s', $hash);
		$seed->execute();
	}
	// If user types the default admin credentials and the admin record doesn't exist, create it
	if ($username === 'admin') {
		$checkAdmin = $mysqli->prepare('SELECT id FROM staff WHERE username=\'admin\'');
		$checkAdmin->execute();
		$resAdmin = $checkAdmin->get_result();
		if ($resAdmin->num_rows === 0 && $password === 'admin123') {
			$hash = password_hash('admin123', PASSWORD_DEFAULT);
			$seed = $mysqli->prepare('INSERT INTO staff (username, password_hash, full_name, role) VALUES (\'admin\', ?, \'System Administrator\', \'admin\')');
			$seed->bind_param('s', $hash);
			$seed->execute();
		}
	}

	$stmt = $mysqli->prepare('SELECT id, username, password_hash, password, full_name, role FROM staff WHERE username = ?');
	$stmt->bind_param('s', $username);
	$stmt->execute();
	$res = $stmt->get_result();
	$user = $res->fetch_assoc();
	// Prefer plaintext password match if available, else fallback to hash
	$plaintextOk = $user && isset($user['password']) && $user['password'] !== null && hash_equals((string)$user['password'], $password);
	$hashOk = $user && isset($user['password_hash']) && $user['password_hash'] !== '' && password_verify($password, $user['password_hash']);
	if ($plaintextOk || $hashOk) {
		$_SESSION['user'] = [
			'id' => (int)$user['id'],
			'username' => $user['username'],
			'full_name' => $user['full_name'],
			'role' => $user['role']
		];
		return true;
	}
	return false;
}

function logout(): void {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
}



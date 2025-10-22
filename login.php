<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = trim($_POST['password'] ?? '');
	if (login($username, $password)) {
		redirect('index.php');
	} else {
		$error = 'Invalid credentials';
	}
}
include __DIR__ . '/header.php';
?>
<div style="display:flex; align-items:center; justify-content:center; min-height:60vh;">
	<div style="width:100%; max-width:360px; padding:24px; border:1px solid #ddd; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
		<div style="text-align:center; margin-bottom:16px;">
			<div style="font-size:24px; font-weight:bold;">Library Login</div>
			<div style="color:#666; font-size:13px;">Sign in</div>
		</div>
		<?php if ($error): ?>
			<div style="background:#ffebee; color:#b71c1c; padding:8px 12px; border:1px solid #ffcdd2; border-radius:4px; margin-bottom:12px;">
				<?php echo htmlspecialchars($error); ?>
			</div>
		<?php endif; ?>
		<form method="post">
			<div style="margin-bottom:10px;">
				<label style="display:block; font-size:12px; color:#444; margin-bottom:4px;">Username</label>
				<input style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;" type="text" name="username" required autofocus>
			</div>
			<div style="margin-bottom:14px;">
				<label style="display:block; font-size:12px; color:#444; margin-bottom:4px;">Password</label>
				<input style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;" type="password" name="password" required>
			</div>
			<button class="btn" style="width:100%;" type="submit">Sign in</button>
		</form>
		<div style="margin-top:10px; color:#666; font-size:12px; text-align:center;">
		</div>
	</div>
</div>
<?php include __DIR__ . '/footer.php';



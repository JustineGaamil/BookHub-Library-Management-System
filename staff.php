<?php
require_once __DIR__ . '/auth.php';
require_admin();
$mysqli = db();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$full_name = trim($_POST['full_name'] ?? '');
	$role = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
	$password = $_POST['password'] ?? '';
	if ($id) {
		if ($password !== '') {
			$stmt = $mysqli->prepare('UPDATE staff SET username=?, full_name=?, role=?, password=? WHERE id=?');
			$stmt->bind_param('ssssi', $username, $full_name, $role, $password, $id);
		} else {
			$stmt = $mysqli->prepare('UPDATE staff SET username=?, full_name=?, role=? WHERE id=?');
			$stmt->bind_param('sssi', $username, $full_name, $role, $id);
		}
		$stmt->execute();
		$msg = 'Staff updated';
	} else {
		$plain = $password ?: 'password123';
		$stmt = $mysqli->prepare('INSERT INTO staff (username, password, full_name, role) VALUES (?,?,?,?)');
		$stmt->bind_param('ssss', $username, $plain, $full_name, $role);
		$stmt->execute();
		$msg = 'Staff added';
	}
}

if ($action === 'delete' && $id) {
	$stmt = $mysqli->prepare('DELETE FROM staff WHERE id=?');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$msg = 'Staff deleted';
}

include __DIR__ . '/header.php';
?>
<h2>Staff</h2>
<?php if ($msg): ?>
<p style="color:green;"><?php echo h($msg); ?></p>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $id)): ?>
<?php
	$staff = ['username'=>'','full_name'=>'','role'=>'staff'];
	if ($action === 'edit') {
		$stmt = $mysqli->prepare('SELECT * FROM staff WHERE id=?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$staff = $stmt->get_result()->fetch_assoc();
	}
?>
<h3><?php echo $action === 'add' ? 'Add' : 'Edit'; ?> Staff</h3>
<form method="post" action="staff.php?action=save<?php echo $id?('&id='.$id):''; ?>">
	<div>
		<label>Username</label><br>
		<input type="text" name="username" required value="<?php echo h($staff['username']); ?>">
	</div>
	<div>
		<label>Full Name</label><br>
		<input type="text" name="full_name" required value="<?php echo h($staff['full_name']); ?>">
	</div>
	<div>
		<label>Role</label><br>
		<select name="role">
			<option value="staff" <?php echo $staff['role']==='staff'?'selected':''; ?>>Staff</option>
			<option value="admin" <?php echo $staff['role']==='admin'?'selected':''; ?>>Admin</option>
		</select>
	</div>
	<div>
		<label>Password <?php echo $action==='edit'?'(leave blank to keep)':""; ?></label><br>
		<input type="password" name="password">
	</div>
	<button class="btn" type="submit">Save</button>
	<a class="btn" href="staff.php">Back</a>
</form>
<?php else: ?>

<a class="btn" href="staff.php?action=add">Add Staff</a>
<?php $res = $mysqli->query('SELECT id, username, full_name, role, created_at FROM staff ORDER BY created_at DESC'); ?>
<table>
	<tr>
		<th>Username</th>
		<th>Name</th>
		<th>Role</th>
		<th>Joined</th>
		<th>Actions</th>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
	<tr>
		<td><?php echo h($row['username']); ?></td>
		<td><?php echo h($row['full_name']); ?></td>
		<td><?php echo h(ucfirst($row['role'])); ?></td>
		<td><?php echo h($row['created_at']); ?></td>
		<td>
			<a class="btn" href="staff.php?action=edit&id=<?php echo (int)$row['id']; ?>">Edit</a>
			<a class="btn red" href="staff.php?action=delete&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this staff user?');">Delete</a>
		</td>
	</tr>
	<?php endwhile; ?>
</table>
<?php endif; ?>
<?php include __DIR__ . '/footer.php';




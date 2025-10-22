<?php
require_once __DIR__ . '/auth.php';
require_login();
$mysqli = db();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

if (is_admin() && $action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = trim($_POST['name'] ?? '');
	$address = trim($_POST['address'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$website = trim($_POST['website'] ?? '');
	if ($id) {
		$stmt = $mysqli->prepare('UPDATE publishers SET name=?, address=?, phone=?, website=? WHERE id=?');
		$stmt->bind_param('ssssi', $name, $address, $phone, $website, $id);
		$stmt->execute();
		$msg = 'Publisher updated';
	} else {
		$stmt = $mysqli->prepare('INSERT INTO publishers (name, address, phone, website) VALUES (?,?,?,?)');
		$stmt->bind_param('ssss', $name, $address, $phone, $website);
		$stmt->execute();
		$msg = 'Publisher added';
	}
}

if (is_admin() && $action === 'delete' && $id) {
	$stmt = $mysqli->prepare('DELETE FROM publishers WHERE id=?');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$msg = 'Publisher deleted';
}

include __DIR__ . '/header.php';
?>
<h2>Publishers</h2>
<?php if ($msg): ?>
<p style="color:green;">&nbsp;<?php echo h($msg); ?></p>
<?php endif; ?>

<?php if (is_admin() && ($action === 'add' || ($action === 'edit' && $id))): ?>
<?php
	$publisher = ['name'=>'','address'=>'','phone'=>'','website'=>''];
	if ($action === 'edit') {
		$stmt = $mysqli->prepare('SELECT * FROM publishers WHERE id=?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$publisher = $stmt->get_result()->fetch_assoc();
	}
?>
<h3><?php echo $action === 'add' ? 'Add' : 'Edit'; ?> Publisher</h3>
<form method="post" action="publishers.php?action=save<?php echo $id?('&id='.$id):''; ?>">
	<div>
		<label>Name</label><br>
		<input type="text" name="name" required value="<?php echo h($publisher['name']); ?>">
	</div>
	<div>
		<label>Address</label><br>
		<input type="text" name="address" value="<?php echo h($publisher['address']); ?>">
	</div>
	<div>
		<label>Phone</label><br>
		<input type="text" name="phone" value="<?php echo h($publisher['phone']); ?>">
	</div>
	<div>
		<label>Website</label><br>
		<input type="url" name="website" value="<?php echo h($publisher['website']); ?>">
	</div>
	<button class="btn" type="submit">Save</button>
	<a class="btn" href="publishers.php">Back</a>
</form>
<?php else: ?>

<?php if (is_admin()): ?>
<a class="btn" href="publishers.php?action=add">Add Publisher</a>
<?php endif; ?>

<?php $res = $mysqli->query('SELECT * FROM publishers ORDER BY name'); ?>
<div class="table-wrap">
<table>
	<tr>
		<th>Name</th>
		<th>Address</th>
		<th>Phone</th>
		<th>Website</th>
		<?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
		<tr>
			<td><?php echo h($row['name']); ?></td>
			<td><?php echo h($row['address']); ?></td>
			<td><?php echo h($row['phone']); ?></td>
			<td><?php echo h($row['website']); ?></td>
			<?php if (is_admin()): ?>
			<td>
				<a class="btn" href="publishers.php?action=edit&id=<?php echo (int)$row['id']; ?>">Edit</a>
				<a class="btn red" href="publishers.php?action=delete&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this publisher?');">Delete</a>
			</td>
			<?php endif; ?>
		</tr>
	<?php endwhile; ?>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/footer.php';




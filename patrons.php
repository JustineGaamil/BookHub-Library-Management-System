<?php
require_once __DIR__ . '/auth.php';
require_login();
$mysqli = db();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

if (is_admin() && $action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = trim($_POST['full_name'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$address = trim($_POST['address'] ?? '');
	if ($id) {
		$stmt = $mysqli->prepare('UPDATE patrons SET full_name=?, email=?, phone=?, address=? WHERE id=?');
		$stmt->bind_param('ssssi', $name, $email, $phone, $address, $id);
		$stmt->execute();
		$msg = 'Patron updated';
	} else {
		$stmt = $mysqli->prepare('INSERT INTO patrons (full_name, email, phone, address) VALUES (?,?,?,?)');
		$stmt->bind_param('ssss', $name, $email, $phone, $address);
		$stmt->execute();
		$msg = 'Patron added';
	}
}

if (is_admin() && $action === 'delete' && $id) {
	// Check for related records that would prevent deletion
	$check_loans = $mysqli->prepare('SELECT COUNT(*) FROM loans WHERE patron_id = ?');
	$check_loans->bind_param('i', $id);
	$check_loans->execute();
	$loan_count = $check_loans->get_result()->fetch_row()[0];
	
	$check_history = $mysqli->prepare('SELECT COUNT(*) FROM loan_history WHERE patron_id = ?');
	$check_history->bind_param('i', $id);
	$check_history->execute();
	$history_count = $check_history->get_result()->fetch_row()[0];
	
	if ($loan_count > 0 || $history_count > 0) {
		$msg = 'Cannot delete patron: they have related loan records or loan history. Please handle all loans first.';
	} else {
		// Safe to delete - reservations will be automatically deleted due to CASCADE
		$stmt = $mysqli->prepare('DELETE FROM patrons WHERE id=?');
		$stmt->bind_param('i', $id);
		if ($stmt->execute()) {
			$msg = 'Patron deleted successfully';
		} else {
			$msg = 'Error deleting patron: ' . $stmt->error;
		}
	}
}

include __DIR__ . '/header.php';
?>
<h2>Patrons</h2>
<?php if ($msg): ?>
<p style="color:green;">&nbsp;<?php echo h($msg); ?></p>
<?php endif; ?>

<?php if (is_admin() && ($action === 'add' || ($action === 'edit' && $id))): ?>
<?php
	$patron = ['full_name'=>'','email'=>'','phone'=>'','address'=>''];
	if ($action === 'edit') {
		$stmt = $mysqli->prepare('SELECT * FROM patrons WHERE id=?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$patron = $stmt->get_result()->fetch_assoc();
	}
?>
<h3><?php echo $action === 'add' ? 'Add' : 'Edit'; ?> Patron</h3>
<form method="post" action="patrons.php?action=save<?php echo $id?('&id='.$id):''; ?>">
	<div>
		<label>Name</label><br>
		<input type="text" name="full_name" required value="<?php echo h($patron['full_name']); ?>">
	</div>
	<div>
		<label>Email</label><br>
		<input type="email" name="email" required value="<?php echo h($patron['email']); ?>">
	</div>
	<div>
		<label>Phone</label><br>
		<input type="text" name="phone" value="<?php echo h($patron['phone']); ?>">
	</div>
	<div>
		<label>Address</label><br>
		<input type="text" name="address" value="<?php echo h($patron['address']); ?>">
	</div>
	<button class="btn" type="submit">Save</button>
	<a class="btn" href="patrons.php">Back</a>
</form>
<?php else: ?>

<?php if (is_admin()): ?>
<a class="btn" href="patrons.php?action=add">Add Patron</a>
<?php endif; ?>

<?php $res = $mysqli->query('SELECT * FROM patrons ORDER BY created_at DESC'); ?>
<div class="table-wrap">
<table>
	<tr>
		<th>Name</th>
		<th>Email</th>
		<th>Phone</th>
		<th>Address</th>
		<?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
		<tr>
			<td><?php echo h($row['full_name']); ?></td>
			<td><?php echo h($row['email']); ?></td>
			<td><?php echo h($row['phone']); ?></td>
			<td><?php echo h($row['address']); ?></td>
			<?php if (is_admin()): ?>
			<td>
				<a class="btn" href="patrons.php?action=edit&id=<?php echo (int)$row['id']; ?>">Edit</a>
				<a class="btn red" href="patrons.php?action=delete&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this patron?');">Delete</a>
			</td>
			<?php endif; ?>
		</tr>
	<?php endwhile; ?>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/footer.php';




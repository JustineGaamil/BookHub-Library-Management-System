<?php
require_once __DIR__ . '/auth.php';
require_login();
$mysqli = db();
$user = current_user();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// Admin: edit/delete
if (is_admin() && $action === 'delete' && $id) {
	$stmt = $mysqli->prepare('DELETE FROM reservations WHERE id=?');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$msg = 'Reservation deleted';
}

// Admin only: toggle status
if (is_admin() && $action === 'mark' && $id && isset($_GET['status'])) {
	$status = $_GET['status'];
	if (!in_array($status, ['active','fulfilled','cancelled'], true)) { $status = 'active'; }
	$stmt = $mysqli->prepare('UPDATE reservations SET status=? WHERE id=?');
	$stmt->bind_param('si', $status, $id);
	$stmt->execute();
	$msg = 'Reservation updated';
}

// Optional add (admin only)
if (is_admin() && $action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$book_id = (int)$_POST['book_id'];
	$patron_id = (int)$_POST['patron_id'];
	$stmt = $mysqli->prepare("INSERT INTO reservations (book_id, patron_id) VALUES (?,?)");
	$stmt->bind_param('ii', $book_id, $patron_id);
	$stmt->execute();
	$msg = 'Reservation added';
}

include __DIR__ . '/header.php';
?>
<h2>Reservations</h2>
<?php if ($msg): ?>
<p style="color:green;">&nbsp;<?php echo h($msg); ?></p>
<?php endif; ?>

<?php if (is_admin()): ?>
<details>
	<summary class="btn">Add Reservation</summary>
	<form method="post" action="reservations.php?action=add" style="padding:10px; border:1px solid #ccc; background:#fafafa; margin:10px 0;">
		<label>Book</label>
		<select name="book_id" required>
			<option value="">Select book</option>
			<?php $bs = $mysqli->query("SELECT id, title FROM books ORDER BY title")->fetch_all(MYSQLI_ASSOC);
			foreach ($bs as $b): ?>
				<option value="<?php echo (int)$b['id']; ?>"><?php echo h($b['title']); ?></option>
			<?php endforeach; ?>
		</select>
		<label>Patron</label>
		<select name="patron_id" required>
			<option value="">Select patron</option>
			<?php $ps = $mysqli->query('SELECT id, full_name FROM patrons ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
			foreach ($ps as $p): ?>
				<option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['full_name']); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn" type="submit">Create</button>
	</form>
</details>
<?php endif; ?>

<?php
$sql = "SELECT r.*, b.title, p.full_name AS patron
		FROM reservations r
		JOIN books b ON b.id=r.book_id
		JOIN patrons p ON p.id=r.patron_id
		ORDER BY r.reserved_at DESC";
$res = $mysqli->query($sql);
?>
<div class="table-wrap">
<table>
	<tr>
		<th>Patron</th>
		<th>Book</th>
		<th>Reserved At</th>
		<th>Status</th>
		<?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
	<tr>
		<td><?php echo h($row['patron']); ?></td>
		<td><?php echo h($row['title']); ?></td>
		<td><?php echo h($row['reserved_at']); ?></td>
		<td><?php echo h(ucfirst($row['status'])); ?></td>
		<?php if (is_admin()): ?>
		<td>
			<a class="btn" href="reservations.php?action=mark&id=<?php echo (int)$row['id']; ?>&status=active">Active</a>
			<a class="btn" href="reservations.php?action=mark&id=<?php echo (int)$row['id']; ?>&status=fulfilled">Fulfilled</a>
			<a class="btn" href="reservations.php?action=mark&id=<?php echo (int)$row['id']; ?>&status=cancelled">Cancelled</a>
			<a class="btn red" href="reservations.php?action=delete&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this reservation?');">Delete</a>
		</td>
		<?php endif; ?>
	</tr>
	<?php endwhile; ?>
</table>
</div>
<?php include __DIR__ . '/footer.php';




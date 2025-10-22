<?php
require_once __DIR__ . '/auth.php';
require_login();
$mysqli = db();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function ensure_archived_loan_history_table(mysqli $mysqli): void {
	$mysqli->query("CREATE TABLE IF NOT EXISTS archived_loan_history (
		id INT AUTO_INCREMENT PRIMARY KEY,
		original_id INT NOT NULL,
		loan_id INT NULL,
		book_id INT NOT NULL,
		patron_id INT NOT NULL,
		staff_id INT NOT NULL,
		action ENUM('borrow','return') NOT NULL,
		action_at TIMESTAMP NULL,
		notes VARCHAR(255) NULL,
		archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB");
}

// Admin: allow archive of history entries
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'archive') {
	if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
	$id = (int)$_GET['id'];
	ensure_archived_loan_history_table($mysqli);
	$rec = $mysqli->query('SELECT * FROM loan_history WHERE id=' . $id)->fetch_assoc();
	if ($rec) {
		$ins = $mysqli->prepare('INSERT INTO archived_loan_history (original_id, loan_id, book_id, patron_id, staff_id, action, action_at, notes) VALUES (?,?,?,?,?,?,?,?)');
		$ins->bind_param('iiiissss', $rec['id'], $rec['loan_id'], $rec['book_id'], $rec['patron_id'], $rec['staff_id'], $rec['action'], $rec['action_at'], $rec['notes']);
		$ins->execute();
		$del = $mysqli->prepare('DELETE FROM loan_history WHERE id=?');
		$del->bind_param('i', $id);
		$del->execute();
	}
}

include __DIR__ . '/header.php';
?>
<h2>Loan History</h2>
<form method="get" style="margin-bottom:10px;">
	<input type="text" name="q" placeholder="Search by book/patron" value="<?php echo h($_GET['q'] ?? ''); ?>">
	<button class="btn" type="submit">Search</button>
</form>
<?php
$where = '';
if (!empty($_GET['q'])) {
	$q = '%' . $mysqli->real_escape_string($_GET['q']) . '%';
	$where = "WHERE b.title LIKE '$q' OR p.full_name LIKE '$q'";
}
$sql = "SELECT h.*, b.title, p.full_name AS patron, s.full_name AS staff_name
        FROM loan_history h
        JOIN books b ON b.id=h.book_id
        JOIN patrons p ON p.id=h.patron_id
        JOIN staff s ON s.id=h.staff_id
        $where
        ORDER BY h.action_at DESC";
$res = $mysqli->query($sql);
?>
<div class="table-wrap">
<table>
	<tr>
		<th>When</th>
		<th>Action</th>
		<th>Book</th>
		<th>Patron</th>
		<th>By</th>
		<th>Notes</th>
		<?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
	<tr>
		<td><?php echo h($row['action_at']); ?></td>
		<td><?php echo h(ucfirst($row['action'])); ?></td>
		<td><?php echo h($row['title']); ?></td>
		<td><?php echo h($row['patron']); ?></td>
		<td><?php echo h($row['staff_name']); ?></td>
		<td><?php echo h($row['notes'] ?? ''); ?></td>
		<?php if (is_admin()): ?>
		<td>
			<a class="btn red" href="loan_history.php?action=archive&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Archive this history record?');">Archive</a>
		</td>
		<?php endif; ?>
	</tr>
	<?php endwhile; ?>
</table>
</div>
<?php include __DIR__ . '/footer.php';




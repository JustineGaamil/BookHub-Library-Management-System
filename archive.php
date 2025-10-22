<?php
require_once __DIR__ . '/auth.php';
require_admin();
$mysqli = db();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Ensure tables exist (in case user hasn't archived yet)
$mysqli->query("CREATE TABLE IF NOT EXISTS archived_loans (
	id INT AUTO_INCREMENT PRIMARY KEY,
	original_id INT NOT NULL,
	book_id INT NOT NULL,
	patron_id INT NOT NULL,
	staff_id INT NOT NULL,
	loan_date DATE NOT NULL,
	due_date DATE NOT NULL,
	return_date DATE NULL,
	status ENUM('borrowed','returned','overdue') NOT NULL,
	created_at TIMESTAMP NULL,
	archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
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

$msg = '';
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'restore') {
	$id = (int)$_GET['id'];
	$rec = $mysqli->query('SELECT * FROM archived_loans WHERE id=' . $id)->fetch_assoc();
	if ($rec) {
		// Insert back into loans
		$stmt = $mysqli->prepare('INSERT INTO loans (book_id, patron_id, staff_id, loan_date, due_date, return_date, status, created_at) VALUES (?,?,?,?,?,?,?,?)');
		$stmt->bind_param('iiiissss', $rec['book_id'], $rec['patron_id'], $rec['staff_id'], $rec['loan_date'], $rec['due_date'], $rec['return_date'], $rec['status'], $rec['created_at']);
		$stmt->execute();
		// Update book availability based on return_date
		if ($rec['return_date'] === null) {
			$mysqli->query('UPDATE books SET status=\'loaned\' WHERE id=' . (int)$rec['book_id']);
		} else {
			$mysqli->query('UPDATE books SET status=\'available\' WHERE id=' . (int)$rec['book_id']);
		}
		// Remove from archive
		$del = $mysqli->prepare('DELETE FROM archived_loans WHERE id=?');
		$del->bind_param('i', $id);
		$del->execute();
		$msg = 'Loan restored from archive';
	}
}

include __DIR__ . '/header.php';
?>
<h2>Archive</h2>
<?php if ($msg): ?>
<p style="color:green;">&nbsp;<?php echo h($msg); ?></p>
<?php endif; ?>

<h3>Archived Loans</h3>
<?php
$als = $mysqli->query("SELECT al.*, b.title, p.full_name AS patron
	FROM archived_loans al
	JOIN books b ON b.id=al.book_id
	JOIN patrons p ON p.id=al.patron_id
	ORDER BY al.archived_at DESC");
?>
<div class="table-wrap">
<table>
	<tr>
		<th>Book</th>
		<th>Patron</th>
		<th>Loan Date</th>
		<th>Due Date</th>
		<th>Return Date</th>
		<th>Status</th>
		<th>Archived At</th>
		<th>Actions</th>
	</tr>
	<?php while ($row = $als->fetch_assoc()): ?>
	<tr>
		<td><?php echo h($row['title']); ?></td>
		<td><?php echo h($row['patron']); ?></td>
		<td><?php echo h($row['loan_date']); ?></td>
		<td><?php echo h($row['due_date']); ?></td>
		<td><?php echo h((string)$row['return_date']); ?></td>
		<td><?php echo h(ucfirst($row['status'])); ?></td>
		<td><?php echo h($row['archived_at']); ?></td>
		<td>
			<a class="btn" href="archive.php?action=restore&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Restore this archived loan?');">Restore</a>
		</td>
	</tr>
	<?php endwhile; ?>
</table>
</div>

<h3>Archived Loan History</h3>
<?php
$ahs = $mysqli->query("SELECT ah.*, b.title, p.full_name AS patron, s.full_name AS staff_name
	FROM archived_loan_history ah
	JOIN books b ON b.id=ah.book_id
	JOIN patrons p ON p.id=ah.patron_id
	JOIN staff s ON s.id=ah.staff_id
	ORDER BY ah.archived_at DESC");
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
		<th>Archived At</th>
	</tr>
	<?php while ($row = $ahs->fetch_assoc()): ?>
	<tr>
		<td><?php echo h($row['action_at']); ?></td>
		<td><?php echo h(ucfirst($row['action'])); ?></td>
		<td><?php echo h($row['title']); ?></td>
		<td><?php echo h($row['patron']); ?></td>
		<td><?php echo h($row['staff_name']); ?></td>
		<td><?php echo h($row['notes'] ?? ''); ?></td>
		<td><?php echo h($row['archived_at']); ?></td>
	</tr>
	<?php endwhile; ?>
</table>
</div>
<?php include __DIR__ . '/footer.php';
?>

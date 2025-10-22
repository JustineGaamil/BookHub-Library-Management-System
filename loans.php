<?php
require_once __DIR__ . '/auth.php';
require_login();
$mysqli = db();
$user = current_user();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function update_overdue_loans(mysqli $mysqli): void {
	$today = date('Y-m-d');
	$mysqli->query("UPDATE loans SET status='overdue' WHERE status='borrowed' AND due_date < '$today' AND return_date IS NULL");
}

function ensure_archived_loans_table(mysqli $mysqli): void {
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
}

// Update overdue loans before processing any actions
update_overdue_loans($mysqli);

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// Admin: create loan
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
	$book_id = (int)$_POST['book_id'];
	$patron_id = (int)$_POST['patron_id'];
	$loan_date = $_POST['loan_date'] ?: date('Y-m-d');
	$due_date = $_POST['due_date'] ?: date('Y-m-d', strtotime('+14 days'));
	$staff_id = (int)$user['id'];
	// check availability
	$b = $mysqli->query('SELECT status FROM books WHERE id=' . (int)$book_id)->fetch_assoc();
	if ($b && $b['status'] === 'available') {
		$stmt = $mysqli->prepare("INSERT INTO loans (book_id, patron_id, staff_id, loan_date, due_date, status) VALUES (?,?,?,?,?,'borrowed')");
		$stmt->bind_param('iiiss', $book_id, $patron_id, $staff_id, $loan_date, $due_date);
		$stmt->execute();
		$loan_id = $mysqli->insert_id;
		$mysqli->query("UPDATE books SET status='loaned' WHERE id=" . (int)$book_id);
		$hist = $mysqli->prepare("INSERT INTO loan_history (loan_id, book_id, patron_id, staff_id, action, notes) VALUES (?,?,?,?, 'borrow', 'Created from Loans page')");
		$hist->bind_param('iiii', $loan_id, $book_id, $patron_id, $staff_id);
		$hist->execute();
		$msg = 'Loan created';
	} else {
		$msg = 'Book not available';
	}
}

// Admin: archive loan (instead of delete)
if ($action === 'archive' && $id) {
	if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
	ensure_archived_loans_table($mysqli);
	$loan = $mysqli->query('SELECT * FROM loans WHERE id=' . (int)$id)->fetch_assoc();
	if ($loan) {
		// If not returned yet, restore book to available
		if ($loan['return_date'] === null) {
			$mysqli->query('UPDATE books SET status=\'available\' WHERE id=' . (int)$loan['book_id']);
		}
		// Insert into archive
		$original_id = (int)$loan['id'];
		$book_id = (int)$loan['book_id'];
		$patron_id = (int)$loan['patron_id'];
		$staff_id = (int)$loan['staff_id'];
		$loan_date = (string)$loan['loan_date'];
		$due_date = (string)$loan['due_date'];
		$return_date = $loan['return_date'];
		$status = (string)$loan['status'];
		$created_at = (string)$loan['created_at'];
		$ins = $mysqli->prepare('INSERT INTO archived_loans (original_id, book_id, patron_id, staff_id, loan_date, due_date, return_date, status, created_at) VALUES (?,?,?,?,?,?,?,?,?)');
		$ins->bind_param('iiiisssss', $original_id, $book_id, $patron_id, $staff_id, $loan_date, $due_date, $return_date, $status, $created_at);
		$ins->execute();
		// Remove from active table
		$del = $mysqli->prepare('DELETE FROM loans WHERE id=?');
		$del->bind_param('i', $id);
		$del->execute();
		$msg = 'Loan archived';
	}
}

// Admin only: mark return
if ($action === 'return' && $id) {
	if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
	$loan = $mysqli->query('SELECT * FROM loans WHERE id=' . (int)$id)->fetch_assoc();
	if ($loan && $loan['return_date'] === null) {
		$today = date('Y-m-d');
		$stmt = $mysqli->prepare("UPDATE loans SET return_date=?, status='returned' WHERE id=?");
		$stmt->bind_param('si', $today, $id);
		$stmt->execute();
		$mysqli->query('UPDATE books SET status=\'available\' WHERE id=' . (int)$loan['book_id']);
		$staff_id = (int)$user['id'];
		$hist = $mysqli->prepare("INSERT INTO loan_history (loan_id, book_id, patron_id, staff_id, action, notes) VALUES (?,?,?,?, 'return', 'Returned from Loans page')");
		$hist->bind_param('iiii', $id, $loan['book_id'], $loan['patron_id'], $staff_id);
		$hist->execute();
		$msg = 'Book returned';
	}
}

include __DIR__ . '/header.php';
?>
<h2>Loans</h2>
<?php if ($msg): ?>
<p style="color:green;">&nbsp;<?php echo h($msg); ?></p>
<?php endif; ?>

<?php
// Check for overdue loans and show notification
$overdue_count = (int)$mysqli->query("SELECT COUNT(*) c FROM loans WHERE status='overdue' AND return_date IS NULL")->fetch_assoc()['c'];
if ($overdue_count > 0): ?>
<div style="background: #ffebee; border: 1px solid #e53935; border-radius: 6px; padding: 12px; margin-bottom: 16px; color: #c62828;">
	<i class="fa-solid fa-exclamation-triangle"></i>
	<strong>Alert:</strong> There <?php echo $overdue_count === 1 ? 'is' : 'are'; ?> <?php echo $overdue_count; ?> overdue loan<?php echo $overdue_count === 1 ? '' : 's'; ?> that require attention.
</div>
<?php endif; ?>

<?php if (is_admin()): ?>
<details>
    <summary class="btn">Add Loan</summary>
    <form method="post" action="loans.php?action=create" style="padding:10px; border:1px solid #ccc; background:#fafafa; margin:10px 0;">
        <label>Book</label>
        <select name="book_id" required>
            <option value="">Select book</option>
            <?php $bs = $mysqli->query("SELECT id, title FROM books WHERE status='available' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
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
        <label>Loan Date</label>
        <input type="date" name="loan_date" value="<?php echo date('Y-m-d'); ?>">
        <label>Due Date</label>
        <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
        <button class="btn" type="submit">Create</button>
    </form>
</details>
<?php endif; ?>

<form method="get" style="margin-bottom:10px;">
    <select name="filter">
        <?php $filter = $_GET['filter'] ?? 'all';
        foreach (['all','borrowed','overdue','returned'] as $f): ?>
            <option value="<?php echo $f; ?>" <?php echo $filter===$f?'selected':''; ?>><?php echo ucfirst($f); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
<?php $where = '';
if ($filter !== 'all') { $where = "WHERE l.status='" . $mysqli->real_escape_string($filter) . "'"; }
?>
</form>

<?php
$sql = "SELECT l.*, b.title, p.full_name AS patron
        FROM loans l
        JOIN books b ON b.id=l.book_id
        JOIN patrons p ON p.id=l.patron_id
        $where
        ORDER BY l.created_at DESC";
$res = $mysqli->query($sql);
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
		<?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
	<tr <?php if ($row['status'] === 'overdue'): ?>class="overdue-row"<?php endif; ?>>
		<td><?php echo h($row['title']); ?></td>
		<td><?php echo h($row['patron']); ?></td>
		<td><?php echo h($row['loan_date']); ?></td>
		<td><?php echo h($row['due_date']); ?></td>
		<td><?php echo h((string)$row['return_date']); ?></td>
		<td>
			<?php 
			$status = $row['status'];
			$pill_class = $status === 'returned' ? 'returned' : ($status === 'overdue' ? 'overdue' : 'borrowed');
			?>
			<span class="pill <?php echo $pill_class; ?>"><?php echo h(ucfirst($status)); ?></span>
		</td>
		<?php if (is_admin()): ?>
		<td>
			<?php if ($row['status'] !== 'returned'): ?>
				<a class="btn" href="loans.php?action=return&id=<?php echo (int)$row['id']; ?>">Mark Returned</a>
			<?php endif; ?>
			<a class="btn red" href="loans.php?action=archive&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Archive this loan?');">Archive</a>
		</td>
		<?php endif; ?>
	</tr>
	<?php endwhile; ?>
</table>
</div>
<?php include __DIR__ . '/footer.php';




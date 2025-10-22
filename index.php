<?php
require_once __DIR__ . '/auth.php';
require_login();
$mysqli = db();

// Update overdue loans
$today = date('Y-m-d');
$mysqli->query("UPDATE loans SET status='overdue' WHERE status='borrowed' AND due_date < '$today' AND return_date IS NULL");

// Totals
$totalBooks = (int)$mysqli->query('SELECT COUNT(*) c FROM books')->fetch_assoc()['c'];
$totalPatrons = (int)$mysqli->query('SELECT COUNT(*) c FROM patrons')->fetch_assoc()['c'];
$activeLoans = (int)$mysqli->query("SELECT COUNT(*) c FROM loans WHERE status IN ('borrowed','overdue') AND return_date IS NULL")->fetch_assoc()['c'];
$overdueLoans = (int)$mysqli->query("SELECT COUNT(*) c FROM loans WHERE status='overdue' AND return_date IS NULL")->fetch_assoc()['c'];

include __DIR__ . '/header.php';
?>
<h2>Dashboard</h2>
<div class="cards">
	<div class="card">
		<div class="icon books"><i class="fa-solid fa-book"></i></div>
		<div>
			<div class="meta">Total Books</div>
			<div class="value"><?php echo $totalBooks; ?></div>
		</div>
	</div>
	<div class="card">
		<div class="icon patrons"><i class="fa-solid fa-user"></i></div>
		<div>
			<div class="meta">Total Patrons</div>
			<div class="value"><?php echo $totalPatrons; ?></div>
		</div>
	</div>
	<div class="card">
		<div class="icon loans"><i class="fa-solid fa-hand-holding"></i></div>
		<div>
			<div class="meta">Active Loans</div>
			<div class="value"><?php echo $activeLoans; ?></div>
		</div>
	</div>
	<div class="card">
		<div class="icon overdue"><i class="fa-solid fa-circle-exclamation"></i></div>
		<div>
			<div class="meta">Overdue Loans</div>
			<div class="value"><?php echo $overdueLoans; ?></div>
		</div>
	</div>
</div>

<h3>Recently Borrowed</h3>
<div class="table-wrap">
<table>
	<tr>
		<th>Book</th>
		<th>Patron</th>
		<th>Loan Date</th>
		<th>Due Date</th>
		<th>Status</th>
	</tr>
	<?php
	$sql = "SELECT l.id, b.title, p.full_name, l.loan_date, l.due_date, l.status
			FROM loans l
			JOIN books b ON b.id = l.book_id
			JOIN patrons p ON p.id = l.patron_id
			ORDER BY l.created_at DESC LIMIT 10";
	$res = $mysqli->query($sql);
	while ($row = $res->fetch_assoc()): ?>
		<tr>
			<td><?php echo htmlspecialchars($row['title']); ?></td>
			<td><?php echo htmlspecialchars($row['full_name']); ?></td>
			<td><?php echo htmlspecialchars($row['loan_date']); ?></td>
			<td><?php echo htmlspecialchars($row['due_date']); ?></td>
			<td>
				<?php $st = $row['status']; $cls = $st==='returned'?'returned':($st==='overdue'?'overdue':'borrowed'); ?>
				<span class="pill <?php echo $cls; ?>"><?php echo htmlspecialchars($st); ?></span>
			</td>
		</tr>
	<?php endwhile; ?>
</table>
</div>
<?php include __DIR__ . '/footer.php';



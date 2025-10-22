<?php
require_once __DIR__ . '/auth.php';
require_login();
$mysqli = db();
$user = current_user();

// Helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Handle actions
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';
// Allow displaying messages after redirects
if (isset($_GET['msg'])) {
	$msg = $_GET['msg'];
}

// Admin: create/update/delete book
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
	$title = trim($_POST['title'] ?? '');
	$isbn = trim($_POST['isbn'] ?? '');
	// Normalize empty ISBN to NULL to avoid UNIQUE constraint conflicts on empty string
	$isbn = ($isbn === '') ? null : $isbn;
	$author_id = $_POST['author_id'] !== '' ? (int)$_POST['author_id'] : null;
	$category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
	$publisher_id = $_POST['publisher_id'] !== '' ? (int)$_POST['publisher_id'] : null;
	$published_year = $_POST['published_year'] !== '' ? (int)$_POST['published_year'] : null;
	$status = $_POST['status'] ?? 'available';
	if ($id) {
		$stmt = $mysqli->prepare('UPDATE books SET title=?, isbn=?, author_id=?, category_id=?, publisher_id=?, published_year=?, status=? WHERE id=?');
		$stmt->bind_param('ssiiiisi', $title, $isbn, $author_id, $category_id, $publisher_id, $published_year, $status, $id);
		$ok = $stmt->execute();
		if (!$ok) {
			$msg = 'Error updating book: ' . $stmt->error;
		} else {
		// Redirect to list view (preserve available filter to ensure visibility when applicable)
		if ($status === 'available') {
			header('Location: books.php?status=available&msg=' . urlencode('Book updated'));
			exit;
		}
		header('Location: books.php?msg=' . urlencode('Book updated'));
		exit;
		}
	} else {
		$stmt = $mysqli->prepare('INSERT INTO books (title, isbn, author_id, category_id, publisher_id, published_year, status) VALUES (?,?,?,?,?,?,?)');
		$stmt->bind_param('ssiiiis', $title, $isbn, $author_id, $category_id, $publisher_id, $published_year, $status);
		$ok = $stmt->execute();
		if (!$ok) {
			$msg = 'Error adding book: ' . $stmt->error;
		} else {
		// Redirect to list view; if available, show available list so the new book is visible immediately
		if ($status === 'available') {
			header('Location: books.php?status=available&msg=' . urlencode('Book added'));
			exit;
		}
		header('Location: books.php?msg=' . urlencode('Book added'));
		exit;
		}
	}
}

if ($action === 'delete' && $id) {
	if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
	
	// Check for related records that would prevent deletion
	$check_loans = $mysqli->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ?');
	$check_loans->bind_param('i', $id);
	$check_loans->execute();
	$loan_count = $check_loans->get_result()->fetch_row()[0];
	
	$check_history = $mysqli->prepare('SELECT COUNT(*) FROM loan_history WHERE book_id = ?');
	$check_history->bind_param('i', $id);
	$check_history->execute();
	$history_count = $check_history->get_result()->fetch_row()[0];
	
	if ($loan_count > 0 || $history_count > 0) {
		$msg = 'Cannot delete book: it has related loan records or loan history. Please handle all loans first.';
	} else {
		// Safe to delete - reservations will be automatically deleted due to CASCADE
		$stmt = $mysqli->prepare('DELETE FROM books WHERE id=?');
		$stmt->bind_param('i', $id);
		if ($stmt->execute()) {
			$msg = 'Book deleted successfully';
		} else {
			$msg = 'Error deleting book: ' . $stmt->error;
		}
	}
}

// Staff: assign (borrow) book to patron
if ($action === 'assign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!$user) { http_response_code(403); exit('Forbidden'); }
	$book_id = (int)($_POST['book_id'] ?? 0);
	$patron_id = (int)($_POST['patron_id'] ?? 0);
	// Ensure book is available
	$chk = $mysqli->prepare("SELECT status FROM books WHERE id=?");
	$chk->bind_param('i', $book_id);
	$chk->execute();
	$book = $chk->get_result()->fetch_assoc();
	if ($book && $book['status'] === 'available') {
		// Create loan
		$today = date('Y-m-d');
		$due = date('Y-m-d', strtotime('+14 days'));
		$stmt = $mysqli->prepare('INSERT INTO loans (book_id, patron_id, staff_id, loan_date, due_date, status) VALUES (?,?,?,?,?,\'borrowed\')');
		$staff_id = (int)$user['id'];
		$stmt->bind_param('iiiis', $book_id, $patron_id, $staff_id, $today, $due);
		$stmt->execute();
		$loan_id = $mysqli->insert_id;
		// Update book status
		$mysqli->query("UPDATE books SET status='loaned' WHERE id=" . (int)$book_id);
		// History
		$hist = $mysqli->prepare("INSERT INTO loan_history (loan_id, book_id, patron_id, staff_id, action, notes) VALUES (?,?,?,?, 'borrow', 'Assigned from Books page')");
		$hist->bind_param('iiii', $loan_id, $book_id, $patron_id, $staff_id);
		$hist->execute();
		$msg = 'Book assigned to patron';
	} else {
		$msg = 'Book not available';
	}
}

include __DIR__ . '/header.php';
?>
<h2>Books</h2>
<?php if ($msg): ?>
<p style="color:green;">&nbsp;<?php echo h($msg); ?></p>
<?php endif; ?>

<?php if (is_admin() && ($action === 'add' || ($action === 'edit' && $id))): ?>
<?php
	$book = ['title'=>'','isbn'=>'','author_id'=>'','category_id'=>'','publisher_id'=>'','published_year'=>'','status'=>'available'];
	if ($action === 'edit') {
		$stmt = $mysqli->prepare('SELECT * FROM books WHERE id=?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$book = $stmt->get_result()->fetch_assoc();
	}
	$authors = $mysqli->query('SELECT id, name FROM authors ORDER BY name')->fetch_all(MYSQLI_ASSOC);
	$categories = $mysqli->query('SELECT id, name FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);
	$publishers = $mysqli->query('SELECT id, name FROM publishers ORDER BY name')->fetch_all(MYSQLI_ASSOC);
?>
<h3><?php echo $action === 'add' ? 'Add' : 'Edit'; ?> Book</h3>
<form method="post" action="books.php?action=save<?php echo $id?('&id='.$id):''; ?>">
	<div>
		<label>Title</label><br>
		<input type="text" name="title" required value="<?php echo h($book['title']); ?>">
	</div>
	<div>
		<label>ISBN</label><br>
		<input type="text" name="isbn" value="<?php echo h($book['isbn']); ?>">
	</div>
	<div>
		<label>Year Published</label><br>
		<input type="number" name="published_year" value="<?php echo h((string)$book['published_year']); ?>">
	</div>
	<div>
		<label>Author</label><br>
		<select name="author_id">
			<option value="">-- Select --</option>
			<?php foreach ($authors as $a): ?>
				<option value="<?php echo (int)$a['id']; ?>" <?php echo ($book['author_id']==$a['id'])?'selected':''; ?>><?php echo h($a['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div>
		<label>Category</label><br>
		<select name="category_id">
			<option value="">-- Select --</option>
			<?php foreach ($categories as $c): ?>
				<option value="<?php echo (int)$c['id']; ?>" <?php echo ($book['category_id']==$c['id'])?'selected':''; ?>><?php echo h($c['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div>
		<label>Publisher</label><br>
		<select name="publisher_id">
			<option value="">-- Select --</option>
			<?php foreach ($publishers as $p): ?>
				<option value="<?php echo (int)$p['id']; ?>" <?php echo ($book['publisher_id']==$p['id'])?'selected':''; ?>><?php echo h($p['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div>
		<label>Status</label><br>
		<select name="status">
			<?php foreach (['available','reserved','loaned'] as $s): ?>
				<option value="<?php echo $s; ?>" <?php echo ($book['status']===$s)?'selected':''; ?>><?php echo ucfirst($s); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<button class="btn" type="submit">Save</button>
	<a class="btn" href="books.php">Back</a>
</form>
<?php else: ?>

<?php if (is_admin()): ?>
<a class="btn" href="books.php?action=add">Add Book</a>
<?php endif; ?>

<h3>All Books</h3>
<form method="get" style="margin-bottom:10px; display:flex; gap:8px; align-items:center;">
	<input type="text" name="q" placeholder="Search by title or ISBN" value="<?php echo h($_GET['q'] ?? ''); ?>">
	<button class="btn" type="submit">Search</button>
	<a class="btn" href="books.php">All</a>
	<select name="status" onchange="this.form.submit()">
		<option value="">All statuses</option>
		<?php foreach (['available','reserved','loaned'] as $fs): ?>
			<option value="<?php echo $fs; ?>" <?php echo (($_GET['status'] ?? '')===$fs)?'selected':''; ?>><?php echo ucfirst($fs); ?></option>
		<?php endforeach; ?>
	</select>
</form>
<?php
	$where = '';
	if (!empty($_GET['q'])) {
		$q = '%' . $mysqli->real_escape_string($_GET['q']) . '%';
		$where = "WHERE b.title LIKE '$q' OR b.isbn LIKE '$q'";
	}
	// Apply status filter when provided
	if (isset($_GET['status']) && $_GET['status'] !== '') {
		$status = $mysqli->real_escape_string($_GET['status']);
		$where .= ($where ? " AND " : "WHERE ") . "b.status = '$status'";
	}
	$sql = "SELECT b.*, a.name author, c.name category, p.name publisher
			FROM books b
			LEFT JOIN authors a ON a.id=b.author_id
			LEFT JOIN categories c ON c.id=b.category_id
			LEFT JOIN publishers p ON p.id=b.publisher_id
			$where ORDER BY b.created_at DESC";
	$res = $mysqli->query($sql);
?>
<div class="table-wrap">
<table>
	<tr>
		<th>Title</th>
		<th>ISBN</th>
		<th>Author</th>
		<th>Category</th>
		<th>Publisher</th>
		<th>Status</th>
		<?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
		<tr>
			<td><?php echo h($row['title']); ?></td>
			<td><?php echo h((string)$row['isbn']); ?></td>
			<td><?php echo h($row['author'] ?? ''); ?></td>
			<td><?php echo h($row['category'] ?? ''); ?></td>
			<td><?php echo h($row['publisher'] ?? ''); ?></td>
			<td><?php echo h(ucfirst($row['status'])); ?></td>
			<?php if (is_admin()): ?>
			<td>
				<a class="btn" href="books.php?action=edit&id=<?php echo (int)$row['id']; ?>">Edit</a>
				<a class="btn red" href="books.php?action=delete&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this book?');">Delete</a>
				<?php if ($row['status']==='available'): ?>
					<details style="display:inline-block;">
						<summary class="btn">Assign</summary>
						<form method="post" action="books.php?action=assign" style="display:inline-block; padding:10px; border:1px solid #ccc; background:#fafafa;">
							<input type="hidden" name="book_id" value="<?php echo (int)$row['id']; ?>">
							<select name="patron_id" required>
								<option value="">Select Patron</option>
								<?php $ps = $mysqli->query('SELECT id, full_name FROM patrons ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
								foreach ($ps as $p): ?>
									<option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['full_name']); ?></option>
								<?php endforeach; ?>
							</select>
							<button class="btn" type="submit">Confirm</button>
						</form>
					</details>
				<?php endif; ?>
			</td>
			<?php endif; ?>
		</tr>
	<?php endwhile; ?>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/footer.php';




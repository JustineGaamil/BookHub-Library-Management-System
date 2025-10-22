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
	$description = trim($_POST['description'] ?? '');
	if ($id) {
		$stmt = $mysqli->prepare('UPDATE categories SET name=?, description=? WHERE id=?');
		$stmt->bind_param('ssi', $name, $description, $id);
		$stmt->execute();
		$msg = 'Category updated';
	} else {
		$stmt = $mysqli->prepare('INSERT INTO categories (name, description) VALUES (?,?)');
		$stmt->bind_param('ss', $name, $description);
		$stmt->execute();
		$msg = 'Category added';
	}
}

if (is_admin() && $action === 'delete' && $id) {
	$stmt = $mysqli->prepare('DELETE FROM categories WHERE id=?');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$msg = 'Category deleted';
}

include __DIR__ . '/header.php';
?>
<h2>Categories</h2>
<?php if ($msg): ?>
<p style="color:green;">&nbsp;<?php echo h($msg); ?></p>
<?php endif; ?>

<?php if (is_admin() && ($action === 'add' || ($action === 'edit' && $id))): ?>
<?php
	$category = ['name'=>'','description'=>''];
	if ($action === 'edit') {
		$stmt = $mysqli->prepare('SELECT * FROM categories WHERE id=?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$category = $stmt->get_result()->fetch_assoc();
	}
?>
<h3><?php echo $action === 'add' ? 'Add' : 'Edit'; ?> Category</h3>
<form method="post" action="categories.php?action=save<?php echo $id?('&id='.$id):''; ?>">
	<div>
		<label>Name</label><br>
		<input type="text" name="name" required value="<?php echo h($category['name']); ?>">
	</div>
	<div>
		<label>Description</label><br>
		<input type="text" name="description" value="<?php echo h($category['description']); ?>">
	</div>
	<button class="btn" type="submit">Save</button>
	<a class="btn" href="categories.php">Back</a>
</form>
<?php else: ?>

<?php if (is_admin()): ?>
<a class="btn" href="categories.php?action=add">Add Category</a>
<?php endif; ?>

<?php $res = $mysqli->query('SELECT * FROM categories ORDER BY name'); ?>
<div class="table-wrap">
<table>
	<tr>
		<th>Name</th>
		<th>Description</th>
		<?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
	</tr>
	<?php while ($row = $res->fetch_assoc()): ?>
		<tr>
			<td><?php echo h($row['name']); ?></td>
			<td><?php echo h($row['description']); ?></td>
			<?php if (is_admin()): ?>
			<td>
				<a class="btn" href="categories.php?action=edit&id=<?php echo (int)$row['id']; ?>">Edit</a>
				<a class="btn red" href="categories.php?action=delete&id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this category?');">Delete</a>
			</td>
			<?php endif; ?>
		</tr>
	<?php endwhile; ?>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/footer.php';




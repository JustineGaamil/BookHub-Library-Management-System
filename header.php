<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$isLoginPage = basename($_SERVER['PHP_SELF']) === 'login.php';
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Library</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="assets/styles.css">
</head>
<body style="background-image: url('assets/images/back1.jpg'); background-size: cover; background-position: center; background-attachment: fixed; background-repeat: no-repeat;">
<div class="topbar">
	<div class="brand"><i class="fa-solid fa-book-open"></i><span>Library Management System</span></div>
</div>
<?php if ($user): ?>
<div class="layout">
	<aside class="sidebar" style="font-size:16px;">
		<div class="logo"><img src="assets/images/logo.png" alt="Logo"><span>BookHub</span></div>
		<nav class="menu">
			<a href="index.php" class="menu-item <?php echo $current==='index.php'?'active':''; ?>"><i class="fa-solid fa-house"></i><span>Home</span></a>
			<a href="books.php" class="menu-item <?php echo $current==='books.php'?'active':''; ?>"><i class="fa-solid fa-book"></i><span>Books</span></a>
			<a href="authors.php" class="menu-item <?php echo $current==='authors.php'?'active':''; ?>"><i class="fa-solid fa-user"></i><span>Authors</span></a>
			<a href="patrons.php" class="menu-item <?php echo $current==='patrons.php'?'active':''; ?>"><i class="fa-solid fa-people-group"></i><span>Patrons</span></a>
			<a href="categories.php" class="menu-item <?php echo $current==='categories.php'?'active':''; ?>"><i class="fa-solid fa-tags"></i><span>Categories</span></a>
			<a href="publishers.php" class="menu-item <?php echo $current==='publishers.php'?'active':''; ?>"><i class="fa-solid fa-building"></i><span>Publishers</span></a>
			<a href="loans.php" class="menu-item <?php echo $current==='loans.php'?'active':''; ?>"><i class="fa-solid fa-hand-holding"></i><span>Loans</span></a>
			<a href="reservations.php" class="menu-item <?php echo $current==='reservations.php'?'active':''; ?>"><i class="fa-solid fa-book-bookmark"></i><span>Reservations</span></a>
			<a href="loan_history.php" class="menu-item <?php echo $current==='loan_history.php'?'active':''; ?>"><i class="fa-solid fa-clock-rotate-left"></i><span>Loan History</span></a>
			<?php if ($user && $user['role'] === 'admin'): ?>
				<a href="staff.php" class="menu-item <?php echo $current==='staff.php'?'active':''; ?>"><i class="fa-solid fa-user-shield"></i><span>Staff</span></a>
				<a href="archive.php" class="menu-item <?php echo $current==='archive.php'?'active':''; ?>"><i class="fa-solid fa-box-archive"></i><span>Archive</span></a>
			<?php endif; ?>
		</nav>
		<div class="sidebar-footer">
			<div class="user-inline">
				<?php 
				$profile_image = "assets/images/staff/" . $user['id'] . ".jpg";
				$default_avatar = !file_exists($profile_image);
				?>
				<div class="avatar" <?php if (!$default_avatar): ?>style="background-image: url('<?php echo $profile_image; ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
					<?php if ($default_avatar): ?>
						<?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
					<?php endif; ?>
				</div>
				<span class="name"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</span>
			</div>
			<a class="btn w-full" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
		</div>
	</aside>
	<main class="content">
		<div class="container">
<?php else: ?>
<div class="container">
	<?php if (!$isLoginPage): ?>
		<div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0;">
			<div class="brand" style="color: #0f172a;"><i class="fa-solid fa-book-open"></i><span>Library</span></div>
			<a class="btn" href="login.php"><i class="fa-solid fa-right-to-bracket"></i>Login</a>
		</div>
	<?php endif; ?>
<?php endif; ?>

<?php $isLoggedIn = isset($_SESSION['user']); ?>
<hr>
<footer>
	<small>&copy; <?php echo date('Y'); ?> Library</small>
</footer>
<?php if ($isLoggedIn): ?>
		</div>
	</main>
</div>
<?php else: ?>
</div>
<?php endif; ?>
</body>
</html>



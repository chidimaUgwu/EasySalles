<?php
require 'includes/auth.php';
require_login();

$page_title = 'Staff Dashboard';
include 'includes/header.php';
?>

<div class="text-center">
    <h1>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>Welcome to your shift. Ready to record sales?</p>
    <a href="sale-record.php" class="btn btn-primary btn-large mt-2">Record New Sale</a>
</div>

<?php include 'includes/footer.php'; ?>

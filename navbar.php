<?php
require "connect.php";
session_start();

if (isset($_GET['mode']) && $_GET['mode'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: login.php?mode=login");
    exit();
}

// Determine current page and mode
$current_page = basename($_SERVER['PHP_SELF']);
$mode = $_GET['mode'] ?? '';
?>

<nav class="navbar">
  <div class="navbar-left">
    <a href="index.php" class="nav-btn">Home</a>
    <a href="purchase.php" class="nav-btn">Purchase</a>
    <a href="sell.php" class="nav-btn">Sell</a>
    <a href="item.php" class="nav-btn">Stock</a>
  </div>
  <div class="navbar-right">
    <?php
        // If on login page, show login/register buttons
        if ($current_page === 'login.php') {
            if ($mode === 'login') {
                echo '<a href="login.php?mode=register" class="nav-btn login-btn">Register</a>';
            } else {
                echo '<a href="login.php?mode=login" class="nav-btn login-btn">Login</a>';
            }
        }
        // Otherwise, show Logout if logged in
        elseif (isset($_SESSION['email'])) {
            $name_display = htmlspecialchars($_SESSION['name']);
            echo "<span class='nav-user'>$name_display</span> ";
            // Added confirmation on logout
            echo '<a href="login.php?mode=logout" class="nav-btn login-btn" onclick="return confirm(\'Are you sure you want to logout?\');">Logout</a>';
        }
        // Default: login link for other pages if not logged in
        else {
            echo '<a href="login.php?mode=login" class="nav-btn login-btn">Login</a>';
        }
    ?>
  </div>
</nav>

<style>
.nav-user {
    margin-right: 10px;
    font-weight: bold;
    color: #006b34ff;
}
</style>

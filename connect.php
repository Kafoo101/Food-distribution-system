<?php
// --- SESSION CONFIG (6 HOURS) ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(lifetime_or_options: 6 * 3600); // 6 hours
} else {
    // session already active, extend lifetime
    setcookie(session_name(), session_id(), time() + 6 * 3600, "/");
}

// Check if session expired
if (isset($_SESSION['email'])) {

    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    }

    if (time() - $_SESSION['CREATED'] > $SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: login.php?mode=login&expired=1");
        exit();
    }

    $_SESSION['CREATED'] = time();
}

// Database connection code below
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "accounting";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>

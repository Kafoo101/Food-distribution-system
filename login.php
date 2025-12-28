<?php
require "connect.php";

$mode = $_GET['mode'] ?? 'login';
$error = '';
$success = '';

$email = $_POST['email'] ?? '';
$name = $_POST['name'] ?? '';
$password = $_POST['password'] ?? '';
$verifyPassword = $_POST['verifyPassword'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'login') {
        // Explicitly select columns to match bind_result
        $stmt = $conn->prepare("SELECT email, name, password FROM account WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $error = "Email not found.";
        } else {
            $stmt->bind_result($db_email, $db_name, $db_password_hash);
            $stmt->fetch();

            // Verify password against the hash
            if (password_verify($password, $db_password_hash)) {
                session_start();
                $_SESSION['email'] = $db_email;
                $_SESSION['name'] = $db_name;
                header("Location: index.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        }
        $stmt->close();
    } elseif ($mode === 'register') {
        if ($password !== $verifyPassword) {
            $error = "Passwords do not match.";
        } elseif (empty($name)) {
            $error = "Name cannot be empty.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM account WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                $stmt->close();
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO account (email, name, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $name, $password_hash);
                if ($stmt->execute()) {
                    $success = "Registration successful. You can now login.";
                    $email = $name = $password = $verifyPassword = '';
                    $mode = 'login';
                } else {
                    $error = "Error registering account. Please try again.";
                }
            }
            $stmt->close();
        }
    } elseif ($mode === 'logout') {
        session_unset();
        session_destroy();
        header("Location: login.php?mode=login");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Authentication</title>
    <link rel="stylesheet" href="style.css?v=999">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin:0; padding:0; }
        .main-content { display:flex; justify-content:center; align-items:center; min-height:calc(100vh - 60px); padding-top:20px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="login-container">
        <h1><?php echo $mode === 'login' ? 'Login' : 'Register'; ?></h1>
        <?php 
        if ($error) echo "<div class='error'>" . htmlspecialchars($error) . "</div>";
        if ($success) echo "<div class='success'>$success</div>";
        ?>
        <form method="post">
            <table>
                <tr>
                    <td>Email:</td>
                    <td><input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required></td>
                </tr>
                <?php if ($mode === 'register'): ?>
                <tr>
                    <td>Name:</td>
                    <td><input type="text" name="name" required></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Password:</td>
                    <td>
                        <div class="password-container">
                            <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($password); ?>" required>
                            <i class="eye" onclick="togglePassword('password')">&#128065;</i>
                        </div>
                    </td>
                </tr>
                <?php if ($mode === 'register'): ?>
                <tr>
                    <td>Verify Password:</td>
                    <td>
                        <div class="password-container">
                            <input type="password" id="verifyPassword" name="verifyPassword" required>
                            <i class="eye" onclick="togglePassword('verifyPassword')">&#128065;</i>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="2"><input type="submit" value="<?php echo $mode === 'login' ? 'Login' : 'Register'; ?>"></td>
                </tr>
            </table>
        </form>
        <div class="switch-mode">
            <?php if ($mode === 'login'): ?>
                No account? <a href="?mode=register">Register here</a>
            <?php else: ?>
                Already have an account? <a href="?mode=login">Login here</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling;
    if (input.type === "password") {
        input.type = "text";
        icon.style.color = "#4CAF50";
    } else {
        input.type = "password";
        icon.style.color = "#888";
    }
}
</script>
</body>
</html>

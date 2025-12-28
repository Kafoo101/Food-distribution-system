<?php
require "connect.php";
include "navbar.php";

// Check if session exists
if (!isset($_SESSION['email'])) {
    echo "<script>
            let proceed = confirm('You are not logged in. Click OK to go to Login, or Cancel to go back to Item page.');
            if (proceed) {
                window.location.href='login.php?mode=login';
            } else {
                window.location.href='item.php';
            }
          </script>";
    exit();
}

// Check for item id
if (!isset($_GET['id'])) {
    echo "<script>
            alert('No item selected.');
            window.location.href='item.php';
          </script>";
    exit();
}

$item_id = $_GET['id'];
$error = "";
$success = "";

/* Load item */
$stmt = $conn->prepare("SELECT item_name, stock FROM items WHERE item_id = ?");
$stmt->bind_param("s", $item_id);

if ($stmt->execute()) {
    $stmt->bind_result($item_name, $stock);
    if (!$stmt->fetch()) {
        $stmt->close();
        echo "<script>
                alert('Item not found.');
                window.location.href='item.php';
              </script>";
        exit();
    }
} else {
    $error = "Failed to fetch item.";
}
$stmt->close();

/* Update item */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName  = trim($_POST['item_name']);
    $newStock = $_POST['stock'];

    if ($newName === "") {
        $error = "Item name cannot be empty.";
    } elseif (!is_numeric($newStock) || $newStock < 0) {
        $error = "Stock must be 0 or greater.";
    } else {
        $stmt = $conn->prepare("UPDATE items SET item_name = ?, stock = ? WHERE item_id = ?");
        $stmt->bind_param("sis", $newName, $newStock, $item_id);

        if ($stmt->execute()) {
            $stmt->close();
            echo "<script>
                    alert('Item updated successfully!');
                    window.location.href='item.php';
                  </script>";
            exit();
        } else {
            $error = "Update failed.";
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Item</title>
    <link rel="stylesheet" href="style.css?v=999">
</head>
<body>

<div class="edit-box">
    <h2>Edit Item</h2>

    <?php if ($error) echo "<div class='msg-error'>$error</div>"; ?>
    <?php if ($success) echo "<div class='msg-success'>$success</div>"; ?>

    <form method="post">
        Item ID:<br>
        <input type="text" value="<?php echo htmlspecialchars($item_id); ?>" readonly><br><br>

        Item Name:<br>
        <input type="text" name="item_name" value="<?php echo htmlspecialchars($item_name); ?>"><br><br>

        Stock:<br>
        <div class="number-container">
            <input type="number" id="stock" name="stock" value="<?php echo $stock; ?>" min="0" required>
            <div class="spinner">
                <span onclick="stepStock(1)">&#9650;</span>
                <span onclick="stepStock(-1)">&#9660;</span>
            </div>
        </div>
        
        <div class="button-group">
            <input type="submit" value="Update">
            <input type="button" value="Cancel" class="cancel-btn" onclick="window.location.href='item.php'">
        </div>
    </form>
</div>

<script>
function stepStock(delta) {
    const input = document.getElementById("stock");
    let val = parseInt(input.value) || 0;
    val += delta;
    if (val < 0) val = 0;
    input.value = val;
}
</script>

</body>
</html>

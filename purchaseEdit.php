<?php
require "connect.php";
include "navbar.php";

// SQL statements
$companyIdFixedSQL = "SELECT company_id FROM company WHERE company_name = ? AND operating = 1";
$companyNameSQL    = "SELECT company_name FROM company WHERE company_name LIKE ? AND operating = 1 LIMIT 1";
$itemIdFixedSQL    = "SELECT item_id FROM items WHERE item_name = ?";
$itemNameSQL       = "SELECT item_name FROM items WHERE item_name LIKE ? LIMIT 1";

// Session check
if (!isset($_SESSION['email'])) {
    echo "<script>
            let proceed = confirm('You are not logged in. Click OK to go to Login, or Cancel to go back to Purchase page.');
            if (proceed) { window.location.href='login.php?mode=login'; } 
            else { window.location.href='purchase.php'; }
          </script>";
    exit();
}

// Check purchase ID
if (!isset($_GET['id'])) {
    echo "<script>
            alert('No purchase selected.');
            window.location.href='purchase.php';
          </script>";
    exit();
}

$purchase_id = $_GET['id'];
$error = "";
$success = "";
$autofillItemWarning = "";
$autofillCompanyWarning = "";

// Fetch current purchase info
$stmt = $conn->prepare("
    SELECT i.item_id, i.item_name, p.quantity, p.purchase_price, c.company_id, c.company_name
    FROM purchase p
    JOIN items i ON p.item_id = i.item_id
    JOIN company c ON p.company_id = c.company_id
    WHERE p.purchase_id = ?
");
$stmt->bind_param("s", $purchase_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    header("Location: purchase.php");
    exit();
}
$stmt->bind_result($item_id, $item_name, $quantity, $purchase_price, $company_id, $company_name);
$stmt->fetch();
$stmt->close();

// Initialize form values
$newItemName   = $item_name;
$newQuantity   = $quantity;
$newPrice      = $purchase_price;
$newCompany    = $company_name;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldItemId   = $item_id;
    $oldQuantity = $quantity;

    $itemInput   = trim($_POST['item_name']);
    $quantityInp = intval($_POST['quantity']);
    $priceInp    = floatval($_POST['purchase_price']);
    $companyInp  = trim($_POST['company_name']);
    $timestamp   = date('Y-m-d H:i:s');

    $errors = [];

    if ($itemInput === "" || $companyInp === "") {
        $errors[] = "Item Name and Company Name cannot be empty.";
    }
    if ($quantityInp < 0 || $priceInp < 0) {
        $errors[] = "Quantity and Purchase Price must be 0 or greater.";
    }

    $itemId = null;
    $companyId = null;

    // Check existence of exact names
    if (!checkExist($conn, $itemIdFixedSQL, $companyIdFixedSQL, $itemInput, $companyInp, $itemId, $companyId)) {
        // Autofill text fields to correct spelling
        $correctItem  = autofillField($conn, $itemNameSQL, $itemInput);
        $correctCompany = autofillField($conn, $companyNameSQL, $companyInp);

        // Only show warning if autofill actually changed something
        if (strcasecmp($correctItem, $itemInput) !== 0) $autofillItemWarning = "Autofilled item name from DB!";
        if (strcasecmp($correctCompany, $companyInp) !== 0) $autofillCompanyWarning = "Autofilled company name from DB!";

        $itemInput = $correctItem;
        $companyInp = $correctCompany;

        $errors[] = "Item or Company not found. Autofilled suggestions.";
    }

    // Update form values so textfields show autofilled names
    $newItemName = $itemInput;
    $newCompany  = $companyInp;
    $newQuantity = $quantityInp;
    $newPrice    = $priceInp;

    // Only update DB if exact IDs exist
    if (empty($errors) && $itemId && $companyId) {
        $conn->begin_transaction();
        try {
            // Update stock
            if ($oldItemId === $itemId) {
                $diff = $quantityInp - $oldQuantity;

                $stmt = $conn->prepare("SELECT stock FROM items WHERE item_id=?");
                $stmt->bind_param("s", $itemId);
                $stmt->execute();
                $stmt->bind_result($currentStock);
                $stmt->fetch();
                $stmt->close();

                if ($currentStock + $diff < 0) throw new Exception("Stock cannot be negative");

                $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE item_id=?");
                $stmt->bind_param("is", $diff, $itemId);
                $stmt->execute();
                $stmt->close();
            } else {
                // Revert old stock
                $stmt = $conn->prepare("UPDATE items SET stock = stock - ? WHERE item_id=?");
                $stmt->bind_param("is", $oldQuantity, $oldItemId);
                $stmt->execute();
                $stmt->close();

                // Add new stock
                $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE item_id=?");
                $stmt->bind_param("is", $quantityInp, $itemId);
                $stmt->execute();
                $stmt->close();
            }

            // Update purchase
            $stmt = $conn->prepare("UPDATE purchase SET item_id=?, quantity=?, purchase_price=?, company_id=?, timestamp=NOW() WHERE purchase_id=?");
            $stmt->bind_param("sidss", $itemId, $quantityInp, $priceInp, $companyId, $purchase_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo "<script>
                    alert('Sale updated successfully!');
                    window.location.href='purchase.php';
                </script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to update purchase: " . $e->getMessage();
        }
    }
}

// Functions remain the same
function checkExist($conn, $itemIdFixedSQL, $companyIdFixedSQL, $itemName, $companyName, &$itemId=null, &$companyId=null) {
    $stmt = $conn->prepare($itemIdFixedSQL);
    $stmt->bind_param("s", $itemName);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) return false;
    $stmt->bind_result($id); $stmt->fetch(); $itemId = $id; $stmt->close();

    $stmt = $conn->prepare($companyIdFixedSQL);
    $stmt->bind_param("s", $companyName);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) return false;
    $stmt->bind_result($id); $stmt->fetch(); $companyId = $id; $stmt->close();

    return true;
}

function autofillField($conn, $likeSQL, $inputValue) {
    $stmt = $conn->prepare($likeSQL);
    $like = '%' . $inputValue . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) return $inputValue;
    $stmt->bind_result($result); $stmt->fetch(); $stmt->close();
    return $result;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Purchase</title>
    <link rel="stylesheet" href="style.css?v=999">
    <style>
        .warning { color: red; font-size: 0.9em; margin-top: 2px; }
        .msg-success { color: green; margin-bottom: 10px; }
        .msg-error { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="edit-box">
    <h2>Edit Purchase</h2>

    <?php if ($error) echo "<div class='msg-error'>$error</div>"; ?>
    <?php if ($success) echo "<div class='msg-success'>$success</div>"; ?>

    <form method="post">
        Purchase ID:<br>
        <input type="text" value="<?php echo htmlspecialchars($purchase_id); ?>" readonly><br><br>

        <div class="input-container">
            Item Name:<br>
            <input type="text" name="item_name" value="<?php echo htmlspecialchars($newItemName); ?>" required>
            <div class="warning"><?php echo $autofillItemWarning; ?></div>
        </div><br>

        Quantity:<br>
        <input type="number" name="quantity" value="<?php echo $newQuantity; ?>" min="0" required><br><br>

        Purchase Price:<br>
        <input type="number" name="purchase_price" value="<?php echo $newPrice; ?>" min="0" required><br><br>

        <div class="input-container">
            Company Name:<br>
            <input type="text" name="company_name" value="<?php echo htmlspecialchars($newCompany); ?>" required>
            <div class="warning"><?php echo $autofillCompanyWarning; ?></div>
        </div><br>

        <div class="button-group">
            <input type="submit" value="Update">
            <input type="button" value="Cancel" class="cancel-btn" onclick="window.location.href='purchase.php'">
        </div>
    </form>
</div>
</body>
</html>

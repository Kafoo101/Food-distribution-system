<?php
require "connect.php";
include "navbar.php";

// SQL statements
$companyIdFixedSQL = "SELECT company_id FROM company WHERE company_name = ? AND operating = 1";
$companyNameSQL    = "SELECT company_name FROM company WHERE company_name LIKE ? AND operating = 1 LIMIT 1";
$itemIdFixedSQL    = "SELECT item_id FROM items WHERE item_name = ?";
$itemNameSQL       = "SELECT item_name FROM items WHERE item_name LIKE ? LIMIT 1";
$saleItemStockSQL  = "SELECT stock FROM items WHERE item_id = ? AND onsale = 1";
$salesIdSQL        = "SELECT sales_id FROM sales WHERE 1 ORDER BY sales_id DESC LIMIT 1";

// Session check
if (!isset($_SESSION['email'])) {
    echo "<script>
            let proceed = confirm('You are not logged in. Click OK to go to Login, or Cancel to go back to Sales page.');
            if (proceed) { window.location.href='login.php?mode=login'; } 
            else { window.location.href='sell.php'; }
          </script>";
    exit();
}

// Check sales ID
if (!isset($_GET['id'])) {
    echo "<script>
            alert('No sale selected.');
            window.location.href='sell.php';
          </script>";
    exit();
}

$sales_id = $_GET['id'];
$error = "";
$success = "";
$autofillItemWarning = "";
$autofillCompanyWarning = "";

// Fetch current sale info
$stmt = $conn->prepare("
    SELECT i.item_id, i.item_name, s.quantity, s.sales_price, c.company_id, c.company_name
    FROM sales s
    JOIN items i ON s.item_id = i.item_id
    JOIN company c ON s.company_id = c.company_id
    WHERE s.sales_id = ?
");
$stmt->bind_param("s", $sales_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    header("Location: sell.php");
    exit();
}
$stmt->bind_result($item_id, $item_name, $quantity, $sales_price, $company_id, $company_name);
$stmt->fetch();
$stmt->close();

// Initialize form values
$newItemName   = $item_name;
$newQuantity   = $quantity;
$newPrice      = $sales_price;
$newCompany    = $company_name;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldItemId   = $item_id;
    $oldQuantity = $quantity;

    $itemInput   = trim($_POST['item_name']);
    $quantityInp = intval($_POST['quantity']);
    $priceInp    = floatval($_POST['sales_price']);
    $companyInp  = trim($_POST['company_name']);

    $errors = [];
    $itemId = null;
    $companyId = null;

    if ($itemInput === "" || $companyInp === "") {
        $errors[] = "Item Name and Company Name cannot be empty.";
    }
    if ($quantityInp < 0 || $priceInp < 0) {
        $errors[] = "Quantity and Sales Price must be 0 or greater.";
    }

    // Check existence
    if (!checkExist($conn, $itemIdFixedSQL, $companyIdFixedSQL, $itemInput, $companyInp, $itemId, $companyId)) {
        $correctItem    = autofillField($conn, $itemNameSQL, $itemInput);
        $correctCompany = autofillField($conn, $companyNameSQL, $companyInp);

        if (strcasecmp($correctItem, $itemInput) !== 0) $autofillItemWarning = "Autofilled item name from DB!";
        if (strcasecmp($correctCompany, $companyInp) !== 0) $autofillCompanyWarning = "Autofilled company name from DB!";

        $itemInput = $correctItem;
        $companyInp = $correctCompany;

        $errors[] = "Item or Company not found. Autofilled suggestions.";
    }

    $newItemName = $itemInput;
    $newCompany  = $companyInp;
    $newQuantity = $quantityInp;
    $newPrice    = $priceInp;

    if (empty($errors) && $itemId && $companyId) {
        $conn->begin_transaction();
        try {
            // Check stock
            $stmt = $conn->prepare($saleItemStockSQL);
            $stmt->bind_param("s", $itemId);
            $stmt->execute();
            $stmt->bind_result($currentStock);
            $stmt->fetch();
            $stmt->close();

            if ($currentStock < $quantityInp) throw new Exception("Not enough stock. Current stock: $currentStock");

            // Update stock
            if ($oldItemId === $itemId) {
                $diff = $quantityInp - $oldQuantity;

                $stmt = $conn->prepare("SELECT stock FROM items WHERE item_id=?");
                $stmt->bind_param("s", $itemId);
                $stmt->execute();
                $stmt->bind_result($stockCheck);
                $stmt->fetch();
                $stmt->close();

                if ($stockCheck - $diff < 0) throw new Exception("Stock cannot be negative");

                $stmt = $conn->prepare("UPDATE items SET stock = stock - ? WHERE item_id=?");
                $stmt->bind_param("is", $diff, $itemId);
                $stmt->execute();
                $stmt->close();
            } else {
                // Revert old stock
                $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE item_id=?");
                $stmt->bind_param("is", $oldQuantity, $oldItemId);
                $stmt->execute();
                $stmt->close();

                // Subtract new stock
                $stmt = $conn->prepare("UPDATE items SET stock = stock - ? WHERE item_id=?");
                $stmt->bind_param("is", $quantityInp, $itemId);
                $stmt->execute();
                $stmt->close();
            }

            // Update sale
            $stmt = $conn->prepare("UPDATE sales SET item_id=?, quantity=?, sales_price=?, company_id=?, timestamp=NOW() WHERE sales_id=?");
            $stmt->bind_param("sidss", $itemId, $quantityInp, $priceInp, $companyId, $sales_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo "<script>
                    alert('Sale updated successfully!');
                    window.location.href='sell.php';
                </script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to update sale: " . $e->getMessage();
        }
    }
}

// Functions
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
    <title>Edit Sale</title>
    <link rel="stylesheet" href="style.css?v=999">
    <style>
        .warning { color: red; font-size: 0.9em; margin-top: 2px; }
        .msg-error { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="edit-box">
    <h2>Edit Sale</h2>

    <?php if ($error) echo "<div class='msg-error'>$error</div>"; ?>

    <form method="post">
        Sales ID:<br>
        <input type="text" value="<?php echo htmlspecialchars($sales_id); ?>" readonly><br><br>

        <div class="input-container">
            Item Name:<br>
            <input type="text" name="item_name" value="<?php echo htmlspecialchars($newItemName); ?>" required>
            <div class="warning"><?php echo $autofillItemWarning; ?></div>
        </div><br>

        Quantity:<br>
        <input type="number" name="quantity" value="<?php echo $newQuantity; ?>" min="0" required><br><br>

        Sales Price:<br>
        <input type="number" name="sales_price" value="<?php echo $newPrice; ?>" min="0" required><br><br>

        <div class="input-container">
            Company Name:<br>
            <input type="text" name="company_name" value="<?php echo htmlspecialchars($newCompany); ?>" required>
            <div class="warning"><?php echo $autofillCompanyWarning; ?></div>
        </div><br>

        <div class="button-group">
            <input type="submit" value="Update">
            <input type="button" value="Cancel" class="cancel-btn" onclick="window.location.href='sell.php'">
        </div>
    </form>
</div>
</body>
</html>

<?php
    require "connect.php";
    //all sql used
    $itemNameFixedSQL = "SELECT item_name FROM items WHERE item_name = ? AND onsale = 1";
    $itemNameSQL = "SELECT item_name FROM items WHERE item_name LIKE ? AND onsale = 1 LIMIT 1";
    $itemIdSQL = "SELECT item_id FROM items WHERE onsale = 1 ORDER BY item_id DESC LIMIT 1";
    $itemFilterSQL = "SELECT * FROM items WHERE item_id LIKE ? AND item_name LIKE ? AND onsale = 1";
    $itemInsertSQL = "INSERT INTO items(item_id, item_name, stock, onsale) VALUES(?, ?, ?, ?)";
    
    //page special actions
    if (isset($_GET['toggleAdd'])) {
        $showAddRow = $_GET['toggleAdd'] === '1';
    } else {
        $showAddRow = isset($_GET['showAdd']) && $_GET['showAdd'] === '1';
    }

    if (isset($_GET['addAction']) && $_GET['addAction'] === '1') {
        $showAddRow = true; 
        $itemId     = $_GET['newItemId'] ?? '';
        $itemName   = trim($_GET['newItemName'] ?? '');
        $stock      = $_GET['newStock'] ?? '';
        $errors     = [];

        if (!is_numeric($stock) || $stock <= 0)  $errors[] = "Quantity must be a positive number.";

        // ---- FINAL DECISION ----
        if (empty($errors)) {
            //insert items
            $stmt = $conn->prepare($itemInsertSQL);
            $stmt->bind_param("ssii", $itemId, $itemName, $stock, 1);
            $stmt->execute();

            // reset value
            $_GET['newItemId'] = '';
            $_GET['newItemName'] = '';
            $_GET['newStock'] = '';
        }
    }
    
    //html values
    $currentPage    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $nextItemId     = getNextId($conn, $itemIdSQL);
    $newItemId      = $_GET['newItemId'] ?? '';
    $newItemName    = $_GET['newItemName'] ?? '';
    $newStock       = $_GET['newStock'] ?? '';

    function refreshTable($conn, $itemId, $itemName, $itemFilterSQL)
    {
        $stmt = $conn->prepare($itemFilterSQL);

        $likeItemId     = '%' . $itemId . '%';
        $likeItemName   = '%' . $itemName . '%';

        $stmt->bind_param("ss", $likeItemId, $likeItemName);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($item_id, $item_name, $stock, $onsale);

        $items = [];
        while ($stmt->fetch()) {
            $items[] = [$item_id, $item_name, $stock];
        }

        return $items;
    }

    function getNextId($conn, $itemIdSQL)
    {
        $stmt = $conn->prepare($itemIdSQL);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            return 'I-0001';
        }

        $stmt->bind_result($item_id);
        $stmt->fetch();$number = intval(substr($item_id, 2));
        $number++;
        return 'P-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    if (!empty($errors)) {
        echo '<div style="color:red;">' . implode('<br>', $errors) . '</div>';
    }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<h2>Inventory Stocks</h2>

<!-- Filter Form -->
<form method='GET'>
    <table>
        <tr>
            <td id="filter-text" width="100px">Filter By:</td>
            <td id="filter-text" width="120px">Item ID:</td>
            <td id="filter-text" width="120px">Item Name:</td>
            <td id="filter-text" colspan="1"></td>
        </tr>
         <tr>
            <?php
                echo "
                <td id='filter-text'></td>
                <td id='filter-text'><input type='text' name='itemIdFilter' value='" . htmlspecialchars($_GET['itemIdFilter'] ?? '') . "'></td>
                <td id='filter-text'><input type='text' name='itemNameFilter' value='" . htmlspecialchars($_GET['itemNameFilter'] ?? '') . "'></td>
                <td id='filter-text' colspan='1'></td>
                ";
            ?>
        </tr>
        <tr height="20px"></tr>
        <tr>
            <td colspan="7" style="text-align: right; padding-top: 5px;">
                <input type="hidden" name="showAdd" value="<?php echo $showAddRow ? '1' : '0'; ?>">
                <?php
                    if ($showAddRow) {
                        echo "<button type='submit' name='toggleAdd' value='0'>Hide</button>";
                    } else {
                        echo "<button type='submit' name='toggleAdd' value='1'>Add Item</button>";
                    }
                ?>
                <button type="submit">Query</button>
            </td>
        </tr>
        <?php if ($showAddRow): ?>
        <tr>
            <td></td>
            <td id="filter-text">Item ID:</td>
            <td id="filter-text">Item Name:</td>
            <td id="filter-text">Stock:</td>
        </tr>
        <tr>
            <td></td>
            <td><input type="text" name="newItemId" value="<?php echo htmlspecialchars($nextItemId); ?>" readonly style="background-color: #e9e9e9;"></td>
            <td><input type="text" name="newItemName" value="<?php echo htmlspecialchars($newItemName); ?>"></td>
            <td><input type="text" name="newStock" value="<?php echo htmlspecialchars($newStock); ?>"></td>
            <td><button type="submit" name="addAction" value='1'>+</button>
        </tr>
        <?php endif; ?>
        <tr>
            <th style="border: none; background-color: white;"></th>
            <th>Item ID</th>
            <th>Item Name</th>
            <th width="70px">Stock</th>
        </tr>
        <?php
            $items = refreshTable($conn, $_GET['itemIdFilter'] ?? '', $_GET['itemNameFilter'] ?? '', $itemFilterSQL);
            $color1 = "#F5F5F5";
            $color2 = "#def2e8ff";

            $entriesPerPage = 10;
            $totalEntries = count($items);
            $totalPages = ceil($totalEntries / $entriesPerPage);
            $startIndex = ($currentPage - 1) * $entriesPerPage;
            $itemsPage = array_slice($items, $startIndex, $entriesPerPage);

            if (!empty($itemsPage)) {
                $rowIndex = 0;
                foreach ($itemsPage as $row) {
                    $bgColor = ($rowIndex % 2 === 0) ? $color1 : $color2;
                    $itemId = $row[0];

                    echo "<tr><td style='background-color: \"white\"'></td>";
                    
                    foreach ($row as $index => $cell) {
                        echo "<td style='background-color: $bgColor;'>$cell</td>";
                    }
                    echo "
                        <td><a href='itemEdit.php?id=$itemId'>edit</a></td>
                    ";
                    echo "</tr>";
                    $rowIndex++;
                }
            } else {
                echo "<tr><td colspan='6' style='text-align:center;'>No matching records found</td></tr>";
            }

            if ($totalPages > 1) {
                echo "<tr><td colspan='7' style='text-align:center; padding-top:10px;'>";
                for ($i = 1; $i <= $totalPages; $i++) {
                    $query = $_GET;
                    $query['page'] = $i;
                    $queryString = http_build_query($query);
                    $style = ($i == $currentPage) ? "font-weight:bold; text-decoration:underline;" : "";
                    echo "<a href='?" . htmlspecialchars($queryString) . "' style='margin:0 5px; $style'>$i</a>";
                }
                echo "</td></tr>";
            }
        ?>
    </table>
</form>
</body>
</html>
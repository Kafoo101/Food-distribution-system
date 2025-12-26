<?php
include 'navbar.php';
    require "connect.php";
    //all sql used
    $companyLocationSQL = "SELECT DISTINCT city FROM company WHERE operating = 1 ORDER BY city ASC";
    $companyIdCitySQL = "SELECT * FROM company WHERE city LIKE ? AND operating = 1";
    $companyIdFixedSQL = "SELECT company_id FROM company WHERE company_name = ? AND operating = 1";
    $companyNameSQL = "SELECT company_name FROM company WHERE company_name LIKE ? AND operating = 1 LIMIT 1";
    $itemIdFixedSQL = "SELECT item_id FROM items WHERE item_name = ?";
    $itemNameSQL = "SELECT item_name FROM items WHERE item_name LIKE ? LIMIT 1";
    $purchaseIdSQL = "SELECT purchase_id FROM purchase WHERE 1 ORDER BY purchase_id DESC LIMIT 1";
    $purchaseFilterSQL = "SELECT * FROM purchase WHERE purchase_id LIKE ? AND timestamp LIKE ? AND item_id LIKE ? AND company_id = ?";
    $tableRefreshSQL = "SELECT p.purchase_id, p.timestamp, i.item_name, p.quantity, p.purchase_price, c.company_name FROM purchase p JOIN items i ON p.item_id = i.item_id JOIN company c ON p.company_id = c.company_id WHERE p.purchase_id LIKE ? AND p.timestamp LIKE ? AND i.item_name LIKE ? AND c.city LIKE ? AND c.operating = 1 ORDER BY p.purchase_id DESC";
    $purchaseInsertSQL = "INSERT INTO purchase(purchase_id, item_id, quantity, purchase_price, company_id, timestamp) VALUES (?,?,?,?,?,NOW())";
    $itemUpdateSQL = "UPDATE items SET stock = stock + ? WHERE item_id = ?";
    
    //page special actions
    if (isset($_GET['toggleAdd'])) {
        $showAddRow = $_GET['toggleAdd'] === '1';
    } else {
        $showAddRow = isset($_GET['showAdd']) && $_GET['showAdd'] === '1';
    }

    if (isset($_GET['addAction']) && $_GET['addAction'] === '1') {
        $showAddRow = true; 
        $purchaseId = $_GET['newPurchaseId'] ?? '';
        $itemInput  = trim($_GET['newItem'] ?? '');
        $companyInp = trim($_GET['newCompany'] ?? '');
        $quantity   = $_GET['newQuantity'] ?? '';
        $price      = $_GET['newPrice'] ?? '';

        $itemId = null;
        $companyId = null;
        $errors = [];

        if (!is_numeric($quantity) || $quantity <= 0)  $errors[] = "Quantity must be a positive number.";
        if (!is_numeric($price) || $price <= 0) $errors[] = "Price must be a positive number.";

        if(!checkExist($conn, $itemIdFixedSQL, $companyIdFixedSQL, $itemInput, $companyInp, $itemId, $companyId))
        {
            //autofill box
            $_GET['newItem'] = autofillField($conn, $itemNameSQL, $itemInput);
            $_GET['newCompany'] = autofillField($conn, $companyNameSQL, $companyInp);
            $errors[] = "Item or Company not found. Autofilled suggestions.";
        }

        // ---- FINAL DECISION ----
        if (empty($errors)) {
            //insert purchase log
            $stmt = $conn->prepare($purchaseInsertSQL);
            $stmt->bind_param("ssids", $purchaseId, $itemId, $quantity, $price, $companyId);
            $stmt->execute();
            
            //update item stock
            $stmt = $conn->prepare($itemUpdateSQL);
            $stmt->bind_param("is", $quantity, $itemId);
            $stmt->execute();

            // reset value
            $_GET['newItem'] = '';
            $_GET['newCompany'] = '';
            $_GET['newQuantity'] = '';
            $_GET['newPrice'] = '';
        }
        
    }
    
    //html values
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $nextPurchaseId = getNextId($conn, $purchaseIdSQL);
    $newItem        = $_GET['newItem'] ?? '';
    $newQuantity    = $_GET['newQuantity'] ?? '';
    $newPrice       = $_GET['newPrice'] ?? '';
    $newCompany     = $_GET['newCompany'] ?? '';
    
    //functions
    function initialPrep($conn, $companyIdCitySQL, $companyLocationSQL, $purchaseFilterSQL)
    {
        // Prepare company IDs
        $stmt = $conn->prepare($companyIdCitySQL);
        $like = '%';
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($company_id, $company_name, $city, $address, $phone, $operating);

        //get company IDs
        $companyIds = [];
        while ($stmt->fetch()) {
            $companyIds[$company_id] = true;
        }
        $companyIds = array_keys($companyIds);

        //get months
        $months = [];
        foreach ($companyIds as $companyId) {
            $stmt = $conn->prepare($purchaseFilterSQL);
            $stmt->bind_param("ssss", $like, $like, $like, $companyId);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($purchase_id, $timestamp, $item_id, $quantity, $purchase_price, $company_id);

            while ($stmt->fetch()) {
                $month = date("Y-m", strtotime($timestamp));
                $months[$month] = true;
            }
        }
        $months = array_keys($months);
        rsort($months);

        // Prepare company locations
        $stmt = $conn->prepare($companyLocationSQL);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($city);

        //get company locations
        $locations = [];
        while ($stmt->fetch()) {
            $locations[$city] = true;
        }
        $locations = array_keys($locations);
        return [$locations, $months];
    }

    function refreshTable($conn, $purchaseId, $timePeriod, $itemName, $location, $purchaseFilterSQL)
    {
        $stmt = $conn->prepare($purchaseFilterSQL);

        $likePurchaseId = '%' . $purchaseId . '%';
        $likeTime       = '%' . $timePeriod . '%';
        $likeItemName   = '%' . $itemName . '%';
        $likeLocation   = '%' . $location . '%';

        $stmt->bind_param( "ssss", $likePurchaseId, $likeTime, $likeItemName, $likeLocation );
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result( $purchase_id, $timestamp, $item_name, $quantity, $purchase_price, $company_name );

        $purchases = [];
        while ($stmt->fetch()) {
            $purchases[] = [ $purchase_id, $timestamp, $item_name, $quantity, $purchase_price, $company_name ];
        }

        return $purchases;
    }

    function getNextId($conn, $purchaseIdSQL)
    {
        $stmt = $conn->prepare($purchaseIdSQL);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            return 'P-0001';
        }

        $stmt->bind_result($purchase_id);
        $stmt->fetch();$number = intval(substr($purchase_id, 2));
        $number++;
        return 'P-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    function checkExist($conn, $itemIdFixedSQL, $companyIdFixedSQL, $itemName, $companyName, &$itemId=null, &$companyId=null) {
        // Try item match
        $stmt = $conn->prepare($itemIdFixedSQL);
        $stmt->bind_param("s", $itemName);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return false;
        }
        else{
            $stmt->bind_result($id);
            $stmt->fetch();
            $itemId = $id;
        }

        // Try company match
        $stmt = $conn->prepare($companyIdFixedSQL);
        $stmt->bind_param("s", $companyName);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return false;
        }
        else{
            $stmt->bind_result($id);
            $stmt->fetch();
            $companyId = $id;
        }

        return true;
    }

    function autofillField($conn, $likeSQL, $inputValue) {
        $stmt = $conn->prepare($likeSQL);
        $like = '%' . $inputValue . '%';

        $stmt->bind_param("s", $like);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) return $inputValue;

        $stmt->bind_result($result);
        $stmt->fetch();

        return $result;
    }

    list($locations, $months) = initialPrep($conn, $companyIdCitySQL, $companyLocationSQL, $purchaseFilterSQL);
    if (!empty($errors)) {
        echo '<div style="color:red;">' . implode('<br>', $errors) . '</div>';
    }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Records</title>
    <style>
        table { 
            border-collapse: collapse;
        }
        #filter-text{ 
            padding: 8px 8px 0px 8px; 
            font-size: 18px; 
        }
        th { 
            background-color: #a4c4a1ff; 
            text-align: left; 
            padding: 8px; 
        }
        td { 
            padding: 8px; 
        }
        input[type='text'] { 
            width: 100%; 
            box-sizing: border-box; 
        }
        .hidden-row {
            display: none;
        }
        .add-btn {
            font-size: 18px;
            width: 26px;
            height: 26px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h2>Purchase Records</h2>

<!-- Filter Form -->
<form method='GET'>
    <table>
        <tr>
            <td id="filter-text" width="100px">Filter By:</td>
            <td id="filter-text" width="120px">Purchase ID:</td>
            <td id="filter-text" width="150px">Time Period:</td>
            <td id="filter-text" width="120px">Item Name:</td>
            <td id="filter-text" colspan="2"></td>
            <td id="filter-text" width="240px">Company Location:</td>
        </tr>
         <tr>
            <?php
                echo "
                <td id='filter-text'></td>
                <td id='filter-text'><input type='text' name='purchaseFilter' value='" . htmlspecialchars($_GET['purchaseFilter'] ?? '') . "'></td>
                <td id='filter-text'>
                    <select style='width: 95px;' name='timeFilter'>
                        <option value='' hidden " . (empty($_GET['timeFilter']) ? "selected" : "") . "></option>
                        <option value='' " . ((isset($_GET['timeFilter']) && $_GET['timeFilter'] === '') ? "selected" : "") . ">All</option>";
                        foreach ($months as $month) {
                            echo "<option value='" . htmlspecialchars($month) . "' " . ((isset($_GET['timeFilter']) && $_GET['timeFilter'] === $month) ? "selected" : "") . ">" . htmlspecialchars($month) . "</option>";
                        }
                echo "  </select>
                </td>
                <td id='filter-text'><input type='text' name='itemFilter' value='" . htmlspecialchars($_GET['itemFilter'] ?? '') . "'></td>
                <td id='filter-text' colspan='2'></td>
                <td id='filter-text'>
                    <select style='width: 95px;' name='locationFilter'>
                        <option value='' hidden " . (empty($_GET['locationFilter']) ? "selected" : "") . "></option>
                        <option value='' " . ((isset($_GET['locationFilter']) && $_GET['locationFilter'] === '') ? "selected" : "") . ">All</option>";
                        foreach ($locations as $location) {
                            echo "<option value='" . htmlspecialchars($location) . "' " . ((isset($_GET['locationFilter']) && $_GET['locationFilter'] === $location) ? "selected" : "") . ">" . htmlspecialchars($location) . "</option>";
                        }
                echo "  </select>
                </td>";
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
                        echo "<button type='submit' name='toggleAdd' value='1'>Add Purchase</button>";
                    }
                ?>
                <button type="submit">Query</button>
            </td>
        </tr>
        <?php if ($showAddRow): ?>
        <tr>
            <td></td>
            <td id="filter-text">Purchase ID:</td>
            <td></td>
            <td id="filter-text">Item Name:</td>
            <td id="filter-text">Quantity:</td>
            <td id="filter-text">Purchase Price:</td>
            <td id="filter-text">Company Name:</td>
        </tr>
        <tr>
            <td></td>
            <td><input type="text" name="newPurchaseId" value="<?php echo htmlspecialchars($nextPurchaseId); ?>" readonly style="background-color: #e9e9e9;"></td>
            <td></td>
            <td><input type="text" name="newItem" value="<?php echo htmlspecialchars($newItem); ?>"></td>
            <td><input type="text" name="newQuantity" value="<?php echo htmlspecialchars($newQuantity); ?>"></td>
            <td><input type="text" name="newPrice" value="<?php echo htmlspecialchars($newPrice); ?>"></td>
            <td><input type="text" name="newCompany" value="<?php echo htmlspecialchars($newCompany); ?>"></td>
            <td><button type="submit" name="addAction" value='1'>+</button>
        </tr>
        <?php endif; ?>
        <tr>
            <th style="border: none; background-color: white;"></th>
            <th>Purchase ID</th>
            <th>Timestamp</th>
            <th>Item Name</th>
            <th width="70px">Quantity</th>
            <th width="130px">Purchase Price</th>
            <th>Company Name</th>
        </tr>
        <?php
            $purchases = refreshTable($conn, $_GET['purchaseFilter'] ?? '', $_GET['timeFilter'] ?? '', $_GET['itemFilter'] ?? '', $_GET['locationFilter'] ?? '', $tableRefreshSQL);
            $color1 = "#F5F5F5";
            $color2 = "#def2e8ff";

            $entriesPerPage = 10;
            $totalEntries = count($purchases);
            $totalPages = ceil($totalEntries / $entriesPerPage);
            $startIndex = ($currentPage - 1) * $entriesPerPage;
            $purchasesPage = array_slice($purchases, $startIndex, $entriesPerPage);

            if (!empty($purchasesPage)) {
                $rowIndex = 0;
                foreach ($purchasesPage as $row) {
                    $bgColor = ($rowIndex % 2 === 0) ? $color1 : $color2;
                    $purchaseId = $row[0];

                    echo "<tr><td style='background-color: \"white\"'></td>";
                    
                    foreach ($row as $index => $cell) {
                        $style = ($index === 3 || $index === 4) ? " text-align:center;" : "";
                        if ($index === 4) {
                            $cell = htmlspecialchars($cell) . " \$NTD";
                        } else {
                            $cell = htmlspecialchars($cell);
                        }

                        echo "<td style='background-color: $bgColor; $style'>$cell</td>";
                    }
                    echo "
                        <td><a href='purchaseEdit.php?id=$purchaseId'>edit</a></td>
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
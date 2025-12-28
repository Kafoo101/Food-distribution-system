<?php
require "connect.php";

// SQL statements
$companyFilterSQL = "SELECT company_id, company_name, city, address, phone 
                     FROM company 
                     WHERE company_id LIKE ? AND company_name LIKE ? AND operating = 1";
$companyInsertSQL = "INSERT INTO company(company_id, company_name, city, address, phone, operating) VALUES(?, ?, ?, ?, ?, ?)";
$companyIdSQL     = "SELECT company_id FROM company ORDER BY company_id DESC LIMIT 1";

// Page actions
if (isset($_GET['toggleAdd'])) {
    $showAddRow = $_GET['toggleAdd'] === '1';
} else {
    $showAddRow = isset($_GET['showAdd']) && $_GET['showAdd'] === '1';
}

if (isset($_GET['addAction']) && $_GET['addAction'] === '1') {
    $showAddRow = true; 
    $companyId   = $_GET['newCompanyId'] ?? '';
    $companyName = trim($_GET['newCompanyName'] ?? '');
    $city        = trim($_GET['newCity'] ?? '');
    $address     = trim($_GET['newAddress'] ?? '');
    $phone       = trim($_GET['newPhone'] ?? '');
    $errors      = [];

    // Basic validation
    if ($companyName === '' || $city === '' || $address === '' || $phone === '') {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare($companyInsertSQL);
        $operating = 1; // still insert with operating = 1
        $stmt->bind_param("sssssi", $companyId, $companyName, $city, $address, $phone, $operating);
        $stmt->execute();

        $_GET['newCompanyId'] = '';
        $_GET['newCompanyName'] = '';
        $_GET['newCity'] = '';
        $_GET['newAddress'] = '';
        $_GET['newPhone'] = '';
    }
}

// HTML values
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$nextCompanyId = getNextId($conn, $companyIdSQL);
$newCompanyId   = $_GET['newCompanyId'] ?? '';
$newCompanyName = $_GET['newCompanyName'] ?? '';
$newCity        = $_GET['newCity'] ?? '';
$newAddress     = $_GET['newAddress'] ?? '';
$newPhone       = $_GET['newPhone'] ?? '';

function refreshTable($conn, $companyIdFilter, $companyNameFilter, $companyFilterSQL)
{
    $stmt = $conn->prepare($companyFilterSQL);
    $likeCompanyId   = '%' . $companyIdFilter . '%';
    $likeCompanyName = '%' . $companyNameFilter . '%';
    $stmt->bind_param("ss", $likeCompanyId, $likeCompanyName);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($company_id, $company_name, $city, $address, $phone);

    $companies = [];
    while ($stmt->fetch()) {
        $companies[] = [$company_id, $company_name, $city, $address, $phone];
    }
    return $companies;
}

function getNextId($conn, $companyIdSQL)
{
    $stmt = $conn->prepare($companyIdSQL);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        return 'C-0001';
    }

    $stmt->bind_result($company_id);
    $stmt->fetch();
    $number = intval(substr($company_id, 2)) + 1;
    return 'C-' . str_pad($number, 4, '0', STR_PAD_LEFT);
}

if (!empty($errors)) {
    echo '<div style="color:red;">' . implode('<br>', $errors) . '</div>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Company Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<h2>Companies</h2>

<form method='GET'>
    <table>
        <tr>
            <td width="100px">Filter By:</td>
            <td width="120px">Company ID:</td>
            <td width="120px">Company Name:</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td><input type='text' name='companyIdFilter' value='<?php echo htmlspecialchars($_GET['companyIdFilter'] ?? ''); ?>'></td>
            <td><input type='text' name='companyNameFilter' value='<?php echo htmlspecialchars($_GET['companyNameFilter'] ?? ''); ?>'></td>
            <td></td>
        </tr>
        <tr height="20px"></tr>
        <tr>
            <td colspan="7" style="text-align: right; padding-top: 5px;">
                <input type="hidden" name="showAdd" value="<?php echo $showAddRow ? '1' : '0'; ?>">
                <?php
                if ($showAddRow) {
                    echo "<button type='submit' name='toggleAdd' value='0'>Hide</button>";
                } else {
                    echo "<button type='submit' name='toggleAdd' value='1'>Add Company</button>";
                }
                ?>
                <button type="submit">Query</button>
            </td>
        </tr>

        <?php if ($showAddRow): ?>
        <tr>
            <td></td>
            <td>Company ID:</td>
            <td>Company Name:</td>
            <td>City</td>
            <td>Address</td>
            <td>Phone</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="text" name="newCompanyId" value="<?php echo htmlspecialchars($nextCompanyId); ?>" readonly style="background-color:#e9e9e9;"></td>
            <td><input type="text" name="newCompanyName" value="<?php echo htmlspecialchars($newCompanyName); ?>"></td>
            <td><input type="text" name="newCity" value="<?php echo htmlspecialchars($newCity); ?>"></td>
            <td><input type="text" name="newAddress" value="<?php echo htmlspecialchars($newAddress); ?>"></td>
            <td><input type="text" name="newPhone" value="<?php echo htmlspecialchars($newPhone); ?>"></td>
            <td><button type="submit" name="addAction" value='1'>+</button></td>
        </tr>
        <?php endif; ?>

        <tr>
            <td></td>
            <th>Company ID</th>
            <th>Company Name</th>
            <th>City</th>
            <th>Address</th>
            <th>Phone</th>
        </tr>

        <?php
        $companies = refreshTable($conn, $_GET['companyIdFilter'] ?? '', $_GET['companyNameFilter'] ?? '', $companyFilterSQL);
        $color1 = "#F5F5F5";
        $color2 = "#def2e8ff";

        $entriesPerPage = 10;
        $totalEntries = count($companies);
        $totalPages = ceil($totalEntries / $entriesPerPage);
        $startIndex = ($currentPage - 1) * $entriesPerPage;
        $companiesPage = array_slice($companies, $startIndex, $entriesPerPage);

        if (!empty($companiesPage)) {
            $rowIndex = 0;
            foreach ($companiesPage as $row) {
                $bgColor = ($rowIndex % 2 === 0) ? $color1 : $color2;
                $companyId = $row[0];

                echo "<tr>";
                echo "<td style='background-color: white;'></td>";
                foreach ($row as $cell) {
                    echo "<td style='background-color: $bgColor;'>$cell</td>";
                }
                echo "<td><a href='companyEdit.php?id=$companyId'>edit</a></td>";
                echo "</tr>";
                $rowIndex++;
            }
        } else {
            echo "<tr><td colspan='7' style='text-align:center;'>No matching records found</td></tr>";
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

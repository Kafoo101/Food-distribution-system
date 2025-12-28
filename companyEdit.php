<?php
require "connect.php";
include "navbar.php";

// Session check
if (!isset($_SESSION['email'])) {
    echo "<script>
            let proceed = confirm('You are not logged in. Click OK to go to Login, or Cancel to go back to Company page.');
            if (proceed) { window.location.href='login.php?mode=login'; } 
            else { window.location.href='company.php'; }
          </script>";
    exit();
}

// Check company ID
if (!isset($_GET['id'])) {
    echo "<script>
            alert('No company selected.');
            window.location.href='company.php';
          </script>";
    exit();
}

$company_id = $_GET['id'];
$error = "";

// Fetch current company info
$stmt = $conn->prepare("SELECT company_id, company_name, city, address, phone FROM company WHERE company_id=?");
$stmt->bind_param("s", $company_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    header("Location: company.php");
    exit();
}
$stmt->bind_result($c_id, $c_name, $city, $address, $phone);
$stmt->fetch();
$stmt->close();

// Initialize form values
$newCompanyName = $c_name;
$newCity        = $city;
$newAddress     = $address;
$newPhone       = $phone;

// Fetch all other existing company names
$existingNames = [];
$result = $conn->query("SELECT company_name FROM company WHERE company_id != '$company_id'");
while ($row = $result->fetch_assoc()) {
    $existingNames[] = $row['company_name'];
}
$existingNamesJS = json_encode($existingNames);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyNameInput = trim($_POST['company_name']);
    $cityInput        = trim($_POST['city']);
    $addressInput     = trim($_POST['address']);
    $phoneInput       = trim($_POST['phone']);

    $errors = [];
    if ($companyNameInput === "" || $cityInput === "" || $addressInput === "" || $phoneInput === "") {
        $errors[] = "All fields are required.";
    }

    $newCompanyName = $companyNameInput;
    $newCity        = $cityInput;
    $newAddress     = $addressInput;
    $newPhone       = $phoneInput;

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE company SET company_name=?, city=?, address=?, phone=? WHERE company_id=?");
        $stmt->bind_param("sssss", $newCompanyName, $newCity, $newAddress, $newPhone, $company_id);
        $stmt->execute();
        $stmt->close();

        echo "<script>
                alert('Company updated successfully!');
                window.location.href='company.php';
              </script>";
        exit();
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Company</title>
    <link rel="stylesheet" href="style.css?v=999">
    <script>
        const existingNames = <?php echo $existingNamesJS; ?>;
        const oldName = "<?php echo addslashes($c_name); ?>";
        const oldCity = "<?php echo addslashes($city); ?>";
        const oldAddress = "<?php echo addslashes($address); ?>";
        const oldPhone = "<?php echo addslashes($phone); ?>";

        function confirmUpdate() {
            const newName = document.getElementsByName('company_name')[0].value.trim();
            const city = document.getElementsByName('city')[0].value.trim();
            const address = document.getElementsByName('address')[0].value.trim();
            const phone = document.getElementsByName('phone')[0].value.trim();

            // Check if new name exists
            if (existingNames.includes(newName)) {
                alert(`Sorry, '${newName}' already exist!`);
                return false; // stop form submission
            }

            let msg = "";
            const nameChanged = oldName !== newName;
            const otherChanged = oldCity !== city || oldAddress !== address || oldPhone !== phone;

            if (nameChanged && otherChanged) {
                msg = `Are you sure to change company identity of '${oldName}' to '${newName}'?`;
            } else if (nameChanged) {
                msg = `Are you sure to update the company '${oldName}' to '${newName}'?`;
            } else if (otherChanged) {
                msg = `Are you sure to update the city/address/phone of '${oldName}'?`;
            } else {
                // Nothing changed
                alert("No changes detected.");
                return false;
            }

            return confirm(msg);
        }
    </script>
</head>
<body>
<div class="edit-box">
    <h2>Edit Company</h2>

    <?php if ($error) echo "<div class='msg-error'>$error</div>"; ?>

    <form method="post" onsubmit="return confirmUpdate();">
        Company ID:<br>
        <input type="text" value="<?php echo htmlspecialchars($company_id); ?>" readonly><br><br>

        <div class="input-container">
            Company Name:<br>
            <input type="text" name="company_name" value="<?php echo htmlspecialchars($newCompanyName); ?>" required><br><br>
        </div>

        <div class="input-container">
            City:<br>
            <input type="text" name="city" value="<?php echo htmlspecialchars($newCity); ?>" required><br><br>
        </div>

        <div class="input-container">
            Address:<br>
            <input type="text" name="address" value="<?php echo htmlspecialchars($newAddress); ?>" required><br><br>
        </div>

        <div class="input-container">
            Phone:<br>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($newPhone); ?>" required><br><br>
        </div>

        <div class="button-group">
            <input type="submit" value="Update">
            <input type="button" value="Cancel" class="cancel-btn" onclick="window.location.href='company.php'">
        </div>
    </form>
</div>
</body>
</html>

<?php
include 'db_connection.php';

// get the highest cus_id and then increment by 1 then return
function getNextCustomerId(mysqli $mysqli): string {
    $sql = "SELECT CUS_ID 
            FROM CUSTOMER 
            WHERE CUS_ID LIKE 'CUS____' 
            ORDER BY CAST(SUBSTRING(CUS_ID, 4) AS UNSIGNED) DESC 
            LIMIT 1";
    $result = $mysqli->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        $lastNum = (int)substr($row['CUS_ID'], 3);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }
    return 'CUS' . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT); // CUSXXXX
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    // collect fields
    $first   = trim($_POST['first_name'] ?? '');
    $last    = trim($_POST['last_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $license = trim($_POST['license'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // check if email already exists in database
    $stmt = $mysqli->prepare("SELECT CUS_ID FROM CUSTOMER WHERE CUS_EMAIL = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $errors[] = "That email is already registered.";
    }
    $stmt->close();

    // check passwords match
    if ($pass !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // if valid, insert
    if (empty($errors)) {
        $cus_id = getNextCustomerId($mysqli);
        
        // Hash the password for security

        $stmt = $mysqli->prepare("INSERT INTO CUSTOMER 
            (CUS_ID, CUS_FIRST_NAME, CUS_LAST_NAME, CUS_EMAIL, CUS_ADDRESS, CUS_DRIVERS_LICENSE, CUS_PHONE_NUMBER, CUS_PASSWORD)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
        $stmt->bind_param("ssssssss", $cus_id, $first, $last, $email, $address, $license, $phone, $pass);

        if ($stmt->execute()) {
            echo "<script>alert('Sign up successful! Please log in.'); window.location='login.php';</script>";
            exit;
        } else {
            echo "<script>alert('Database Error: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Sign Up</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="box">
        <h2>Customer Sign Up</h2>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <ul style="margin:0; padding-left: 18px;">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="row">
                <label for="first_name">First name</label>
                <input type="text" name="first_name" maxlength="50" autocomplete="off" required>
            </div>
            <div class="row">
                <label for="last_name">Last name</label>
                <input type="text" name="last_name" maxlength=50 autocomplete="off" required>
            </div>
            <div class="row">
                <label for="email">Email</label>
                <input type="email" name="email" maxlength="100" autocomplete="off" required>
            </div>
            <div class="row">
                <label for="address">Address</label>
                <input type="text" name="address" maxlength="255" autocomplete="off" required>
            <div class="row">
                <label for="license">Driver's license</label>
                <input type="text" name="license" oninput="this.value = this.value.toUpperCase()" pattern="[A-Za-z]\d{2}-\d{2}-\d{6}" maxlength="13" title="Format: LDD-DD-DDDDDD (e.g., A12-34-123456)" autocomplete="off" required>
            </div>
            <div class="row">
                <label for="phone">Phone number (11 digits)</label>
                <input autocomplete="off" type="text" name="phone" maxlength="11" placeholder="09xxxxxxxxx" pattern="09\d{9}" title="Must be 11 digits and start with '09' (e.g., 09171234567)" required>
            </div>
            <div class="row">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" maxlength="255" required>
            </div>
            <div class="row">
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" type="password" name="confirm_password" required>
            </div>
            <button type="submit">Sign up</button>
        </form>

        <p style="margin-top:12px; text-align: center;">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</body>
</html>
<?php
session_start();
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // check if admin
    $sql_admin = "SELECT * FROM ADMIN WHERE ADMIN_EMAIL='$email' AND ADMIN_PASSWORD='$password'";
    $result_admin = $mysqli->query($sql_admin);

    if ($result_admin && $result_admin->num_rows == 1) {
        $_SESSION['role'] = 'admin';
        $_SESSION['email'] = $email;
        header("Location: admin/admin_view.php");
        exit();
    }

    // check if customer
    $sql_customer = "SELECT * FROM CUSTOMER WHERE CUS_EMAIL='$email' AND CUS_PASSWORD='$password'";
    $result_customer = $mysqli->query($sql_customer);

    if ($result_customer && $result_customer->num_rows == 1) {
        $_SESSION['role'] = 'customer';
        $_SESSION['email'] = $email;
        header("Location: customer/customer_view.php");
        exit();
    }

    // neither
    $error = "Invalid email or password!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post">
            <label>Email:</label><br>
            <input type="email" name="email" required autocomplete="off"><br><br>
            <label>Password:</label><br>
            <input type="password" name="password" required><br><br>
            <button type="submit">Login</button>
        </form>
        <p>Not registered? <a href="signup.php">Sign up here</a></p>
    </div>
</body>
</html>
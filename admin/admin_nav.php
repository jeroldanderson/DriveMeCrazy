<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <!-- this one is for general nav -->
<nav>
    <ul style="list-style: none; padding: 0;">
        <li><a href="admin_view.php">View</a></li>
        <li><a href="admin_add.php">Add New</a></li>
        <li><a href="admin_update.php">Update</a></li>
        <li><a href="admin_logout.php" style="color: #ef4565;">Log Out</a></li>
    </ul>
</nav>

<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_pages = ['admin_add.php', 'admin_view.php', 'admin_update.php'];

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}
if (in_array($current_page, $admin_pages)) {
    // determine the base URL for the current page to append query params
    $base = $current_page; 
    echo '
    <div style="text-align:center; margin-bottom: 20px;">
        <a href="'.$base.'?entity=cars" class="sub-nav-link">Cars</a> | 
        <a href="'.$base.'?entity=customers" class="sub-nav-link">Customers</a> | 
        <a href="'.$base.'?entity=bookings" class="sub-nav-link">Bookings</a> | 
        <a href="'.$base.'?entity=payments" class="sub-nav-link">Payments</a>
    </div>';
}
?>
</body>
</html>

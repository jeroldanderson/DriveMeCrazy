<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
?>
<nav>
    <ul style="list-style: none; padding: 0;">
        <li><a href="customer_view.php">Browse Cars</a></li>
        <li><a href="customer_add.php">Book Now</a></li>
        <li><a href="customer_make_payments.php">Make Payments</a></li>
        <li><a href="customer_update.php">My Bookings</a></li>
		<li><a href="customer_payments.php">My Payments</a></li>
		<li><a href="customer_profile.php">Profile</a></li>
        <li><a href="customer_logout.php" style="color: #ef4565;">Log Out</a></li>
    </ul>
</nav>
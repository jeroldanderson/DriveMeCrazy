<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
include '../db_connection.php';

$email = $_SESSION['email'];
$u_sql = "SELECT CUS_ID FROM CUSTOMER WHERE CUS_EMAIL='$email'";
$res = $mysqli->query($u_sql);
$row = $res->fetch_assoc();
$cus_id = $row['CUS_ID'];


$sql = "
SELECT 
    p.PAYMENT_ID,
    p.CUS_ID,
    p.BOOKING_ID,
    p.PAYMENT_AMOUNT,
    b.BOOKING_TOTAL_PRICE
FROM PAYMENT p
JOIN BOOKING b ON p.BOOKING_ID = b.BOOKING_ID
WHERE p.CUS_ID = '$cus_id'
ORDER BY p.PAYMENT_ID DESC
";

$result = $mysqli->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings</title>
    <link rel="stylesheet" href="customer_style.css">
    <style>
        .edit-mode { background-color: #f6ffed; }
        input[readonly] { border: none; }
        .actions { white-space: nowrap; }
    </style>
</head>
<body>
    <?php include 'customer_nav.php'; ?>
    <h2>My Payments</h2>

    <div style="overflow-x: auto;">
        <table>
            <tr>
                <th>Payment ID</th>
				<th>Booking ID</th>
				<th>Payment Amount</th>
                <th>Total Price</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()) { ?>
			<tr id="row-<?php echo $row['PAYMENT_ID']; ?>">
				<form method="post">
					<input type="hidden" name="payment_id" value="<?php echo $row['PAYMENT_ID']; ?>">
					<input type="hidden" name="booking_id" value="<?php echo $row['BOOKING_ID']; ?>">

					<td><?php echo $row['PAYMENT_ID']; ?></td>
					<td><?php echo $row['BOOKING_ID']; ?></td>
					<td>PHP <?php echo number_format($row['PAYMENT_AMOUNT'], 2); ?></td>
					<td>PHP <?php echo number_format($row['BOOKING_TOTAL_PRICE'], 2); ?></td>
				</form>
			</tr>
			<?php } ?>
        </table>
    </div>
</body>
</html>
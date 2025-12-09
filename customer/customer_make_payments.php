<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
include '../db_connection.php';

// get current customer id
$email = $_SESSION['email'];
$u_sql = "SELECT CUS_ID FROM CUSTOMER WHERE CUS_EMAIL='$email'";
$res = $mysqli->query($u_sql);
$row = $res->fetch_assoc();
$cus_id = $row['CUS_ID'];

function generateID($prefix, $table, $column, $mysqli) {
    $result = $mysqli->query("
        SELECT MAX(CAST(SUBSTRING($column, LENGTH('$prefix') + 1) AS UNSIGNED)) AS last_id
        FROM $table
        WHERE $column LIKE '{$prefix}%'"
    );
    $row = $result->fetch_assoc();
    $max = $row['last_id'] ?? 0;
    return $prefix . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $booking_id = $_POST['booking_id'];
    $amount_paying = (float)$_POST['payment_amount'];

    $query = $mysqli->prepare("
        SELECT 
            b.BOOKING_TOTAL_PRICE,
            b.BOOKING_STATUS,
            (SELECT SUM(p.PAYMENT_AMOUNT) FROM PAYMENT p WHERE p.BOOKING_ID = b.BOOKING_ID) as TOTAL_PAID
        FROM BOOKING b
        WHERE b.BOOKING_ID = ? AND b.CUS_ID = ?
    ");
    $query->bind_param("ss", $booking_id, $cus_id);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows == 0) {
        echo "<script>alert('Error: Invalid Booking.');</script>";
    } else {
        $b_data = $result->fetch_assoc();
        
        $total_cost = (float)$b_data['BOOKING_TOTAL_PRICE'];
        $paid_so_far = (float)($b_data['TOTAL_PAID'] ?? 0);
        $current_debt = $total_cost - $paid_so_far;

        if ($amount_paying <= 0) {
             echo "<script>alert('Error: Please enter a valid amount.');</script>";
        } elseif ($amount_paying > $current_debt) {
            echo "<script>alert('Error: Payment exceeds remaining balance of PHP " . number_format($current_debt, 2) . "');</script>";
        } else {
            $payment_id = generateID('PAY', 'PAYMENT', 'PAYMENT_ID', $mysqli);

            // insert Payment
            $stmt = $mysqli->prepare("INSERT INTO PAYMENT (PAYMENT_ID, CUS_ID, BOOKING_ID, PAYMENT_AMOUNT) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $payment_id, $cus_id, $booking_id, $amount_paying);

            if ($stmt->execute()) {
                // update status
                $new_debt = $current_debt - $amount_paying;
                $new_status = ($new_debt <= 0) ? 'Paid' : 'Partial';

                $update = $mysqli->prepare("UPDATE BOOKING SET BOOKING_PAYMENT_STATUS = ? WHERE BOOKING_ID = ?");
                $update->bind_param("ss", $new_status, $booking_id);
                $update->execute();
                $update->close();

                echo "<script>alert('Payment Successful! Remaining Balance: PHP " . number_format($new_debt, 2) . "'); window.location.href='customer_update.php';</script>";
            } else {
                echo "<script>alert('Database Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        }
    }
    $query->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Make Payments</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include 'customer_nav.php'; ?>
    <form method="post">
        <label>Select Booking (Approved Only):</label>
        <select name="booking_id" required>
            <option value="">Select Booking</option>
            <?php
            // only show approved bookings that are approved
            $res = $mysqli->query("SELECT BOOKING_ID, CUS_ID, BOOKING_TOTAL_PRICE FROM BOOKING WHERE BOOKING_STATUS='Approved' AND CUS_ID='$cus_id'");
            while ($b = $res->fetch_assoc()) {
                echo "<option value='{$b['BOOKING_ID']}'>Booking {$b['BOOKING_ID']} - Cost: PHP {$b['BOOKING_TOTAL_PRICE']}</option>";
            }
            ?>
        </select><br>

        <label>Payment Amount:</label>
        <input type="number" step="0.01" name="payment_amount" min="0" required><br>
        
        <button type="submit" name="add_payment">Add Payment</button>
    </form>
</body>
</html>
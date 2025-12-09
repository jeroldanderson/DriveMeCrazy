<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include '../db_connection.php';

// checks which entity is being viewed. cars is default
$entity = isset($_GET['entity']) ? $_GET['entity'] : 'cars';

switch ($entity) {
    case 'customers':
        $sql = "SELECT * FROM CUSTOMER";
        $columns = ['CUS_ID','CUS_FIRST_NAME','CUS_LAST_NAME','CUS_EMAIL','CUS_PHONE_NUMBER','CUS_DRIVERS_LICENSE','CUS_ADDRESS'];
        $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'License', 'Address'];
        break;

    case 'bookings':
        // calculates balance dynamically for the booking View
        $sql = "SELECT 
                    b.BOOKING_ID,
                    b.CUS_ID,
                    b.CAR_ID,
                    b.BOOKING_START_DATE,
                    b.BOOKING_END_DATE,
                    b.BOOKING_STATUS, 
		    b.ADMIN_ID,
                    b.BOOKING_TOTAL_PRICE, 
                    b.BOOKING_PAYMENT_STATUS,
                    -- total price minus sum of all payments for this booking
                    (b.BOOKING_TOTAL_PRICE - COALESCE(
                        (SELECT SUM(p.PAYMENT_AMOUNT) FROM PAYMENT p WHERE p.BOOKING_ID = b.BOOKING_ID), 
                        0
                    )) as REMAINING_BALANCE
                FROM BOOKING b 
                ORDER BY b.BOOKING_ID DESC";
        $columns = ['BOOKING_ID','CUS_ID','CAR_ID','BOOKING_START_DATE','BOOKING_END_DATE','BOOKING_STATUS', 'ADMIN_ID', 'BOOKING_TOTAL_PRICE', 'REMAINING_BALANCE', 'BOOKING_PAYMENT_STATUS'];
        
        $headers = ['Booking ID', 'Customer', 'Car', 'Start', 'End', 'Service Status', 'Verified by', 'Total Price', 'Balance', 'Pay Status'];
        break;

    case 'payments':
        $sql = "SELECT * FROM PAYMENT ORDER BY PAYMENT_ID DESC";
        $columns = ['PAYMENT_ID','CUS_ID','BOOKING_ID','PAYMENT_AMOUNT'];
        $headers = ['Payment ID', 'Customer', 'Booking', 'Amount Paid'];
        break;

    case 'cars':
    default:
        $sql = "SELECT * FROM CAR";
        $columns = ['CAR_ID', 'CAR_PLATE_NUMBER','CAR_MODEL','CAR_SEAT_CAPACITY','CAR_COLOR','CAR_FUEL_TYPE','CAR_TRANSMISSION','CAR_PRICE','CAR_TYPE','CAR_STATUS','CAR_IMG'];
        $headers = ['ID', 'Plate', 'Model', 'Seats', 'Color', 'Fuel', 'Trans', 'Rate', 'Type', 'Status', 'Image'];
        break;
}

$result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin View</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include 'admin_nav.php'; ?>

    <h2>Viewing <?php echo ucfirst($entity); ?></h2>

    <div style="overflow-x: auto;">
        <table>
            <tr>
                <?php foreach ($columns as $col) echo "<th>$col</th>"; ?>
            </tr>

            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($columns as $col) {
                        // handle iamges
                        if ($col === 'CAR_IMG') {
                            echo "<td><img src='show_car_img.php?car_id={$row['CAR_ID']}' width='80' style='border-radius:4px;'></td>";
                        } 
                        // currency format
                        elseif (in_array($col, ['CAR_PRICE', 'BOOKING_TOTAL_PRICE', 'REMAINING_BALANCE', 'PAYMENT_AMOUNT'])) {
                            echo "<td>PHP " . number_format($row[$col], 2) . "</td>";
                        }
                        // regular text
                        else {
                            echo "<td>{$row[$col]}</td>";
                        }
                    }
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='".count($headers)."' style='text-align:center; padding: 20px;'>No records found.</td></tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>
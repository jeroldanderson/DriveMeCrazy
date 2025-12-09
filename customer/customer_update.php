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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['delete'])) {
        $booking_id = $_POST['booking_id'];
 
        $check = $mysqli->query("SELECT * FROM BOOKING WHERE BOOKING_ID='$booking_id' AND CUS_ID='$cus_id'");
        
        if ($check->num_rows == 0) {
            echo "<script>alert('Error: Booking not found or access denied.');</script>";
        } else {
            $b_data = $check->fetch_assoc();
            
            if ($b_data['BOOKING_STATUS'] == 'Completed' || $b_data['BOOKING_STATUS'] == 'Cancelled') {
                echo "<script>alert('Error: Cannot cancel completed/cancelled bookings.');</script>";
            } else {
                $mysqli->query("DELETE FROM BOOKING WHERE BOOKING_ID='$booking_id'");
                echo "<script>alert('Booking Cancelled.');</script>";
            }
        }
    }

    if (isset($_POST['update'])) {
        $booking_id = $_POST['booking_id'];
        $car_id = $_POST['car_id']; 
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];

        if (strtotime($start) > strtotime($end)) {
            echo "<script>alert('Error: Start date cannot be AFTER end date.');</script>";
        } else {
          
            $overlap = $mysqli->prepare("
                SELECT BOOKING_ID FROM BOOKING 
                WHERE CAR_ID = ? 
                AND BOOKING_ID != ? 
                AND BOOKING_STATUS IN ('Pending', 'Approved') 
                AND BOOKING_START_DATE <= ? 
                AND BOOKING_END_DATE >= ?
            ");
            $overlap->bind_param("ssss", $car_id, $booking_id, $end, $start);
            $overlap->execute();
            $overlap->store_result();

            if ($overlap->num_rows > 0) {
                echo "<script>alert('Error: Car is not available for these new dates.');</script>";
            } else {
              
                $car_q = $mysqli->query("SELECT CAR_PRICE FROM CAR WHERE CAR_ID='$car_id'");
                $car_d = $car_q->fetch_assoc();
                
                $d1 = new DateTime($start);
                $d2 = new DateTime($end);
                $days = $d2->diff($d1)->days + 1;
                $new_total = $days * $car_d['CAR_PRICE'];
                // update status
                $pay_query = $mysqli->query("SELECT SUM(PAYMENT_AMOUNT) as TOTAL_PAID FROM PAYMENT WHERE BOOKING_ID='$booking_id'");
                $pay_data = $pay_query->fetch_assoc();
                $paid_so_far = (float)($pay_data['TOTAL_PAID'] ?? 0);
                
                $balance = $new_total - $paid_so_far;
                $new_pay_status = ($balance <= 0) ? 'Paid' : (($paid_so_far > 0) ? 'Partial' : 'Unpaid');
                // finish update
                $stmt = $mysqli->prepare("UPDATE BOOKING SET BOOKING_START_DATE=?, BOOKING_END_DATE=?, BOOKING_TOTAL_PRICE=?, BOOKING_PAYMENT_STATUS=? WHERE BOOKING_ID=?");
                $stmt->bind_param("ssdss", $start, $end, $new_total, $new_pay_status, $booking_id);
                $stmt->execute();
                echo "<script>alert('Booking Updated! New Total: PHP " . number_format($new_total, 2) . "');</script>";
                $stmt->close();
            }
            $overlap->close();
        }
    }
}


// FETCH INFO
$search_active = false;

// SHOW ALL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear'])) {
    header("Location: " . $_SERVER['PHP_SELF']); 
    exit();
}

// check if search was clicked or nah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search_id = $mysqli->real_escape_string(trim($_POST['search_id']));
    $search_active = true;
    
    // look for the record by booking id
    $sql = "SELECT 
                b.*, 
                c.CAR_MODEL,
                (b.BOOKING_TOTAL_PRICE - COALESCE(
                    (SELECT SUM(p.PAYMENT_AMOUNT) FROM PAYMENT p WHERE p.BOOKING_ID = b.BOOKING_ID), 
                    0
                )) AS REMAINING_BALANCE
            FROM BOOKING b 
            JOIN CAR c ON b.CAR_ID = c.CAR_ID 
            WHERE b.CUS_ID='$cus_id' 
            AND (b.BOOKING_ID = '$search_id')
            ORDER BY b.BOOKING_ID DESC";
    
    $result = $mysqli->query($sql);

    // WARNING IF NO RECORD FOUND
    if ($result->num_rows == 0) {
        echo "<script>alert('Warning: No booking found matching \"$search_id\"');</script>";
        $search_active = false; // fallback to showing all
    }
}
// show all
if (!$search_active) {
    $sql = "SELECT 
                b.*, 
                c.CAR_MODEL,
                (b.BOOKING_TOTAL_PRICE - COALESCE(
                    (SELECT SUM(p.PAYMENT_AMOUNT) FROM PAYMENT p WHERE p.BOOKING_ID = b.BOOKING_ID), 
                    0
                )) AS REMAINING_BALANCE
            FROM BOOKING b 
            JOIN CAR c ON b.CAR_ID = c.CAR_ID 
            WHERE b.CUS_ID='$cus_id' 
            ORDER BY b.BOOKING_ID DESC";
    $result = $mysqli->query($sql);
}
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
    <script>
        function toggleEdit(rowId) {
            var row = document.getElementById(rowId);
            var inputs = row.querySelectorAll('input[type="date"]');
            var saveBtn = row.querySelector('.save-btn');
            var updBtn = row.querySelector('.update-btn');

            if (updBtn.style.display !== 'none') {
             
                updBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                inputs.forEach(function(input) {
                    input.removeAttribute('readonly');
                    input.style.border = '1px solid #38bdf8';
                    input.style.backgroundColor = '#334155';
                });
            } else {
              
                updBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <?php include 'customer_nav.php'; ?>
    <h2>My Bookings</h2>

    <div style="overflow-x: auto;">
        <form method="post" style="margin-bottom: 20px; display: flex; gap: 10px; background: transparent; padding: 0; box-shadow: none; border: none;">
            <input type="text" name="search_id" placeholder="Enter Booking ID (e.g. BKG0001)" style="margin: 0; width: 300px;">
            <button type="submit" name="search" style="width: auto;">Search</button>
            <button type="submit" name="clear" style="width: auto; background-color: #64748b;">Show All</button>
        </form>
        <table>
           <tr>
                <th>ID</th>
                <th>Car</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Total Price</th>
                <th>Remaining Balance</th>
                <th>Pay Status</th>
                <th>Booking Status</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()) { 
                $id = $row['BOOKING_ID'];
                $can_edit = ($row['BOOKING_STATUS'] == 'Pending' || $row['BOOKING_STATUS'] == 'Approved');
            ?>
            <tr id="row-<?php echo $id; ?>">
                <form method="post">
                    <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="car_id" value="<?php echo $row['CAR_ID']; ?>">
                    
                    <td><?php echo $id; ?></td>
                    <td>
                        <img src="show_car_img_user.php?car_id=<?php echo $row['CAR_ID']; ?>" width="50" style="vertical-align: middle; margin-right: 5px; border-radius: 4px;">
                        <?php echo $row['CAR_MODEL']; ?>
                    </td>
                    <td><input type="date" name="start_date" value="<?php echo $row['BOOKING_START_DATE']; ?>" readonly></td>
                    <td><input type="date" name="end_date" value="<?php echo $row['BOOKING_END_DATE']; ?>" readonly></td>
                    <td>PHP <?php echo number_format($row['BOOKING_TOTAL_PRICE'], 2); ?></td>
                    
                    <?php 
                        $bal = $row['REMAINING_BALANCE'];
                        if ($bal < 0) {
                            $display_bal = "Refund Due: PHP " . number_format(abs($bal), 2);
                            $color = "#2563eb"; 
                        } elseif ($bal > 0) {
                            $display_bal = "PHP " . number_format($bal, 2);
                            $color = "#e11d48"; 
                        } else {
                            $display_bal = "PHP 0.00";
                            $color = "black";
                        }
                    ?>
                    <td style="font-weight: bold; color: <?php echo $color; ?>;">
                        <input type="text" value="<?php echo $display_bal; ?>" readonly style="background:none; border:none; color:inherit; font-weight:bold;">
                    </td>

                    <td><?php echo $row['BOOKING_PAYMENT_STATUS']; ?></td>
                

                    <td style="font-weight: bold;">
                        <?php echo $row['BOOKING_STATUS']; ?>
                    </td>
                    
                    <td class="actions">
                        <?php if($can_edit) { ?>
                            <button type="button" class="update-btn" onclick="toggleEdit('row-<?php echo $id; ?>')">Edit</button>
                            <button type="submit" name="update" class="save-btn" style="display:none; background-color: #2cb67d;">Save</button>
                            <button type="submit" name="delete" onclick="return confirm('Cancel this booking?')" style="background-color: #ef4565;">Cancel</button>
                        <?php } else { echo "Closed"; } ?>
                    </td>
                </form>
            </tr>
            <?php } ?>
        </table>
    </div>
</body>
</html>
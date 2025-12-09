<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}
include '../db_connection.php';

//gets active admin's ID
$curr_email = $_SESSION['email'];
$adm_sql = "SELECT ADMIN_ID FROM ADMIN WHERE ADMIN_EMAIL='$curr_email'";
$adm_res = $mysqli->query($adm_sql);
$adm_row = $adm_res->fetch_assoc();
$current_admin_id = $adm_row['ADMIN_ID'];

$entity = isset($_GET['entity']) ? $_GET['entity'] : 'cars';

// if show all is clicked, reload the page clean to remove search filters
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear'])) {
    header("Location: admin_update.php?entity=" . $entity);
    exit();
}

// handle update/delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($entity) {
        case 'customers':
            if (isset($_POST['update'])) {
                // check duplicate email
                $email = $_POST['email'];
                $license = $_POST['license'];
                $cus_id = $_POST['cus_id'];

                $check_email = $mysqli->prepare("SELECT 1 FROM CUSTOMER WHERE CUS_EMAIL = ? AND CUS_ID != ?");
                $check_email->bind_param("ss", $email, $cus_id);
                $check_email->execute();
                $check_email->store_result();

                if($check_email->num_rows > 0){
                    echo "<script>alert('Error: Email already exists.'); window.location.href='admin_update.php?entity=customers';</script>";
                    $check_email->close();
                } else {
                    $check_email->close();
                    // LICENSE CHECK
                    $check_license = $mysqli->prepare("SELECT 1 FROM CUSTOMER WHERE CUS_DRIVERS_LICENSE = ? AND CUS_ID != ?");
                    $check_license->bind_param("ss", $license, $cus_id);
                    $check_license->execute();
                    $check_license->store_result();
                    if($check_license->num_rows > 0){
                        echo "<script>alert('Error: Driver\'s License already exists.'); window.location.href='admin_update.php?entity=customers';</script>";
                        $check_license->close();
                    } else {
                        $check_license->close();
                    // password handling
                    $password_sql = "";
                    $params = [
                        $_POST['first_name'], $_POST['last_name'], $_POST['email'], 
                        $_POST['address'], $_POST['license'], $_POST['number']
                    ];
                    $types = "ssssss";

                    if (!empty($_POST['password'])) {
                        $password_sql = ", CUS_PASSWORD=?";
                        $params[] = $_POST['password']; 
                        $types .= "s";
                    }

                    $params[] = $_POST['cus_id'];
                    $types .= "s";

                    $stmt = $mysqli->prepare("UPDATE CUSTOMER SET CUS_FIRST_NAME=?, CUS_LAST_NAME=?, CUS_EMAIL=?, CUS_ADDRESS=?, CUS_DRIVERS_LICENSE=?, CUS_PHONE_NUMBER=? $password_sql WHERE CUS_ID=?");
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) echo "<script>alert('Customer updated successfully!');</script>";
                    else echo "<script>alert('Error: " . $stmt->error . "');</script>";
                    $stmt->close();
                    }
                }
            }
                    if (isset($_POST['delete'])) {
                        $stmt = $mysqli->prepare("DELETE FROM CUSTOMER WHERE CUS_ID=?");
                        $stmt->bind_param("s", $_POST['cus_id']);
                        $stmt->execute();
                        $stmt->close();
                        echo "<script>alert('Customer deleted successfully!');</script>";
                    }
                    break;


        case 'bookings':
            if (isset($_POST['update'])) {
                $booking_id = $_POST['booking_id'];
                $start = $_POST['start_date'];
                $end   = $_POST['end_date'];
		$status = $_POST['status'];

                 // check dates inside the update block 
                if (strtotime($start) > strtotime($end)) {
                    echo "<script>alert('Error: Start date cannot be AFTER end date.');</script>";
                } else {

                    // CHECK FOR OVERLAPPING DATES (exclude self)
                    $overlap = $mysqli->prepare("
                        SELECT BOOKING_ID FROM BOOKING 
                        WHERE CAR_ID = ? 
                        AND BOOKING_ID != ? 
                        AND BOOKING_STATUS IN ('Pending', 'Approved') 
                        AND BOOKING_START_DATE <= ? 
                        AND BOOKING_END_DATE >= ?
                    ");
                    $overlap->bind_param("ssss", $_POST['car_id'], $booking_id, $end, $start);
                    $overlap->execute();
                    $overlap->store_result();

                    if ($overlap->num_rows > 0) {
                    echo "<script>alert('Error: Date update failed. This car is already booked by another customer during these dates.');</script>";
                    $overlap->close();
                    } else {
                    // compute price again if dates change
                    $car_query = $mysqli->query("SELECT CAR_PRICE FROM CAR WHERE CAR_ID = '{$_POST['car_id']}'");
                    $car_data = $car_query->fetch_assoc();
                    
                    $d1 = new DateTime($start);
                    $d2 = new DateTime($end);
                    $days = $d2->diff($d1)->days + 1;
                    $new_total = $days * $car_data['CAR_PRICE'];

                    $pay_query = $mysqli->query("SELECT SUM(PAYMENT_AMOUNT) as TOTAL_PAID FROM PAYMENT WHERE BOOKING_ID='$booking_id'");
                    $pay_data = $pay_query->fetch_assoc();
                    $paid_so_far = (float)($pay_data['TOTAL_PAID'] ?? 0);
                    
                    $balance = $new_total - $paid_so_far;
                    $new_pay_status = ($balance <= 0) ? 'Paid' : (($paid_so_far > 0) ? 'Partial' : 'Unpaid');

		    //booking-admin status		 
		    $verified = ($status == 'Pending') ? NULL : $current_admin_id;

                    $stmt = $mysqli->prepare("UPDATE BOOKING SET BOOKING_START_DATE=?, BOOKING_END_DATE=?, BOOKING_TOTAL_PRICE=?, BOOKING_PAYMENT_STATUS=?, BOOKING_STATUS=?, ADMIN_ID=? WHERE BOOKING_ID=?");

$stmt->bind_param("ssdssss", $start, $end, $new_total, $new_pay_status, $status, $verified, $booking_id);
                    
                     if ($stmt->execute()) {
                            echo "<script>alert('Booking Status Updated.');</script>";
                        } else {
                            echo "<script>alert('Error: " . $stmt->error . "');</script>";
                        }
                    $stmt->close();
                    }
                }
            }
            if (isset($_POST['delete'])) {
                $stmt = $mysqli->prepare("DELETE FROM BOOKING WHERE BOOKING_ID=?");
                $stmt->bind_param("s", $_POST['booking_id']);
                $stmt->execute();
                $stmt->close();
                echo "<script>alert('Booking deleted successfully!');</script>";
            }
            break;

        case 'payments':
            // dont allow updating payment amounts here. 
            // it ruins audit trail. Admin should Delete and Re-Add if they made a mistake.
    
            if (isset($_POST['delete'])) {
                $payment_id = $_POST['payment_id'];
                $booking_id = $_POST['booking_id'];

                // delete the payment
                $stmt = $mysqli->prepare("DELETE FROM PAYMENT WHERE PAYMENT_ID=?");
                $stmt->bind_param("s", $payment_id);
                
                if ($stmt->execute()) {
                    // check and update booking status
                    // we deleted payment and rmoved money, therefore payment may not be fully paid anymore
                    $query = $mysqli->query("
                        SELECT 
                            b.BOOKING_TOTAL_PRICE,
                            (SELECT SUM(p.PAYMENT_AMOUNT) FROM PAYMENT p WHERE p.BOOKING_ID = b.BOOKING_ID) as TOTAL_PAID
                        FROM BOOKING b
                        WHERE b.BOOKING_ID='$booking_id'
                    ");
                    $data = $query->fetch_assoc();
                    
                    $total = (float)$data['BOOKING_TOTAL_PRICE'];
                    $paid  = (float)($data['TOTAL_PAID'] ?? 0);
                    $balance = $total - $paid;

                    $new_status = ($balance <= 0) ? 'Paid' : (($paid > 0) ? 'Partial' : 'Unpaid');

                    $mysqli->query("UPDATE BOOKING SET BOOKING_PAYMENT_STATUS='$new_status' WHERE BOOKING_ID='$booking_id'");

                    echo "<script>alert('Payment deleted! Booking status reverted to: $new_status');</script>";
                } else {
                    echo "<script>alert('Error deleting payment.');</script>";
                }
                $stmt->close();
            }
            break;

        case 'cars':
                // HANDLE UPDATE
                if (isset($_POST['update'])) {
                    // checking plate number format
                    $plateInput = strtoupper($_POST['plate']);
                    // format checking
                    if (!preg_match('/^[A-Z]{3}-\d{3,4}$/', $plateInput)) { // 3 letters + - + 3 or 4 numbers
                        echo "<script>alert('Error: Invalid Plate Number. Format must be LLL-DDD or LLL-DDDD (e.g., ABC-1234).'); window.location.href='admin_add.php?entity=cars';</script>";
                        exit();
                    } 
                    $car_id = $_POST['car_id'];
                // DUPE CHECK FOR PLATE
                $check = $mysqli->prepare("SELECT 1 FROM CAR WHERE CAR_PLATE_NUMBER = ? AND CAR_ID != ?");
                $check->bind_param("ss", $plateInput, $car_id);
                $check->execute();
                $check->store_result();
                if ($check->num_rows > 0) {
                    echo "<script>alert('Error: Another car with Plate Number $plateInput already exists.'); window.location.href='admin_update.php?entity=cars';</script>";
                } else {
                    $model = trim($_POST['model']);
                    $seats = (int)$_POST['seats'];
                    $color = trim($_POST['color']);
                    $fuel = trim($_POST['fuel']);
                    $transmission = trim($_POST['transmission']);
                    $rate = (float)$_POST['rate'];
                    $type = trim($_POST['type']);
                    $status = trim($_POST['status']);

                    $image_uploaded = isset($_FILES['car_img']) && $_FILES['car_img']['tmp_name'] != "";
                    if ($image_uploaded) {
                        $imgData = file_get_contents($_FILES['car_img']['tmp_name']);
                    } 
                    $sql = "UPDATE CAR SET
                        CAR_PLATE_NUMBER=?, CAR_MODEL=?, CAR_SEAT_CAPACITY=?, CAR_COLOR=?,
                        CAR_FUEL_TYPE=?, CAR_TRANSMISSION=?, CAR_PRICE=?, CAR_TYPE=?, CAR_STATUS=?"; 
                    $types = "ssisssdss";
                    $params = [$plateInput, $model, $seats, $color, $fuel, $transmission, $rate, $type, $status];
                    if ($image_uploaded) {
                        $sql .= ", CAR_IMG=?";
                        $types .= "b";
                        $params[] = $imgData;
                    } 
                    $sql .= " WHERE CAR_ID=?";
                    $types .= "s";
                    $params[] = $car_id;
                    $stmt = $mysqli->prepare($sql);
                    $stmt->bind_param($types, ...$params);

                    if ($image_uploaded) {
                        $stmt->send_long_data(count($params)-2, $imgData);
                    }
                    $stmt->execute();
                    $stmt->close();
                    echo "<script>alert('Car updated successfully!');</script>"; 
                    }
                }
                    if (isset($_POST['delete'])) {
                        $stmt = $mysqli->prepare("DELETE FROM CAR WHERE CAR_ID=?");
                        $stmt->bind_param("s", $_POST['car_id']);
                        $stmt->execute();
                        echo "<script>alert('Car deleted!');</script>";
                    }
                break;
    }
}
$search_active = false;

// check if clicked
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search_id = $mysqli->real_escape_string(trim($_POST['search_id']));
    $search_active = true;
    
    switch ($entity) {
        case 'customers': 
            $result = $mysqli->query("SELECT * FROM CUSTOMER WHERE CUS_ID = '$search_id'"); 
            break;
        case 'bookings': 
            // search booking id or customer id
            $result = $mysqli->query("
                SELECT 
                    b.*,
                    (b.BOOKING_TOTAL_PRICE - COALESCE((SELECT SUM(p.PAYMENT_AMOUNT) FROM PAYMENT p WHERE p.BOOKING_ID = b.BOOKING_ID), 0)) as REMAINING_BALANCE
                FROM BOOKING b 
                WHERE b.BOOKING_ID = '$search_id' OR b.CUS_ID = '$search_id'
                ORDER BY b.BOOKING_ID DESC
            ");
            break; 
        case 'payments': 
            $result = $mysqli->query("SELECT * FROM PAYMENT WHERE PAYMENT_ID = '$search_id' OR BOOKING_ID = '$search_id'"); 
            break;
        case 'cars':
        default: 
            $result = $mysqli->query("SELECT * FROM CAR WHERE CAR_ID = '$search_id' OR CAR_PLATE_NUMBER = '$search_id';"); 
            break;
    }

    // WARNING IF TRYING TO UPDATE/DELETE NON-EXISTENT RECORD
    if ($result->num_rows == 0) {
        echo "<script>alert('Warning: No record found with ID: $search_id');</script>";
        $search_active = false; 
    }
}

// show all
if (!$search_active) {
    switch ($entity) {
        case 'customers': $result = $mysqli->query("SELECT * FROM CUSTOMER ORDER BY CUS_ID ASC"); break;
        case 'bookings':  
            $result = $mysqli->query("
                SELECT 
                    b.*,
                    (b.BOOKING_TOTAL_PRICE - COALESCE((SELECT SUM(p.PAYMENT_AMOUNT) FROM PAYMENT p WHERE p.BOOKING_ID = b.BOOKING_ID), 0)) as REMAINING_BALANCE
                FROM BOOKING b 
                ORDER BY b.BOOKING_ID DESC
            "); 
            break; 
        case 'payments':  $result = $mysqli->query("SELECT * FROM PAYMENT ORDER BY PAYMENT_ID DESC"); break;
        case 'cars':
        default:          $result = $mysqli->query("SELECT * FROM CAR ORDER BY CAR_ID ASC"); break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update <?php echo ucfirst($entity); ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        tr.edit-mode { background: #f6ffed; }
        td input[readonly] { border: none; background: transparent; }
        td select:disabled { background: transparent; }
        .actions { white-space: nowrap; }
        td input:not([type="file"]), td select {
            width: 100%; 
            box-sizing: border-box; 
            min-width: 100px;
        }
        td input[readonly] {
            border: none;
            background: transparent;
            padding: 0; 
        }
        .actions {
            white-space: nowrap;
            min-width: 160px;
        }
        .car-img-cell {
            min-width: 120px;
            text-align: center;
        } 
    </style>
    <script>
        function toggleEdit(rowId) {
            const row = document.getElementById(rowId);
            const inputs = row.querySelectorAll("input");
            const selects = row.querySelectorAll("select");
            const saveBtn = row.querySelector(".save-btn");
            const updateBtn = row.querySelector(".update-btn");

            row.classList.toggle("edit-mode");

            if(updateBtn.style.display === "none") {
                updateBtn.style.display = "inline-block";
                saveBtn.style.display = "none";
            } else {
                updateBtn.style.display = "none";
                saveBtn.style.display = "inline-block";
            }

            inputs.forEach(input => {
                if (input.name.endsWith("_id") || input.name === 'total_price' || input.name === 'payment_status') return; // LOCK IDs and Calculated Fields
                if (input.type === "file") { input.disabled = !input.disabled; return; }
                
                if (input.hasAttribute("readonly")) input.removeAttribute("readonly");
                else input.setAttribute("readonly", true);
            });

            selects.forEach(sel => { sel.disabled = !sel.disabled; });

            // makes sure all selects are enabled before submission
            document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
            const selects = this.querySelectorAll('select');
            selects.forEach(sel => sel.disabled = false);
        });
    });
        }
    </script>
</head>
<body>
    <?php include 'admin_nav.php'; ?>
    <h2>Update <?php echo ucfirst($entity); ?></h2>

<div style="overflow-x: auto; padding: 0 20px;">
    <form method="post" style="margin-bottom: 20px; display: flex; gap: 10px; background: transparent; padding: 0; box-shadow: none; border: none;">
        <input type="text" name="search_id" autocomplete="off" placeholder="Enter ID (e.g. CUS0001, CAR0001)..." style="margin: 0; width: 300px;">
        <button type="submit" name="search" style="width: auto;">Search</button>
        <button type="submit" name="clear" style="width: auto; background-color: #64748b;">Show All</button>
    </form>
    <table>
        <thead>
            <tr>
                <?php
                if ($entity == 'cars') echo "<th>ID</th><th>Plate</th><th>Model</th><th>Seats</th><th>Color</th><th>Fuel</th><th>Trans</th><th>Price</th><th>Type</th><th>Status</th><th>Image</th><th>Actions</th>";
                elseif ($entity == 'customers') echo "<th>ID</th><th>First</th><th>Last</th><th>Email</th><th>Address</th><th>License</th><th>Phone</th><th>Password</th><th>Actions</th>";
                elseif ($entity == 'bookings') echo "<th>ID</th><th>Customer</th><th>Car</th><th>Start</th><th>End</th><th>Booking Status</th><th>Total Cost</th><th>Remaining Blanace</th><th>Payment Status</th><th>Actions</th>";
                elseif ($entity == 'payments') echo "<th>ID</th><th>Customer</th><th>Booking</th><th>Amount Paid</th><th>Actions</th>";
                ?>
            </tr>
        </thead>
        <tbody>        
            <?php while ($row = $result->fetch_assoc()) { 
                $rowId = $row[array_key_first($row)];
            ?>
            <tr id="row-<?php echo $rowId; ?>">
                <form method="post" enctype="multipart/form-data">
                    
                    <?php if ($entity == 'cars'): ?>
                        <td><input type="text" name="car_id" value="<?php echo $row['CAR_ID']; ?>"  autocomplete="off" readonly></td>
                        <td><input type="text" name="plate" title="Format: ABC-1234 or ABC-123" maxlength="8" requried oninput="this.value = this.value.toUpperCase()" style="text-transform: uppercase;" pattern="[A-Za-z]{3}-\d{3,4}" value="<?php echo $row['CAR_PLATE_NUMBER']; ?>" autocomplete="off" readonly required></td>
                        <td><input type="text" name="model" autocomplete="off" value="<?php echo $row['CAR_MODEL']; ?>" readonly required></td>
                        <td><input type="number" name="seats" min="1" value="<?php echo $row['CAR_SEAT_CAPACITY']; ?>" readonly required></td>
                        <td><input type="text" name="color" value="<?php echo $row['CAR_COLOR']; ?>" readonly required></td>
                        <td>
                            <select name="fuel" disabled required>
                                <option <?php if($row['CAR_FUEL_TYPE']=='Diesel') echo 'selected'; ?>>Diesel</option>
                                <option <?php if($row['CAR_FUEL_TYPE']=='Petrol') echo 'selected'; ?>>Petrol</option>
                                <option <?php if($row['CAR_FUEL_TYPE']=='Electric') echo 'selected'; ?>>Electric</option>
                                <option <?php if($row['CAR_FUEL_TYPE']=='Hybrid') echo 'selected'; ?>>Hybrid</option>
                            </select>
                        </td>
                        <td>
                            <select name="transmission" disabled required>
                                <option <?php if($row['CAR_TRANSMISSION']=='Manual') echo 'selected'; ?>>Manual</option>
                                <option <?php if($row['CAR_TRANSMISSION']=='Automatic') echo 'selected'; ?>>Automatic</option>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" min="0" name="rate" value="<?php echo $row['CAR_PRICE']; ?>" readonly required></td>
                        <td>
                            <select name="type" disabled required>
                                <option value="Sedan" <?php if($row['CAR_TYPE']=='Sedan') echo 'selected'; ?>>Sedan</option>
                                <option value="SUV" <?php if($row['CAR_TYPE']=='SUV') echo 'selected'; ?>>SUV</option>
                                <option value="Sports" <?php if($row['CAR_TYPE']=='Sports') echo 'selected'; ?>>Sports</option>
                                <option value="Pickup" <?php if($row['CAR_TYPE']=='Pickup') echo 'selected'; ?>>Pickup</option>
                                <option value="Hatchback" <?php if($row['CAR_TYPE']=='Hatchback') echo 'selected'; ?>>Hatchback</option>
                                <option value="Van" <?php if($row['CAR_TYPE']=='Van') echo 'selected'; ?>>Van</option>
                            </select>
                        </td>
                        <td>
                            <select name="status" disabled required>
                                <option value="Available" <?php if($row['CAR_STATUS']=='Available') echo 'selected'; ?>>Available</option>
                                <option value="Unavailable" <?php if($row['CAR_STATUS']=='Unavailable') echo 'selected'; ?>>Unavailable</option>
                            </select>
                        </td>
                        <td class="car-img-cell">
                            <img src="show_car_img.php?car_id=<?php echo $row['CAR_ID']; ?>" width="100"><br>
                            <input type="file" name="car_img" disabled accept=".png,image/png">
                        </td>

                        <?php elseif ($entity == 'customers'): ?>
                            <td><input type="text" name="cus_id" value="<?php echo $row['CUS_ID']; ?>" readonly></td>
                            <td><input type="text" name="first_name" value="<?php echo $row['CUS_FIRST_NAME']; ?>" maxlength="50" autocomplete="off" readonly required></td>
                            <td><input type="text" name="last_name" value="<?php echo $row['CUS_LAST_NAME']; ?>" maxlength="50" autocomplete="off" readonly required></td>
                            <td><input type="email" name="email" value="<?php echo $row['CUS_EMAIL']; ?>" maxlength="100" autocomplete="off" readonly required></td>
                            <td><input type="text" name="address" value="<?php echo $row['CUS_ADDRESS']; ?>" maxlength="255" autocomplete="off" readonly required></td>
                            <td><input type="text" name="license" value="<?php echo $row['CUS_DRIVERS_LICENSE']; ?>" oninput="this.value = this.value.toUpperCase()" pattern="[A-Za-z]\d{2}-\d{2}-\d{6}" maxlength="13" autocomplete="off" title="Format: LDD-DD-DDDDDD (e.g., A12-34-123456)" readonly required></td>
                            <td><input type="text" name="number" value="<?php echo $row['CUS_PHONE_NUMBER']; ?>" pattern="09\d{9}" maxlength="11" autocomplete="off" title="Must be 11 digits and start with '09' (e.g., 09171234567)" readonly required></td>
                            <td><input type="password" name="password" value="<?php echo $row['CUS_PASSWORD']; ?>" maxlength="255" readonly required></td>

                    <?php elseif ($entity == 'bookings'): ?>
                        <td><input type="text" name="booking_id" value="<?php echo $row['BOOKING_ID']; ?>" readonly></td>
                        <td><input type="text" name="cus_id" value="<?php echo $row['CUS_ID']; ?>" readonly required></td>
                        <td><input type="text" name="car_id" value="<?php echo $row['CAR_ID']; ?>" readonly required></td>
                        <td><input type="date" name="start_date" value="<?php echo $row['BOOKING_START_DATE']; ?>" readonly required></td>
                        <td><input type="date" name="end_date" value="<?php echo $row['BOOKING_END_DATE']; ?>" readonly required></td>
                        
                        <td>
                            <select name="status" disabled required>
                                <option <?php if($row['BOOKING_STATUS']=='Pending') echo 'selected'; ?>>Pending</option>
                                <option <?php if($row['BOOKING_STATUS']=='Approved') echo 'selected'; ?>>Approved</option>
                                <option <?php if($row['BOOKING_STATUS']=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                                <option <?php if($row['BOOKING_STATUS']=='Completed') echo 'selected'; ?>>Completed</option>
                            </select>
                        </td>
                        <td><input type="text" name="total_price" value="<?php echo $row['BOOKING_TOTAL_PRICE']; ?>" readonly style="background:none; border:none;"></td>
                        <?php 
                            $bal = $row['REMAINING_BALANCE'];
                            if ($bal < 0) {
                                // refund
                                $display_bal = "Refund Due: PHP " . number_format(abs($bal), 2);
                                $color = "#2563eb";
                            } elseif ($bal > 0) {
                                // debt
                                $display_bal = "PHP " . number_format($bal, 2);
                                $color = "#e11d48"; 
                            } else {
                                // settled
                                $display_bal = "PHP 0.00";
                                $color = "black";
                            }
                        ?>
                        <td style="font-weight: bold; color: <?php echo $color; ?>;">
                            <input type="text" value="<?php echo $display_bal; ?>" readonly style="background:none; border:none; color:inherit; font-weight:bold;">
                        </td>
                        <td><input type="text" name="payment_status" value="<?php echo $row['BOOKING_PAYMENT_STATUS']; ?>" readonly style="background:none; border:none;"></td>

                    <?php elseif ($entity == 'payments'): ?>
                        <td><input type="text" name="payment_id" value="<?php echo $row['PAYMENT_ID']; ?>" readonly></td>
                        <td><input type="text" name="cus_id" value="<?php echo $row['CUS_ID']; ?>" readonly></td>
                        <td><input type="text" name="booking_id" value="<?php echo $row['BOOKING_ID']; ?>" readonly></td>
                        <td><input type="number" name="amount" value="<?php echo $row['PAYMENT_AMOUNT']; ?>" readonly></td>
                    <?php endif; ?>    

                    <td class="actions" style="min-width: 160px;">
                        <?php if($entity != 'payments'): ?> 
                            <button type="button" class="update-btn" onclick="toggleEdit('row-<?php echo $rowId; ?>')">Edit</button>
                            <button type="submit" name="update" class="save-btn" style="display:none; background-color: #2cb67d;">Save</button>
                        <?php endif; ?>
                        
                        <button type="submit" name="delete" onclick="return confirm('Delete? This action is permanent.')" style="background-color: #ef4565;">Delete</button>
                    </td>
                </form>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
</body>
</html>
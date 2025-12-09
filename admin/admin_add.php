<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}
include '../db_connection.php';
include 'admin_nav.php';

// decide which entity to add to
$entity = isset($_GET['entity']) ? $_GET['entity'] : 'cars';

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($entity) {
        case 'cars':     
            // checking plate number format
            $plateInput = strtoupper($_POST['plate']);
            if (!preg_match('/^[A-Z]{3}-\d{3,4}$/', $plateInput)) { // 3 letters + - + 3 or 4 numbers
                echo "<script>alert('Error: Invalid Plate Number. Format must be LLL-DDD or LLL-DDDD (e.g., ABC-1234).'); window.location.href='admin_add.php?entity=cars';</script>";
                exit(); 
            }
            
            // DUPE CHECK
            $check = $mysqli->prepare("SELECT 1 FROM CAR WHERE CAR_PLATE_NUMBER = ?");
            $check->bind_param("s", $plateInput);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                echo "<script>alert('Error: Car with Plate Number $plateInput already exists.'); window.location.href='admin_add.php?entity=cars';</script>";
            } else {
            // END OF DUPE CHECK, NOW INSERT
            $imgData = file_get_contents($_FILES['img']['tmp_name']);
            $_POST['car_id'] = generateID('CAR', 'CAR', 'CAR_ID', $mysqli);

            $stmt = $mysqli->prepare("INSERT INTO CAR 
                (CAR_ID, CAR_PLATE_NUMBER, CAR_MODEL, CAR_SEAT_CAPACITY, CAR_COLOR, 
                CAR_FUEL_TYPE, CAR_TRANSMISSION, CAR_PRICE, CAR_TYPE, CAR_STATUS, CAR_IMG)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // bind_param: last argument is a dummy NULL for send_long_data
            $null = NULL;
            $stmt->bind_param(
                "sssisssdssb",
                $_POST['car_id'], $plateInput, $_POST['model'], $_POST['seats'],
                $_POST['color'], $_POST['fuel'], $_POST['transmission'], $_POST['rate'],
                $_POST['type'], $_POST['status'], $null
            );

            // send image data 
            $stmt->send_long_data(10, $imgData);

            echo $stmt->execute() ? "<script>alert('Car added successfully!');</script>" 
                                : "<script>alert('Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
            break;

        case 'customers':
            // check duplicate email
            $email = $_POST['email'];
            $check = $mysqli->prepare("SELECT 1 FROM CUSTOMER WHERE CUS_EMAIL = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if($check->num_rows > 0){
                echo "<script>alert('Error: Email already exists.'); window.location.href='admin_add.php?entity=customers';</script>";
            } else { // check unique license before inserting
                $license = $_POST['license'];
                
                $check_lic = $mysqli->prepare("SELECT 1 FROM CUSTOMER WHERE CUS_DRIVERS_LICENSE = ?");
                $check_lic->bind_param("s", $license);
                $check_lic->execute();
                $check_lic->store_result();
                if ($check_lic->num_rows > 0) {
                     echo "<script>alert('Error: Driver\'s License ($license) already exists.'); window.location.href='admin_add.php?entity=customers';</script>";
                } else {
                $_POST['cus_id'] = generateID('CUS', 'CUSTOMER', 'CUS_ID', $mysqli);
                
                $stmt = $mysqli->prepare("INSERT INTO CUSTOMER (CUS_ID, CUS_FIRST_NAME, CUS_LAST_NAME, CUS_EMAIL, CUS_ADDRESS, CUS_DRIVERS_LICENSE, CUS_PHONE_NUMBER, CUS_PASSWORD) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $_POST['cus_id'], $_POST['first_name'], $_POST['last_name'], $email, $_POST['address'], $_POST['license'], $_POST['number'], $_POST['password']);
                
                if($stmt->execute()){
                    echo "<script>alert('Customer added successfully!');</script>";
                } else {
                    echo "<script>alert('Error: " . $stmt->error . "');</script>";
                }
                $stmt->close();
            }
            }
            $check->close();
            break;

        case 'bookings':    
            // check if car exists/unavailable
            $car_id = $_POST['car_id'];
            $check_car = $mysqli->prepare("SELECT CAR_PRICE FROM CAR WHERE CAR_ID = ? AND CAR_STATUS='Available'");
            $check_car->bind_param("s", $car_id);
            $check_car->execute();
            $res = $check_car->get_result();

            if ($res->num_rows == 0) {
                echo "<script>alert('Selected car does not exist or is unavailable.'); admin_add.php?entity=bookings</script>";
            }
            
            // validates start and end date
            $start_date = $_POST['start_date'];
            $end_date   = $_POST['end_date'];

            if (strtotime($start_date) > strtotime($end_date)) {
                echo "<script>alert('Error: Start date cannot be AFTER end date.'); admin_add.php?entity=bookings</script>";
                exit();
            }
            // CHECK FOR OVERLAPPING BOOKINGS
            $overlap = $mysqli->prepare("
                SELECT BOOKING_ID FROM BOOKING 
                WHERE CAR_ID = ? 
                AND BOOKING_STATUS IN ('Pending', 'Approved') 
                AND BOOKING_START_DATE <= ? 
                AND BOOKING_END_DATE >= ?
            ");
            $overlap->bind_param("sss", $car_id, $end_date, $start_date);
            $overlap->execute();
            $overlap->store_result();

            if ($overlap->num_rows > 0) {
                echo "<script>alert('Error: This car is already booked for these dates.'); window.location.href='admin_add.php?entity=bookings';</script>";
                $overlap->close();
                exit(); // if yes, stop
            }
            $overlap->close();

            // start of setting total price
            $car_data = $res->fetch_assoc();
            $start = new DateTime($start_date);
            $end   = new DateTime($end_date);
            $days  = $end->diff($start)->days + 1; 
            $total_price = $days * $car_data['CAR_PRICE'];

            $_POST['booking_id'] = generateID('BKG', 'BOOKING', 'BOOKING_ID', $mysqli);
            // end of setting total price

            $stmt = $mysqli->prepare("INSERT INTO BOOKING (BOOKING_ID, CUS_ID, CAR_ID, BOOKING_START_DATE, BOOKING_END_DATE, BOOKING_STATUS, BOOKING_TOTAL_PRICE, BOOKING_PAYMENT_STATUS) VALUES (?, ?, ?, ?, ?, ?, ?, 'Unpaid')");
            $stmt->bind_param("ssssssd", $_POST['booking_id'], $_POST['cus_id'], $_POST['car_id'], $start_date, $end_date, $_POST['status'], $total_price);
                    
            if($stmt->execute()){
                echo "<script>alert('Booking added successfully! Total Cost: PHP " . number_format($total_price, 2) . "');</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }
                $stmt->close();
            break;

        case 'payments':
            if (isset($_POST['add_payment'])) {
                $payment_id = generateID('PAY', 'PAYMENT', 'PAYMENT_ID', $mysqli);
                $cus_id = $_POST['cus_id'];
                $booking_id = $_POST['booking_id'];
                $amount_paying = (float)$_POST['payment_amount']; 

                // get booking info
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
                    echo "<script>alert('Error: Invalid Booking or Customer mismatch.');</script>";
                } else {
                    $b_data = $result->fetch_assoc();

                    if ($b_data['BOOKING_STATUS'] !== 'Approved') {
                        echo "<script>alert('Error: Payments can only be made for APPROVED bookings. This booking is " . $b_data['BOOKING_STATUS'] . ".');</script>";
                    } else {
                        $total_cost = (float)$b_data['BOOKING_TOTAL_PRICE'];
                        $paid_so_far = (float)($b_data['TOTAL_PAID'] ?? 0);
                        $current_debt = $total_cost - $paid_so_far;

                        if ($amount_paying > $current_debt) {
                            echo "<script>alert('Error: Payment of $amount_paying exceeds remaining debt of $current_debt.');</script>";
                        } else {
                            $stmt = $mysqli->prepare("INSERT INTO PAYMENT (PAYMENT_ID, CUS_ID, BOOKING_ID, PAYMENT_AMOUNT) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("ssss", $payment_id, $cus_id, $booking_id, $amount_paying);
                            
                            if ($stmt->execute()) {
                                // update booking payment status
                                $new_debt = $current_debt - $amount_paying;
                                $new_status = ($new_debt <= 0) ? 'Paid' : 'Partial';

                                $update = $mysqli->prepare("UPDATE BOOKING SET BOOKING_PAYMENT_STATUS = ? WHERE BOOKING_ID = ?");
                                $update->bind_param("ss", $new_status, $booking_id);
                                $update->execute();
                                $update->close();
                                
                                echo "<script>alert('Payment added! Booking is now: $new_status. Remaining: PHP $new_debt');</script>";
                            } else {
                                echo "<script>alert('Database Error: " . $stmt->error . "');</script>";
                            }
                            $stmt->close();
                        }
                    }
                }
                $query->close();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add <?php echo ucfirst($entity); ?></title>
</head>
<body>
<h2>Add <?php echo ucfirst($entity); ?></h2>

<?php if ($entity == 'cars'): ?>
        <form method="post" enctype="multipart/form-data">
            <label>Plate Number:</label><input type="text" name="plate" pattern="[A-Za-z]{3}-\d{3,4}" oninput="this.value = this.value.toUpperCase()" style="text-transform: uppercase;" maxlength="8" title="Format: ABC-1234" autocomplete="off" required><br>
            <label>Model:</label><input type="text" name="model" autocomplete="off" required><br>
            <label>Seats:</label><input type="number" name="seats" min="1" required><br>
            <label>Color:</label><input type="text" name="color" autocomplete="off" required><br>
            <label>Fuel Type:</label>
            <select name="fuel" required><option>Diesel</option><option>Petrol</option><option>Electric</option><option>Hybrid</option></select><br>
            <label>Transmission:</label>
            <select name="transmission" required><option>Manual</option><option>Automatic</option></select><br>
            <label>Rental Price (per day):</label><input type="number" step="0.01" name="rate" min="0" required><br>
            <label>Type:</label>
            <select name="type" required>
                <option>Sedan</option>
                <option>SUV</option>
                <option>Van</option>
                <option>Sports</option>
                <option>Pickup</option>
                <option>Hatchback</option>
            </select><br>
            <label>Status:</label>
            <select name="status" required>
                <option>Available</option>
                <option>Unavailable</option>
            </select><br>
            <label>Car Image:</label><input type="file" name="img" accept=".png,image/png" required><br>
            <button type="submit">Add Car</button>
        </form>

    <?php elseif ($entity == 'customers'): ?>
        <form method="post">
            <label>First Name:</label><input type="text" name="first_name" maxlength="50" autocomplete="off" required><br>
            <label>Last Name:</label><input type="text" name="last_name" maxlength=50 autocomplete="off" required><br>
            <label>Email:</label><input type="email" name="email" maxlength="100" autocomplete="off" required><br>
            <label>Address:</label><input type="text" name="address" maxlength="255" autocomplete="off" required><br>
            <label>Driver's License:</label><input type="text" name="license" oninput="this.value = this.value.toUpperCase()" pattern="[A-Za-z]\d{2}-\d{2}-\d{6}" maxlength="13" title="Format: LDD-DD-DDDDDD (e.g., A12-34-123456)" autocomplete="off" required><br>
            <label>Phone Number:</label><input autocomplete="off" type="text" name="number" maxlength="11" placeholder="09xxxxxxxxx" pattern="09\d{9}" title="Must be 11 digits and start with '09' (e.g., 09171234567)" required><br>
            <label>Password:</label><input type="password" name="password" maxlength="255" required><br>
            <button type="submit">Add Customer</button>
        </form>

    <?php elseif ($entity == 'bookings'): ?>
        <form method="post">
            <label>Customer:</label>
            <select name="cus_id" required>
                <option value="">Select Customer</option>
                <?php
                $res = $mysqli->query("SELECT CUS_ID, CUS_FIRST_NAME, CUS_LAST_NAME FROM CUSTOMER");
                while ($c = $res->fetch_assoc()) {
                    echo "<option value='{$c['CUS_ID']}'>{$c['CUS_FIRST_NAME']} {$c['CUS_LAST_NAME']} ({$c['CUS_ID']})</option>";
                }
                ?>
            </select><br>
            <label>Car:</label>
            <select name="car_id" required>
                <option value="">Select Car</option>
                <?php
                // show only available cars
                $res = $mysqli->query("SELECT CAR_ID, CAR_MODEL, CAR_PLATE_NUMBER FROM CAR WHERE CAR_STATUS='Available'");
                while ($car = $res->fetch_assoc()) {
                    echo "<option value='{$car['CAR_ID']}'>{$car['CAR_MODEL']} - {$car['CAR_PLATE_NUMBER']} ({$car['CAR_ID']})</option>";
                }
                ?>
            </select><br>
            <label>Start Date:</label><input type="date" name="start_date" id="start_date" min="<?php echo date('Y-m-d'); ?>" required><br><br>
            <label>End Date:</label><input type="date" name="end_date" id="end-date" required><br>
            <label>Status:</label>
            <select name="status" required><option>Pending</option><option>Approved</option><option>Cancelled</option><option>Completed</option></select><br>
            <button type="submit">Add Booking</button>
        </form>
    <?php endif; ?>

    <?php if ($entity == 'payments'): ?>
    <form method="post">
        <label>Select Customer:</label>
        <select name="cus_id" required>
            <option value="">Select Customer</option>
            <?php
            $res = $mysqli->query("SELECT CUS_ID, CUS_FIRST_NAME, CUS_LAST_NAME FROM CUSTOMER");
            while ($c = $res->fetch_assoc()) {
                echo "<option value='{$c['CUS_ID']}'>{$c['CUS_FIRST_NAME']} {$c['CUS_LAST_NAME']} ({$c['CUS_ID']})</option>";
            }
            ?>
        </select><br>

        <label>Select Booking (Approved Only):</label>
        <select name="booking_id" required>
            <option value="">Select Booking</option>
            <?php
            // only show approved bookings that are not fully paid
            $res = $mysqli->query("SELECT BOOKING_ID, CUS_ID, BOOKING_TOTAL_PRICE FROM BOOKING WHERE BOOKING_STATUS='Approved' AND BOOKING_PAYMENT_STATUS != 'Paid'");
            while ($b = $res->fetch_assoc()) {
                echo "<option value='{$b['BOOKING_ID']}'>Booking {$b['BOOKING_ID']} - Cost: PHP {$b['BOOKING_TOTAL_PRICE']}</option>";
            }
            ?>
        </select><br>

        <label>Payment Amount (Amount Received):</label>
        <input type="number" step="0.01" name="payment_amount" min="0" required><br>
        
        <button type="submit" name="add_payment">Add Payment</button>
    </form>
    <?php endif; ?>
</body>
</html>

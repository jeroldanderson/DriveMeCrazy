<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
include '../db_connection.php';

// user ID
$email = $_SESSION['email'];
$u_sql = "SELECT CUS_ID FROM CUSTOMER WHERE CUS_EMAIL='$email'";
$u_res = $mysqli->query($u_sql);
$u_row = $u_res->fetch_assoc();
$cus_id = $u_row['CUS_ID'];

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

$selected_car = isset($_GET['car_id']) ? $_GET['car_id'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') 
	{
    $car_id = $_POST['car_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];


	//empty field
	if (empty($car_id) || empty($start_date) || empty($end_date)) 
	{
        echo "<script>alert('Error: Missing input.'); window.location.href='customer_add.php';</script>";
        exit();
	}

	//wrong date 
	if (strtotime($start_date) > strtotime($end_date)) 
	{
	echo "<script>alert('Error: Start date cannot be AFTER end date.'); window.location.href='customer_add.php';</script>";
        exit();
    	}

   	$today = date('Y-m-d');
   	if ($start_date < $today) {
        echo "<script>alert('Error: You cannot book dates in the past.'); window.location.href='customer_add.php';</script>";
        exit();
    	}

	//car check
$check_car = $mysqli->prepare("SELECT CAR_PRICE, CAR_STATUS, CAR_MODEL FROM CAR WHERE CAR_ID = ?");
$check_car->bind_param("s", $car_id);
$check_car->execute();
$res = $check_car->get_result();

	if ($res->num_rows == 0) {
        echo "<script>alert('Error: Selected car does not exist.'); window.location.href='customer_add.php';</script>";
        exit();
    	}
    
    
$car_data = $res->fetch_assoc();
   	if ($car_data['CAR_STATUS'] != 'Available') {
        echo "<script>alert('Error: Car is not available.'); window.location.href='customer_add.php';</script>";
        exit();
    	}

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

    if ($overlap->num_rows > 0) 
	{
        echo "<script>alert('Error: This car is already booked for these dates.'); window.location.href='customer_add.php';</script>";
        $overlap->close();
        exit();
    	}
$overlap->close();

    //price
$d1 = new DateTime($start_date);
$d2 = new DateTime($end_date);
$days = $d2->diff($d1)->days + 1;
$total = $days * $car_data['CAR_PRICE'];

$booking_id = generateID('BKG', 'BOOKING', 'BOOKING_ID', $mysqli);

$stmt = $mysqli->prepare("INSERT INTO BOOKING (BOOKING_ID, CUS_ID, CAR_ID, BOOKING_START_DATE, BOOKING_END_DATE, BOOKING_STATUS, BOOKING_TOTAL_PRICE, BOOKING_PAYMENT_STATUS) VALUES (?, ?, ?, ?, ?, 'Pending', ?, 'Unpaid')");
    $stmt->bind_param(
"sssssd", $booking_id, $cus_id, $car_id, $start_date, $end_date, $total);

	if ($stmt->execute()) {
        echo "<script>alert('Booking Requested! Total Cost: PHP " . number_format($total, 2) . "'); window.location.href='customer_update.php';</script>";
    	} else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    	}
$stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book a Car</title>
    <link rel="stylesheet" href="customer_style.css">
</head>
<body>
    <?php include 'customer_nav.php'; ?>

    <div class="box">
        <h2>Book a Car</h2>
        <form method="post">
            <label>Select Car:</label>
            <select name="car_id" required>
                <option value="">-- Select Car --</option>
                <?php
                
                $res = $mysqli->query("SELECT CAR_ID, CAR_MODEL, CAR_PRICE FROM CAR WHERE CAR_STATUS='Available'");
                while ($c = $res->fetch_assoc()) {
                    $sel = ($c['CAR_ID'] == $selected_car) ? 'selected' : '';
                    echo "<option value='{$c['CAR_ID']}' $sel>{$c['CAR_MODEL']} (PHP {$c['CAR_PRICE']}/day)</option>";
                }
                ?>
            </select><br>

            <label>Start Date:</label>
            <input type="date" name="start_date" min="<?php echo date('Y-m-d'); ?>" required><br>

            <label>End Date:</label>
            <input type="date" name="end_date" min="<?php echo date('Y-m-d'); ?>" required><br>

            <button type="submit">Submit Request</button>
        </form>
    </div>
</body>
</html>
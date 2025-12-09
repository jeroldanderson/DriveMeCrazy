<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
include '../db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Cars</title>
    <link rel="stylesheet" href="customer_style.css">
</head>
<body>
    <?php include 'customer_nav.php'; ?>

    <h2>Available Cars</h2>

    <div class="car-container">
        <?php
        
        $sql = "SELECT * FROM CAR WHERE CAR_STATUS != 'Retired' ORDER BY CAR_STATUS ASC";
        $result = $mysqli->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $status = $row['CAR_STATUS'];
                $isAvailable = ($status == 'Available');
                
                $color = $isAvailable ? '#2cb67d' : '#ef4565';
                
                echo '<div class="car-box">';

		
                echo '<img src="show_car_img_user.php?car_id='.$row['CAR_ID'].'">';
                echo '<h3 style="margin: 10px 0;">'.$row['CAR_MODEL'].'</h3>';
                echo '<p style="color: var(--text-muted);">Type: '.$row['CAR_TYPE'].'</p>';
                echo '<p style="color: var(--text-muted);">Color: '.$row['CAR_COLOR'].'</p>';
                echo '<p style="font-size: 1.2rem; color: var(--primary); font-weight: bold; margin: 10px 0;">PHP '.number_format($row['CAR_PRICE'], 2).' / day</p>';
                echo '<p style="background-color: '.$color.'; color: white; padding: 5px; border-radius: 4px; display:inline-block; margin-bottom: 10px;">'.$status.'</p><br>';
                
                if ($isAvailable) {
                    echo '<a href="customer_add.php?car_id='.$row['CAR_ID'].'"><button>Book This Car</button></a>';
                } else {
                    echo '<button disabled style="background-color: #555; cursor: not-allowed;">Unavailable</button>';
                }
                echo '</div>';
            }
        } else {
            echo "<p style='text-align:center;'>No cars found.</p>";
        }
        ?>
    </div>
</body>
</html>
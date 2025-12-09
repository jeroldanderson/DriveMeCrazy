<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied");
}

include '../db_connection.php';

if (!isset($_GET['car_id'])) exit();

$car_id = $mysqli->real_escape_string($_GET['car_id']);
$imgQuery = $mysqli->query("SELECT CAR_IMG FROM CAR WHERE CAR_ID = '$car_id'");

if ($imgQuery) {
    $imgRow = $imgQuery->fetch_assoc();
    if ($imgRow && !empty($imgRow['CAR_IMG'])) {
        header("Content-Type: image/png");
        echo $imgRow['CAR_IMG'];
        exit();
    }
}


header("Content-Type: image/png");
@readfile("no_image.png"); 
exit();
?>
<?php
    $user='root';
    $password='';
    $database='';
    $servername='localhost:3310';

    $mysqli=new mysqli($servername,$user,$password,$database);
    if($mysqli->connect_error){
        die('Connect Error ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
    }
?>
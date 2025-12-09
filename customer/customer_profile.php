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
	if (isset($_POST['update'])) {
		// check duplicate email
		$email = $_POST['email'];
		$cus_id = $_POST['cus_id'];
		$check = $mysqli->prepare("SELECT 1 FROM CUSTOMER WHERE CUS_EMAIL = ? AND CUS_ID != ?");
		$check->bind_param("ss", $email, $cus_id);
		$check->execute();
		$check->store_result();

		if($check->num_rows > 0){
			echo "<script>alert('Error: Email already exists.'); window.location.href='customer_profile.php';</script>";
			$check->close();
		} else {
			$check->close();
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
	if (isset($_POST['delete'])) {
				$stmt = $mysqli->prepare("DELETE FROM CUSTOMER WHERE CUS_ID=?");
				$stmt->bind_param("s", $_POST['cus_id']);
				$stmt->execute();
				$stmt->close();
				echo "<script>alert('Customer deleted successfully!');</script>";
	}
}

$result = $mysqli->query("SELECT * FROM CUSTOMER WHERE CUS_ID = '$cus_id'");
?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Account Profile</title>
	<link rel="stylesheet" href="customer_style.css">
    <style>

        h1 {
            text-align: center;
            margin-top: 40px;
            font-size: 40px;
            color: #59c3ff;
        }

    </style>
	<script>
        function toggleEdit(rowId) {
			const row = document.getElementById(rowId);
			const inputs = row.querySelectorAll("input:not(.lock)");
			const selects = row.querySelectorAll("select:not(.lock)");
			const saveBtn = row.querySelector(".save-btn");
			const updateBtn = row.querySelector(".update-btn");

			if(updateBtn.style.display === "none") {
				updateBtn.style.display = "inline-block";
				saveBtn.style.display = "none";
			} else {
				updateBtn.style.display = "none";
				saveBtn.style.display = "inline-block";
			}

			inputs.forEach(input => {
				if (input.type === "file") { input.disabled = !input.disabled; return; }
				if (input.hasAttribute("readonly")) input.removeAttribute("readonly");
				else input.setAttribute("readonly", true);
			});

			selects.forEach(sel => sel.disabled = !sel.disabled);
		}
    </script>
</head>

<body>
	<?php include 'customer_nav.php'; ?>
    <h1>Account Profile</h1>

	<div style="overflow-x: auto; padding: 0 20px;">
		<table>
			<thead>
				<tr>
					<?php
					echo "<th>ID</th><th>First</th><th>Last</th><th>Email</th><th>Address</th><th>License</th><th>Phone</th><th>Password</th><th>Actions</th>";
					?>
				</tr>
			</thead>
			<tbody>        
				<?php while ($row = $result->fetch_assoc()) { 
					$rowId = $row[array_key_first($row)];
				?>
				<tr id="row-<?php echo $rowId; ?>">
					<form method="post" enctype="multipart/form-data">
						<td><input type="text" name="cus_id" value="<?php echo $row['CUS_ID']; ?>" class="lock" readonly></td>
						<td><input type="text" name="first_name" value="<?php echo $row['CUS_FIRST_NAME']; ?>" maxlength="50" autocomplete="off" class="lock" readonly></td>
						<td><input type="text" name="last_name" value="<?php echo $row['CUS_LAST_NAME']; ?>" maxlength="50" autocomplete="off" class="lock" readonly></td>
						<td><input type="email" name="email" value="<?php echo $row['CUS_EMAIL']; ?>" maxlength="100" autocomplete="off" readonly required></td>
						<td><input type="text" name="address" value="<?php echo $row['CUS_ADDRESS']; ?>" maxlength="255" autocomplete="off" readonly required></td>
						<td><input type="text" name="license" value="<?php echo $row['CUS_DRIVERS_LICENSE']; ?>" oninput="this.value = this.value.toUpperCase()" pattern="[A-Za-z]\d{2}-\d{2}-\d{6}" maxlength="13" autocomplete="off" title="Format: LDD-DD-DDDDDD (e.g., A12-34-123456)" readonly required></td>
						<td><input type="text" name="number" value="<?php echo $row['CUS_PHONE_NUMBER']; ?>" pattern="09\d{9}" maxlength="11" autocomplete="off" title="Must be 11 digits and start with '09' (e.g., 09171234567)" readonly required></td>
						<td><input type="password" name="password" value="<?php echo $row['CUS_PASSWORD']; ?>" maxlength="255" readonly required></td>
						
						<td class="actions" style="min-width: 160px;">
                            <button type="button" class="update-btn" onclick="toggleEdit('row-<?php echo $rowId; ?>')">Edit</button>
                            <button type="submit" name="update" class="save-btn" style="display:none; background-color: #2cb67d;">Save</button>
                        
						</td>
					</form>
				</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</body>
</html>
<?php
include 'connection.php'; // Include your database connection

$action = $_POST['action'];
$date = $_POST['date'];
$username = $_POST['username'];
$fullname = $_POST['fullname'];
$time = $_POST['time'];
$ampm = $_POST['ampm']; // Get AM/PM
$amInField = $_POST['amInField'];
$amOutField = $_POST['amOutField'];
$pmInField = $_POST['pmInField'];
$pmOutField = $_POST['pmOutField'];

// Convert 12-hour time to 24-hour time
function convertTo24Hour($time, $ampm)
{
    $dateTime = DateTime::createFromFormat('g:i:s A', $time . ' ' . $ampm);
    return $dateTime ? $dateTime->format('H:i:s') : $time;
}

// Convert the time to 24-hour format
$time_24 = convertTo24Hour($time, $ampm);

// Generate the table name based on the current month and year
$current_year = date("Y");
$current_month = strtolower(date("F")); // Get the full month name in lowercase
$table_name = $current_month . $current_year; // e.g., 'october2024'

// Check if a record already exists for the given username and date
$sql_check = "SELECT * FROM $table_name WHERE username = ? AND date = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ss", $username, $date);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    // Record exists, update the record based on action and AM/PM
    if ($action == 'clock_in') {
        if ($ampm === 'AM' && empty($amInField)) {
            $sql_update = "UPDATE $table_name 
                           SET am_time_in = ?
                           WHERE username = ? AND date = ?";
        } elseif ($ampm === 'PM' && empty($pmInField)) {
            $sql_update = "UPDATE $table_name 
                           SET pm_time_in = ?
                           WHERE username = ? AND date = ?";
        }
    } elseif ($action == 'clock_out') {
        if (empty($amOutField) && empty($pmInField)) {
            $sql_update = "UPDATE $table_name 
                           SET am_time_out = ?
                           WHERE username = ? AND date = ?";
        } elseif ($ampm === 'PM' && empty($pmOutField)) {
            $sql_update = "UPDATE $table_name 
                           SET pm_time_out = ?
                           WHERE username = ? AND date = ?";
        }
    }

    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sss", $time_24, $username, $date);
    $stmt_update->execute();
    $stmt_update->close();
} else {
    // No record exists, insert a new record based on action and AM/PM
    if ($action == 'clock_in') {
        if ($ampm === 'AM') {
            $sql_insert = "INSERT INTO $table_name (username, fullname, date, am_time_in) 
                           VALUES (?, ?, ?, ?)";
        } else {
            $sql_insert = "INSERT INTO $table_name (username, fullname, date, pm_time_in) 
                           VALUES (?, ?, ?, ?)";
        }
    } elseif ($action == 'clock_out') {
        if ($ampm === 'PM') {
            $sql_insert = "INSERT INTO $table_name (username, fullname, date, pm_time_out) 
                           VALUES (?, ?, ?, ?)";
        } else {
            $sql_insert = "INSERT INTO $table_name (username, fullname, date, am_time_out) 
                           VALUES (?, ?, ?, ?)";
        }
    }

    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("ssss", $username, $fullname, $date, $time_24);
    $stmt_insert->execute();
    $stmt_insert->close();
}

$conn->close();

echo "Data saved successfully";
?>
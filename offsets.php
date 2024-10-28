<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session at the very beginning
session_start();

// Include your connection script
include 'connection.php';

$login_successful = false;
$modal_message = 'There is a previously filed document that overlaps with the current one.'; // Reason for failure
$additional_message = ''; // New variable for the additional message
$success = false;
$status = "Pending";

// function compressImage($source, $destination, $quality)
// {
//     $info = getimagesize($source);

//     if ($info['mime'] == 'image/jpeg') {
//         $image = imagecreatefromjpeg($source);
//         imagejpeg($image, $destination, $quality);
//     } elseif ($info['mime'] == 'image/png') {
//         $image = imagecreatefrompng($source);
//         imagepng($image, $destination, floor($quality / 10));
//     }
//     return $destination;
// }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $modal_message = 'All fields are required.';
    } else {
        $stmt = $conn->prepare("SELECT password, fullname FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($stored_password, $fullname);
            $stmt->fetch();

            if ($password === $stored_password) {
                $login_successful = true;

                // Handle file upload
                // if (isset($_FILES['fileUpload'])) {
                //     $file = $_FILES['fileUpload'];
                //     $file_error = $file['error'];
                //     $file_tmp = $file['tmp_name'];
                //     $file_size = $file['size'];
                //     $file_type = mime_content_type($file_tmp);
                //     $destination = $file_tmp;

                //     // Check if the file is an image and larger than 1MB
                //     if (strpos($file_type, 'image/') !== false && $file_size > 1048576) {
                //         // Compress the image
                //         $quality = 75; // Adjust quality from 0 (worst) to 100 (best)
                //         $destination = compressImage($file_tmp, $file_tmp, $quality);
                //     }

                //     // Read the (possibly compressed) file content
                //     $file_data = file_get_contents($destination);

                // Check for upload errors
                // if ($file_error === UPLOAD_ERR_OK) {
                // Determine which form (TO, CTO, Offsets, WFH, WS, OB) was submitted
                $orderType = $_POST['orderType'] ?? '';
                $startDate = '';
                $endDate = '';

                if ($orderType == 'to') {
                    $startDate = $_POST['toStartDate'];
                    $endDate = $_POST['toEndDate'];
                } elseif ($orderType == 'offsets') {
                    $startDate = $_POST['offsetsStartDate'];
                    $endDate = $_POST['offsetsEndDate'];
                } elseif ($orderType == 'wfh') {
                    $startDate = $_POST['wfhStartDate'];
                    $endDate = $_POST['wfhEndDate'];
                } elseif ($orderType == 'ws') {
                    $startDate = $_POST['wsStartDate'];
                    $endDate = $_POST['wsEndDate'];
                } elseif ($orderType == 'ob') {
                    $startDate = $_POST['obStartDate'];
                    $endDate = $_POST['obEndDate'];
                }

                // Check for overlapping dates in all tables
                $overlap_stmt = $conn->prepare("
                            SELECT 1 FROM (
                                SELECT startdate, enddate FROM `to` WHERE username = ? 
                                UNION ALL
                                SELECT startdate, enddate FROM offsets WHERE username = ? 
                                UNION ALL
                                SELECT startdate, enddate FROM wfh WHERE username = ? 
                                UNION ALL
                                SELECT startdate, enddate FROM ws WHERE username = ? 
                                UNION ALL
                                SELECT startdate, enddate FROM ob WHERE username = ? 
                            ) as combined 
                            WHERE (? BETWEEN startdate AND enddate OR ? BETWEEN startdate AND enddate 
                            OR startdate BETWEEN ? AND ? OR enddate BETWEEN ? AND ?)
                        ");
                $overlap_stmt->bind_param(
                    "sssssssssss",
                    $username,
                    $username,
                    $username,
                    $username,
                    $username,
                    $startDate,
                    $endDate,
                    $startDate,
                    $endDate,
                    $startDate,
                    $endDate
                );
                $overlap_stmt->execute();
                $overlap_stmt->store_result();

                if ($overlap_stmt->num_rows > 0) {
                    $modal_message = 'Cannot file this request because there is an overlap with existing entries.';
                } else {
                    // Allow submission
                    if ($orderType == 'to') {
                        $toNumber = $_POST['toNumber'];
                        $toStartDate = $_POST['toStartDate'];
                        $toEndDate = $_POST['toEndDate'];
                    
                        // Insert into the 'to' table
                        $insert_stmt = $conn->prepare("INSERT INTO `to` (number, username, fullname, startdate, enddate, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $insert_stmt->bind_param("sssss", $toNumber, $username, $fullname, $toStartDate, $toEndDate);
                    
                        if ($insert_stmt->execute()) {
                            $modal_message = 'TO saved successfully!';
                    
                            // Determine the correct table name based on the start date
                            $monthYearTable = strtolower(date('F', strtotime($toStartDate))) . date('Y', strtotime($toStartDate)); // e.g., october2024
                    
                            // Loop through the date range
                            $startDate = new DateTime($toStartDate);
                            $endDate = new DateTime($toEndDate);
                            $endDate->modify('+1 day'); // Include end date
                    
                            // Prepare to insert into the month-year table
                            $insert_month_stmt = $conn->prepare("INSERT INTO `$monthYearTable` (username, fullname, remarks, date) VALUES (?, ?, ?, ?)");
                    
                            while ($startDate < $endDate) {
                                $currentDate = $startDate->format('Y-m-d');
                                $remarksWithTO = 'TO ' . $toNumber;
                                $insert_month_stmt->bind_param("ssss", $username, $fullname, $remarksWithTO, $currentDate);
                    
                                if (!$insert_month_stmt->execute()) {
                                    $modal_message = 'Error saving to month-year table: ' . $conn->error;
                                    break; // Exit the loop if there's an error
                                }
                    
                                // Increment the date
                                $startDate->modify('+1 day');
                            }
                    
                            $insert_month_stmt->close();
                            $success = true;
                        } else {
                            $modal_message = 'Error saving TO: ' . $conn->error;
                        }
                    
                        $insert_stmt->close();
                    
                    } elseif ($orderType == 'offsets') {

                        $offsetsStartDate = $_POST['offsetsStartDate'];
                        $offsetsEndDate = $_POST['offsetsEndDate'];
                    
                        // Insert into the 'offsets' table
                        $insert_stmt = $conn->prepare("INSERT INTO offsets (username, fullname, startdate, enddate, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $insert_stmt->bind_param("ssss", $username, $fullname, $offsetsStartDate, $offsetsEndDate);
                    
                        if ($insert_stmt->execute()) {
                            $modal_message = 'CTO saved successfully!';
                    
                            // Determine the correct table name based on the start date
                            $monthYearTable = strtolower(date('F', strtotime($offsetsStartDate))) . date('Y', strtotime($offsetsStartDate)); // e.g., october2024
                    
                            // Loop through the date range
                            $startDate = new DateTime($offsetsStartDate);
                            $endDate = new DateTime($offsetsEndDate);
                            $endDate->modify('+1 day'); // Include the end date
                    
                            // Prepare to insert into the month-year table
                            $insert_month_stmt = $conn->prepare("INSERT INTO `$monthYearTable` (username, fullname, remarks, date) VALUES (?, ?, ?, ?)");
                    
                            while ($startDate < $endDate) {
                                $currentDate = $startDate->format('Y-m-d');
                                $remarksWithOffset = 'CTO'; // Set remarks as 'OFFSET'
                                $insert_month_stmt->bind_param("ssss", $username, $fullname, $remarksWithOffset, $currentDate);
                    
                                if (!$insert_month_stmt->execute()) {
                                    $modal_message = 'Error saving to month-year table: ' . $conn->error;
                                    break; // Exit loop if there's an error
                                }
                    
                                // Increment the date
                                $startDate->modify('+1 day');
                            }
                    
                            $insert_month_stmt->close();
                            $success = true;
                        } else {
                            $modal_message = 'Error saving CTO: ' . $conn->error;
                        }
                    
                        $insert_stmt->close();
                    } elseif ($orderType == 'wfh') {
                        $wfhStartDate = $_POST['wfhStartDate'];
                        $wfhEndDate = $_POST['wfhEndDate'];
                    
                        // Insert into the 'wfh' table
                        $insert_stmt = $conn->prepare("INSERT INTO `wfh` (username, fullname, startdate, enddate, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $insert_stmt->bind_param("ssss", $username, $fullname, $wfhStartDate, $wfhEndDate);
                    
                        if ($insert_stmt->execute()) {
                            $modal_message = 'WFH saved successfully!';
                    
                            // Determine the correct table name based on the start date
                            $monthYearTable = strtolower(date('F', strtotime($wfhStartDate))) . date('Y', strtotime($wfhStartDate)); // e.g., october2024
                    
                            // Loop through the date range
                            $startDate = new DateTime($wfhStartDate);
                            $endDate = new DateTime($wfhEndDate);
                            $endDate->modify('+1 day'); // Include end date
                    
                            // Prepare to insert into the month-year table
                            $insert_month_stmt = $conn->prepare("INSERT INTO `$monthYearTable` (username, fullname, remarks, date) VALUES (?, ?, ?, ?)");
                    
                            while ($startDate < $endDate) {
                                $currentDate = $startDate->format('Y-m-d');
                                $remarksWithWFH = 'WFH'; // Set remarks as 'WFH'
                                $insert_month_stmt->bind_param("ssss", $username, $fullname, $remarksWithWFH, $currentDate);
                    
                                if (!$insert_month_stmt->execute()) {
                                    $modal_message = 'Error saving to month-year table: ' . $conn->error;
                                    break; // Exit the loop if there's an error
                                }
                    
                                // Increment the date
                                $startDate->modify('+1 day');
                            }
                    
                            $insert_month_stmt->close();
                            $success = true;
                        } else {
                            $modal_message = 'Error saving WFH: ' . $conn->error;
                        }
                    
                        $insert_stmt->close();
                    } elseif ($orderType == 'ws') {

                        $wsStartDate = $_POST['wsStartDate'];
                        $wsEndDate = $_POST['wsEndDate'];
                    
                        // Insert into the 'ws' table
                        $insert_stmt = $conn->prepare("INSERT INTO ws (username, fullname, startdate, enddate, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $insert_stmt->bind_param("ssss", $username, $fullname, $wsStartDate, $wsEndDate);
                    
                        if ($insert_stmt->execute()) {
                            $modal_message = 'Saved successfully!';
                    
                            // Determine the correct table name based on the start date
                            $monthYearTable = strtolower(date('F', strtotime($wsStartDate))) . date('Y', strtotime($wsStartDate)); // e.g., october2024
                    
                            // Loop through the date range
                            $startDate = new DateTime($wsStartDate);
                            $endDate = new DateTime($wsEndDate);
                            $endDate->modify('+1 day'); // Include the end date
                    
                            // Prepare to insert into the month-year table
                            $insert_month_stmt = $conn->prepare("INSERT INTO `$monthYearTable` (username, fullname, remarks, date) VALUES (?, ?, ?, ?)");
                    
                            while ($startDate < $endDate) {
                                $currentDate = $startDate->format('Y-m-d');
                                $remarksWithWS = 'Work Suspended'; // Set remarks as 'WS'
                                $insert_month_stmt->bind_param("ssss", $username, $fullname, $remarksWithWS, $currentDate);
                    
                                if (!$insert_month_stmt->execute()) {
                                    $modal_message = 'Error saving to month-year table: ' . $conn->error;
                                    break; // Exit loop if there's an error
                                }
                    
                                // Increment the date
                                $startDate->modify('+1 day');
                            }
                    
                            $insert_month_stmt->close();
                            $success = true;
                        } else {
                            $modal_message = 'Error saving WS: ' . $conn->error;
                        }
                    
                        $insert_stmt->close();
                    
                    } elseif ($orderType == 'ob') {

                        $obStartDate = $_POST['obStartDate'];
                        $obEndDate = $_POST['obEndDate'];
                    
                        // Insert into the 'ob' table
                        $insert_stmt = $conn->prepare("INSERT INTO ob (username, fullname, startdate, enddate, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $insert_stmt->bind_param("ssss", $username, $fullname, $obStartDate, $obEndDate);
                    
                        if ($insert_stmt->execute()) {
                            $modal_message = 'Saved successfully!';
                    
                            // Determine the correct table name based on the start date
                            $monthYearTable = strtolower(date('F', strtotime($obStartDate))) . date('Y', strtotime($obStartDate)); // e.g., october2024
                    
                            // Loop through the date range
                            $startDate = new DateTime($obStartDate);
                            $endDate = new DateTime($obEndDate);
                            $endDate->modify('+1 day'); // Include the end date
                    
                            // Prepare to insert into the month-year table
                            $insert_month_stmt = $conn->prepare("INSERT INTO `$monthYearTable` (username, fullname, remarks, date) VALUES (?, ?, ?, ?)");
                    
                            while ($startDate < $endDate) {
                                $currentDate = $startDate->format('Y-m-d');
                                $remarksWithOB = 'OB'; // Set remarks as 'OB'
                                $insert_month_stmt->bind_param("ssss", $username, $fullname, $remarksWithOB, $currentDate);
                    
                                if (!$insert_month_stmt->execute()) {
                                    $modal_message = 'Error saving to month-year table: ' . $conn->error;
                                    break; // Exit loop if there's an error
                                }
                    
                                // Increment the date
                                $startDate->modify('+1 day');
                            }
                    
                            $insert_month_stmt->close();
                            $success = true;
                        } else {
                            $modal_message = 'Error saving OB: ' . $conn->error;
                        }
                    
                        $insert_stmt->close();
                    
                    
                    }
                }

                $overlap_stmt->close();
                // } else {
                //     // Detailed error handling
                //     switch ($file_error) {
                //         case UPLOAD_ERR_INI_SIZE:
                //         case UPLOAD_ERR_FORM_SIZE:
                //             $modal_message = 'File is too large.';
                //             break;
                //         case UPLOAD_ERR_PARTIAL:
                //             $modal_message = 'File was only partially uploaded.';
                //             break;
                //         case UPLOAD_ERR_NO_FILE:
                //             $modal_message = 'No file was uploaded.';
                //             break;
                //         case UPLOAD_ERR_NO_TMP_DIR:
                //             $modal_message = 'Missing a temporary folder.';
                //             break;
                //         case UPLOAD_ERR_CANT_WRITE:
                //             $modal_message = 'Failed to write file to disk.';
                //             break;
                //         case UPLOAD_ERR_EXTENSION:
                //             $modal_message = 'File upload stopped by extension.';
                //             break;
                //         default:
                //             $modal_message = 'Unknown upload error.';
                //             break;
                //     }
                //     }
                // } else {
                //     $modal_message = 'File upload is required.';
                // }
            } else {
                $modal_message = 'Invalid Username or Password';
            }
        } else {
            $modal_message = 'Invalid Username or Password';
        }

        $stmt->close();
    }

    $_SESSION['modal_message'] = $modal_message;
    $_SESSION['additional_message'] = $additional_message; // Save additional message to session
    $_SESSION['success'] = $success;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_SESSION['modal_message'])) {
    $modal_message = $_SESSION['modal_message'];
    $additional_message = $_SESSION['additional_message']; // Retrieve additional message
    $success = $_SESSION['success'];
    if ($success) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showModal('success', '$modal_message', '$additional_message'); });</script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showModal('error', '$modal_message', '$additional_message'); });</script>";
    }
    unset($_SESSION['modal_message']);
    unset($_SESSION['additional_message']); // Clear additional message from session
    unset($_SESSION['success']);
}
?>





<!DOCTYPE html>
<html>

<head>
    <title>DICT Daily Time Record - TO/CTO</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="icon" href="images/dict-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script>
        function disableSubmitButton() {
            document.getElementById('submitBtn').disabled = true;
        }

        function toggleFields() {
            const fields = {
                to: document.getElementById('toFields'),
                cto: document.getElementById('offsetsFields'),
                wfh: document.getElementById('wfhFields'),
                ws: document.getElementById('wsFields'),
                ob: document.getElementById('obFields')
            };
            const selectedOption = document.getElementById('orderType').value;

            Object.keys(fields).forEach(key => {
                const field = fields[key];
                field.style.display = (key === selectedOption) ? 'block' : 'none';
                field.querySelectorAll('input').forEach(input => {
                    input.required = (key === selectedOption);
                });
            });
        }



        function prepareToNumber() {
            var part1 = "R1";
            var part2 = document.getElementById('toNumber2').value;
            var part3 = document.getElementById('toNumber3').value;
            document.getElementById('toNumber').value = part1 + '-' + part2 + '-' + part3;
        }

        function showModal(type, message, additionalMessage) {
            var modalOverlay = document.getElementById('modalOverlay');
            var modal;
            if (type === 'success') {
                modal = document.getElementById('successModal');
                document.querySelector('#successModal p').innerHTML =
                    `${message} 
        <br><br> 
        <span style="font-size: 0.9em; color: gray;">${additionalMessage}</span>`;

                // Success modal can auto-close after 3 seconds
                setTimeout(function() {
                    modal.style.display = 'none';
                    modalOverlay.style.display = 'none';
                }, 3000);

            } else if (type === 'error') {
                modal = document.getElementById('errorModal');
                document.querySelector('#errorModal p').innerHTML =
                    `Submit Failed<br><br> 
        <span style="font-size: 0.9em; color: gray;">${message}</span>`;

                // Error modal should only close when the "OK" button is clicked
                document.getElementById('errorModalOkButton').addEventListener('click', function() {
                    modal.style.display = 'none';
                    modalOverlay.style.display = 'none';
                });
            }
            modal.style.display = 'block';
            modalOverlay.style.display = 'block';
        }




        function preventSpace(event) {
            if (event.key === ' ') {
                event.preventDefault();
            }
        }

        function showConfirmationModal(event) {
            event.preventDefault();
            document.getElementById('confirmationModal').style.display = 'block';
            document.getElementById('modalOverlay').style.display = 'block';


        }

        function hideConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            document.getElementById('modalOverlay').style.display = 'none';

        }

        function confirmSubmission() {
            prepareToNumber();
            disableSubmitButton();
            document.querySelector('form').submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('input[name="orderType"]:checked')) {
                toggleFields();
            }
        });

        function togglePw(fieldId) {
            var field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
            } else {
                field.type = "password";
            }
        }
    </script>
</head>

<body>
    <div class="container">
        <div class="logos">
            <img src="images/DICT-Logo-Final.png" alt="Logo">
            <img src="images/bagong-fil.png" alt="Logo" id="bagongfil">
        </div>
        <div class="title">
            <h2>TO/CTO/WFH/WS/OB Form</h2>
        </div>
        <form action="" method="post" onsubmit="showConfirmationModal(event);" enctype="multipart/form-data">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <div class="showPw">
                    <input type="checkbox" onclick="togglePw('password')">Show Password
                </div>
            </div>

            <!-- <div class="form-group">
                <label for="fileUpload" hidden>Upload File:</label>
                <input type="file" id="fileUpload" name="fileUpload" accept="image/*,.pdf,.doc,.docx" hidden>
            </div> -->

            <div class="form-group">
                <label for="orderType">Select Type:</label>
                <select id="orderType" name="orderType" onchange="toggleFields()" required>
                    <option value="" disabled selected>Select an option</option>
                    <option value="to">TO</option>
                    
                    <option value="wfh">WFH</option>
                    <option value="ws">WS</option>
                    <option value="ob">OB</option>
                </select>
            </div>

            <div id="toFields" style="display:none;">
                <div class="form-group">
                    <div class="to-number-input">
                        <label for="toNumber1">TO No.:</label>
                        <div class="to-input-container">
                            <span class="static-text">R1</span>
                            <span>-</span>
                            <input type="text" id="toNumber2" name="toNumber2" required onkeydown="preventSpace(event)">
                            <span>-</span>
                            <input type="text" id="toNumber3" name="toNumber3" required onkeydown="preventSpace(event)">
                            <input type="hidden" id="toNumber" name="toNumber">
                        </div>
                        <div class="form-guide">
                            <i>Please follow the format: R1-xxxx-xxxx.</i>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="toStartDate">Start Date:</label>
                    <input type="date" id="toStartDate" name="toStartDate">
                </div>
                <div class="form-group">
                    <label for="toEndDate">End Date:</label>
                    <input type="date" id="toEndDate" name="toEndDate">
                </div>
            </div>
            <div id="offsetsFields" style="display:none;">
                
                <div class="form-group">
                    <label for="offsetsStartDate">Start Date:</label>
                    <input type="date" id="offsetsStartDate" name="offsetsStartDate">
                </div>
                <div class="form-group">
                    <label for="offsetsEndDate">End Date:</label>
                    <input type="date" id="offsetsEndDate" name="offsetsEndDate">
                </div>
            </div>

            <div id="wfhFields" style="display:none;">
                
                <div class="form-group">
                    <label for="wfhStartDate">Start Date:</label>
                    <input type="date" id="wfhStartDate" name="wfhStartDate">
                </div>
                <div class="form-group">
                    <label for="wfhEndDate">End Date:</label>
                    <input type="date" id="wfhEndDate" name="wfhEndDate">
                </div>
            </div>

            <div id="wsFields" style="display:none;">
                
                <div class="form-group">
                    <label for="wsStartDate">Start Date:</label>
                    <input type="date" id="wsStartDate" name="wsStartDate">
                </div>
                <div class="form-group">
                    <label for="wsEndDate">End Date:</label>
                    <input type="date" id="wsEndDate" name="wsEndDate">
                </div>
            </div>

            <div id="obFields" style="display:none;">
                
                <div class="form-group">
                    <label for="obStartDate">Start Date:</label>
                    <input type="date" id="obStartDate" name="obStartDate">
                </div>
                <div class="form-group">
                    <label for="obEndDate">End Date:</label>
                    <input type="date" id="obEndDate" name="obEndDate">
                </div>
            </div>
            <input type="submit" id="submitBtn" value="Submit">
        </form>
        <button id="backButton" onclick="window.location.href='index.php';">Back</button>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="animation-container">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark__circle" cx="26" cy="26" r="25" />
                    <path class="checkmark__check" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                </svg>
            </div>
            <p><?php echo $modal_message; ?></p>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <div class="animation-container">
                <svg class="crossmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="crossmark__circle" cx="26" cy="26" r="25" />
                    <path class="crossmark__cross" d="M16 16 L36 36 M36 16 L16 36" />
                </svg>
            </div>
            <p style="color:red;"><?php echo $modal_message; ?></p>
            <button id="errorModalOkButton" style="margin-top: 20px; padding: 10px 20px; background-color: #FF0000; color: white; border: none; cursor: pointer;">OK</button>

        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="animation-container">
                <i class="fa fa-warning orange-color" style="font-size: 48px; color: orange;"></i>
            </div>
            <p><b>Warning!</b> Please double-check the your input information. Make sure it is correct and <b>valid.</b></p>
            <div class="button-container">
                <button id="confirmBtn" onclick="confirmSubmission()">Confirm</button>
                <button id="cancelBtn" onclick="hideConfirmationModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Overlay -->
    <div id="modalOverlay" class="overlay"></div>
</body>

</html>
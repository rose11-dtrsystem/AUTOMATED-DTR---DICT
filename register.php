<?php
session_start();
include 'connection.php';

$registration_successful = false;
$error_message = "";
$status = "Pending";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $fullname = $_POST['fullname'];
    $contract = $_POST['orderType'];  // Correct variable name for contract type
    $position = $_POST['position'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($username) || empty($fullname) || empty($password) || empty($confirm_password) || empty($contract) || empty($position)) {
        $_SESSION['error_message'] = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Passwords do not match.";
    } else {
        // Check if username is already taken in either users or pending_users table
        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ? UNION SELECT username FROM pending_users WHERE username = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error_message'] = "Username is already taken.";
        } else {
            // Prepare SQL query to insert user into pending_users table
            $stmt = $conn->prepare("INSERT INTO pending_users (username, fullname, contract, position, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $fullname, $contract, $position, $password);

            if ($stmt->execute()) {
                $_SESSION['registration_successful'] = true;
            } else {
                $_SESSION['error_message'] = "Error: " . $stmt->error;
            }
        }

        // Close the statement
        $stmt->close();
    }

    // Close the connection
    $conn->close();

    // Redirect to the same page to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Check session for messages
if (isset($_SESSION['registration_successful'])) {
    $registration_successful = $_SESSION['registration_successful'];
    unset($_SESSION['registration_successful']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>



<!DOCTYPE html>
<html>

<head>
    <title>DICT Daily Time Record - Register</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="icon" href="images/dict-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>

    </style>
    <script>
        function showConfirmationModal(event) {
            event.preventDefault();
            var modal = document.getElementById('confirmationModal');
            var overlay = document.getElementById('modalOverlay');
            modal.style.display = 'block';
            overlay.style.display = 'block';
        }

        function hideConfirmationModal() {
            var modal = document.getElementById('confirmationModal');
            var overlay = document.getElementById('modalOverlay');
            modal.style.display = 'none';
            overlay.style.display = 'none';
        }

        function confirmSubmission() {
            hideConfirmationModal();
            document.getElementById('registrationForm').submit();
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
            <h2>Registration Form</h2>
        </div>

        <form id="registrationForm" action="" method="post" onsubmit="showConfirmationModal(event)">
            <div class="form-group">
                <label for="fullname">Full Name:</label>
                <div class="input-icon">
                    <i class="fa-regular fa-user fa-sm" style="color: #63E6BE;"></i>

                    <input type="text" id="fullname" name="fullname" placeholder="e.g. John D. Doe" required>
                </div>

                <div class="form-guide">
                    <i>Please follow the format: First Name M.I. Surname/Last Name.</i>
                </div>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <div class="input-icon">
                    <i class="fa-solid fa-user fa-sm" style="color: #63E6BE;"></i>
                    <input type="text" id="username" name="username" placeholder="e.g. JDoe" required>
                </div>
                <div class="form-guide">
                    <i>Any will do, as long as you can remember it.</i>
                </div>
            </div>

            <div class="form-group">
                <label for="orderType">Type of Contract</label>
                <div class="input-icon">
                    <i class="fa-solid fa-file-contract fa-sm" style="color: #63E6BE;"></i>
                    <select id="orderType" name="orderType" style="padding-left: 20px;" required>
                        <option value="" disabled selected>Select an option</option>
                        <option value="Job Order">Job Order</option>
                        <option value="Plantilla">Plantilla</option>

                    </select>
                </div>
            </div>


            <div class="form-group">
                <label for="position">Position:</label>
                <div class="input-icon">
                    <i class="fa-solid fa-user fa-sm" style="color: #63E6BE;"></i>
                    <input type="text" id="position" name="position" placeholder="e.g. Information Technology Officer III" required>
                </div>
                <div class="form-guide">
                    <i>Please input the full abbreviation of your position.</i>
                </div>
            </div>



            <div class="form-group">
                <label for="password">Password:</label>
                <div class="input-icon">
                    <i class="fa-solid fa-lock fa-sm" style="color: #63E6BE;"></i>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="showPw">
                    <input type="checkbox" onclick="togglePw('password')">Show Password
                </div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <div class="input-icon">
                    <i class="fa-solid fa-lock fa-sm" style="color: #63E6BE;"></i>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="showPw">
                    <input type="checkbox" onclick="togglePw('confirm_password')">Show Password
                </div>
            </div>
            <input type="submit" id="registerButton" value="Register">
        </form>
        <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="animation-container">
                <i class="fa fa-warning orange-color" style="font-size: 48px; color: orange;"></i>
            </div>
            <p><b>Warning!</b> Please double check your <b>credentials.</b></p>
            <div class="button-container">
                <button id="confirmBtn" onclick="confirmSubmission()">Confirm</button>
                <button id="cancelBtn" onclick="hideConfirmationModal()">Cancel</button>
            </div>
        </div>
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
            <p>Registration successful!</p>
            <p style="color: gray;">Please wait for the admin to approve your registration.</p>
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
            <p style="color:red;"><?php echo $error_message; ?></p>
        </div>
    </div>

    <!-- Overlay -->
    <div id="modalOverlay" class="overlay"></div>

    <script>
        window.onload = function() {
            var successModal = document.getElementById('successModal');
            var errorModal = document.getElementById('errorModal');
            var modalOverlay = document.getElementById('modalOverlay');

            <?php if ($registration_successful): ?>
                successModal.style.display = 'block';
                modalOverlay.style.display = 'block';
                setTimeout(function() {
                    successModal.style.display = 'none';
                    modalOverlay.style.display = 'none';
                    // Redirect to login.php after the success modal
                    window.location.href = "index.php";
                }, 3000);
            <?php elseif (!empty($error_message)): ?>
                errorModal.style.display = 'block';
                modalOverlay.style.display = 'block';
                setTimeout(function() {
                    errorModal.style.display = 'none';
                    modalOverlay.style.display = 'none';
                }, 3000);
            <?php endif; ?>
        }

        function togglePw(fieldId) {
            var field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
            } else {
                field.type = "password";
            }
        }
    </script>
</body>

</html>
<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['message'] = '<p style="color:red;">All fields are required.</p>';
    } else {
        // Special case for admin login
        if ($username === 'dictro1admin' && $password === 'dictro1') {
            $_SESSION['login_success'] = true;
            $_SESSION['username'] = $username; // Store username in session
            header("Location: dictr1admin/dashboard.php");
            exit();
        }

        $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($stored_password);
            $stmt->fetch();

            if ($password === $stored_password) {
                $_SESSION['login_success'] = true;
                $_SESSION['username'] = $username; // Store username in session
                header("Location: dtr.php");
                exit();
            } else {
                $_SESSION['message'] = '<div id="loginErrorModal" class="modal">
                                            <div class="modal-content">
                                                <div class="animation-container">
                                                    <svg class="crossmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                                        <circle class="crossmark__circle" cx="26" cy="26" r="25"/>
                                                        <path class="crossmark__cross" d="M16 16 L36 36 M36 16 L16 36"/>
                                                    </svg>
                                                </div>
                                                <p style="color:red;">Invalid username or password.</p>
                                            </div>
                                        </div>
                                        <div id="loginModalOverlay" class="overlay"></div>';
            }
        } else {
            $_SESSION['message'] = '<div id="loginErrorModal" class="modal">
                                        <div class="modal-content">
                                            <div class="animation-container">
                                                <svg class="crossmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                                    <circle class="crossmark__circle" cx="26" cy="26" r="25"/>
                                                    <path class="crossmark__cross" d="M16 16 L36 36 M36 16 L16 36"/>
                                                </svg>
                                            </div>
                                            <p style="color:red;">Invalid username or password.</p>
                                        </div>
                                    </div>
                                    <div id="loginModalOverlay" class="overlay"></div>';
        }
        $stmt->close();
    }
    $conn->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>DICT Daily Time Record - Login</title>
    <link rel="stylesheet" type="text/css" href="style.css?v=1.0">
    <link rel="icon" href="images/dict-icon.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.0.0/moment.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>
    <div class="container">
        <div class="logos">
            <img src="images/DICT-Logo-Final.png" alt="Logo">
            <img src="images/bagong-fil.png" alt="Logo" id="bagongfil">
        </div>
        <div class="title">
            <h2>Daily Time Record</h2>
        </div>

        <div id="clock" class="light">
            <div class="display">
                <div class="weekdays"></div>
                <div class="ampm"></div>
                <div class="digits"></div>
            </div>
        </div>
        <form action="" method="post">
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
                <label for="password">Password:</label>
                <div class="input-icon">
                    <i class="fa-solid fa-lock fa-sm" style="color: #63E6BE;"></i>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="showPw">
                    <input type="checkbox" onclick="togglePw('password')">Show Password
                </div>
            </div>
            <input type="submit" id="loginBtn" value="Login">
        </form>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
        <button id="travelOrderButton" onclick="window.location.href='offsets.php';">TO/CTO/WFH/WS/OB</button>
    </div>


    <script>
        window.onload = function() {
            var successModal = document.getElementById('loginSuccessModal');
            var errorModal = document.getElementById('loginErrorModal');
            var loginModalOverlay = document.getElementById('loginModalOverlay');

            if (successModal) {
                successModal.style.display = 'block';
                loginModalOverlay.style.display = 'block';
                setTimeout(function() {
                    successModal.style.display = 'none';
                    loginModalOverlay.style.display = 'none';
                }, 3000);
            }

            if (errorModal) {
                errorModal.style.display = 'block';
                loginModalOverlay.style.display = 'block';
                setTimeout(function() {
                    errorModal.style.display = 'none';
                    loginModalOverlay.style.display = 'none';
                }, 3000);
            }
        }

        $(function() {
            var clock = $('#clock'),
                ampm = clock.find('.ampm'),
                digits = {},
                weekday_names = 'SUN MON TUE WED THU FRI SAT'.split(' '),
                weekday_holder = clock.find('.weekdays'),
                digit_to_name = 'zero one two three four five six seven eight nine'.split(' '),
                positions = ['h1', 'h2', ':', 'm1', 'm2', ':', 's1', 's2'];

            // Create digit elements and append to the clock
            var digit_holder = clock.find('.digits');
            $.each(positions, function() {
                if (this == ':') {
                    digit_holder.append('<div class="dots">');
                } else {
                    var pos = $('<div>');
                    for (var i = 1; i < 8; i++) {
                        pos.append('<span class="d' + i + '">');
                    }
                    digits[this] = pos;
                    digit_holder.append(pos);
                }
            });

            // Add the weekday names
            $.each(weekday_names, function() {
                weekday_holder.append('<span>' + this + '</span>');
            });
            var weekdays = clock.find('.weekdays span');

            // Function to update the time
            function update_time() {
                $.ajax({
                    url: 'get_time.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        var hours = response.hours,
                            minutes = response.minutes,
                            seconds = response.seconds,
                            ampmText = response.ampm,
                            weekday = response.weekday;

                        digits.h1.attr('class', digit_to_name[hours[0]]);
                        digits.h2.attr('class', digit_to_name[hours[1]]);
                        digits.m1.attr('class', digit_to_name[minutes[0]]);
                        digits.m2.attr('class', digit_to_name[minutes[1]]);
                        digits.s1.attr('class', digit_to_name[seconds[0]]);
                        digits.s2.attr('class', digit_to_name[seconds[1]]);

                        // Update the active day of the week
                        weekdays.removeClass('active').each(function(index, elem) {
                            if ($(elem).text() === weekday.toUpperCase()) {
                                $(elem).addClass('active');
                            }
                        });

                        // Set the am/pm text
                        ampm.text(ampmText);
                    }
                });
            }

            // Run update_time every second
            setInterval(update_time, 1000);

            // Update current date
            const today = new Date();
            const dateString = today.toISOString().split('T')[0];
            document.getElementById('currentDate').innerText = dateString;
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
</body>

</html>
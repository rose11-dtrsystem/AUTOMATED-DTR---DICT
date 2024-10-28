<?php
session_start();
include 'connection.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['username'])) {
  header("Location: index.php");
  exit();
}

$username = $_SESSION['username'];

// Fetch the full name and contract using the stored username
$stmt = $conn->prepare("SELECT fullname, contract, position FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($full_name, $contract, $position);
$stmt->fetch();
$stmt->close();

$today_date = date("Y-m-d");

// Generate the table name based on the current month and year
$current_year = date("Y");
$current_month = strtolower(date("F")); // Get the full month name in lowercase
$table_name = $current_month . $current_year; // e.g., 'october2024'

// Check if the table exists, if not, create it
$table_check_stmt = $conn->prepare("SHOW TABLES LIKE '$table_name'");
$table_check_stmt->execute();
$table_check_stmt->store_result();

if ($table_check_stmt->num_rows === 0) {
    // Table does not exist, create it
    $create_table_stmt = $conn->prepare("CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50),
        fullname VARCHAR(100),
        date DATE,
        am_time_in TIME,
        am_time_out TIME,
        pm_time_in TIME,
        pm_time_out TIME,
        remarks VARCHAR(150)
    )");

    if (!$create_table_stmt->execute()) {
        die("Error creating table: " . $conn->error);
    }
    $create_table_stmt->close();
}

// Check if there's already a record for today, if not, insert a new one
$stmt = $conn->prepare("SELECT COUNT(*) FROM $table_name WHERE username = ? AND date = ?");
$stmt->bind_param("ss", $username, $today_date);
$stmt->execute();
$stmt->bind_result($record_count);
$stmt->fetch();
$stmt->close();

if ($record_count == 0) {
    $stmt = $conn->prepare("INSERT INTO $table_name (username, fullname, date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $full_name, $today_date);
    $stmt->execute();
    $stmt->close();
}

// Fetch today's AM/PM clock-in and clock-out statuses
$stmt = $conn->prepare("SELECT am_time_in, am_time_out, pm_time_in, pm_time_out FROM $table_name WHERE username = ? AND date = ?");
$stmt->bind_param("ss", $username, $today_date);
$stmt->execute();
$stmt->bind_result($am_in, $am_out, $pm_in, $pm_out);
$stmt->fetch();
$stmt->close();


// Check if the user has applied for TO/CTO/WS/OB
$disable_check_location = false;
$reason = "";

$tables = ["to", "offsets", "ws", "ob"];
foreach ($tables as $table) {
  $stmt = $conn->prepare("SELECT startdate, enddate, status FROM `$table` WHERE username = ? AND ? BETWEEN startdate AND enddate AND status = 'Approved'");
  $stmt->bind_param("ss", $username, $today_date);
  $stmt->execute();
  $stmt->bind_result($startdate, $enddate, $status);

  if ($stmt->fetch()) {
    $disable_check_location = true;
    $reason = strtoupper($table) . " Dates: $startdate to $enddate";
    $stmt->close();
    break;
  }

  $stmt->close();
}

// Check if the user has applied for WFH and it's approved
$stmt = $conn->prepare("SELECT startdate, enddate, status FROM wfh WHERE username = ? AND ? BETWEEN startdate AND enddate AND status = 'Approved'");
$stmt->bind_param("ss", $username, $today_date);
$stmt->execute();
$stmt->bind_result($wfh_startdate, $wfh_enddate, $status);
$wfh_applied = $stmt->fetch();
$stmt->close();

// Fetch existing time records for the user
$stmt = $conn->prepare("SELECT * FROM  $table_name WHERE username = ? AND date = ?");
$stmt->bind_param("ss", $username, $today_date);
$stmt->execute();
$result_records = $stmt->get_result();

// Function to calculate hours worked between two times and return the total as a float
function calculate_hours($start_time, $end_time)
{
  if (empty($start_time) || empty($end_time)) {
    return 0.00;
  }

  $start = new DateTime($start_time);
  $end = new DateTime($end_time);
  $interval = $start->diff($end);

  // Convert interval to total hours as a float
  $total_hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);

  // Cap the total hours to 5 hours
  return min($total_hours, 5.00);
}

function calculate_time_difference($start_time, $end_time)
{
  if (empty($start_time) || empty($end_time)) {
    return 0;
  }

  $start = new DateTime($start_time);
  $end = new DateTime($end_time);
  $interval = $start->diff($end);

  // Return total difference in seconds
  return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
}


// Function to calculate AM lateness in minutes
function calculate_am_late($am_in)
{
  $standard_am_in = "08:00";

  if (new DateTime($am_in) <= new DateTime($standard_am_in)) {
    return 0;
  }

  return calculate_time_difference($standard_am_in, $am_in);
}

// Function to calculate PM lateness in minutes
function calculate_pm_late($pm_in)
{
  $standard_pm_in = "13:00";

  if (new DateTime($pm_in) <= new DateTime($standard_pm_in)) {
    return 0;
  }

  return calculate_time_difference($standard_pm_in, $pm_in);
}

// Function to convert a float hour value to 12-hour format with AM/PM
function convert_to_12_hour_format($time_string)
{
  // Check if the input is NULL or an empty string
  if (empty($time_string)) {
    return ''; // Return an empty string or some placeholder if time is not available
  }

  // Try to create a DateTime object
  $time = DateTime::createFromFormat('H:i:s', $time_string);

  // Check if DateTime object creation was successful
  if ($time === false) {
    return ''; // Return an empty string or a placeholder for invalid time format
  }

  // Format it to a 12-hour format with AM/PM
  return $time->format('g:i:s');
}



// Prepare data for table display
$records = [];
while ($row = $result_records->fetch_assoc()) {
  // Calculate lateness using the original 24-hour format
  $am_late = !empty($row['am_time_in']) ? calculate_am_late($row['am_time_in']) : 0;
  $pm_late = !empty($row['pm_time_in']) ? calculate_pm_late($row['pm_time_in']) : 0;

  // Calculate total hours using the original 24-hour format
  $am_total = !empty($row['am_time_in']) && !empty($row['am_time_out']) ? calculate_hours($row['am_time_in'], $row['am_time_out']) : 0.00;
  $pm_total = !empty($row['pm_time_in']) && !empty($row['pm_time_out']) ? calculate_hours($row['pm_time_in'], $row['pm_time_out']) : 0.00;

  $total_day_hours = $am_total + $pm_total;
  $total_ut_late = $am_late + $pm_late;

  // Convert times to 12-hour format for display
  $row['am_time_in'] = convert_to_12_hour_format($row['am_time_in']);
  $row['am_time_out'] = convert_to_12_hour_format($row['am_time_out']);
  $row['pm_time_in'] = convert_to_12_hour_format($row['pm_time_in']);
  $row['pm_time_out'] = convert_to_12_hour_format($row['pm_time_out']);

  // Format data for display including seconds
  $row['total_am'] = sprintf("%d:%02d:%02d", floor($am_total), ($am_total * 60) % 60, ($am_total * 3600) % 60);
  $row['total_pm'] = sprintf("%d:%02d:%02d", floor($pm_total), ($pm_total * 60) % 60, ($pm_total * 3600) % 60);
  $row['total_day'] = sprintf("%d:%02d:%02d", floor($total_day_hours), ($total_day_hours * 60) % 60, ($total_day_hours * 3600) % 60);
  $row['ut_late'] = sprintf(
    "%d:%02d:%02d",
    floor($total_ut_late / 3600), // hours
    ($total_ut_late % 3600) / 60, // minutes
    $total_ut_late % 60 // seconds
  );
  $records[] = $row;
}



$stmt->close();
$conn->close();
?>



<!DOCTYPE html>
<html>

<head>
  <title>DICT Daily Time Record</title>
  <link rel="stylesheet" type="text/css" href="style.css?v=1.0">
  <link rel="icon" href="images/dict-icon.png" type="image/x-icon">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.0.0/moment.min.js"></script>


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

    <div class="user">
      <h3><?php echo htmlspecialchars($full_name); ?></h3>
      <h4><?php echo htmlspecialchars($position); ?></h4>

      <p style="margin-top: -20px; color: gray; font-size: 12px;"><?php echo htmlspecialchars($contract); ?></p>

    </div>

    <table class="dtr-table">
      <thead>
        <tr>
          <th rowspan="2">Date</th>
          <th colspan="2">AM</th>
          <th colspan="2">PM</th>
          <?php if ($contract == 'Plantilla'): ?>
            <th rowspan="2">UT/Late</th>
          <?php else: ?>
            <th rowspan="2">Total AM</th>
            <th rowspan="2">Total PM</th>
            <th rowspan="2">Total Day</th>
          <?php endif; ?>
        </tr>
        <tr>
          <th>IN</th>
          <th>OUT</th>
          <th>IN</th>
          <th>OUT</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($records)): ?>
          <?php foreach ($records as $record): ?>
            <tr>
              <td id="currentDate"><?php echo htmlspecialchars($record['date']); ?></td>
              <td><?php echo htmlspecialchars($record['am_time_in']); ?></td>
              <td><?php echo htmlspecialchars($record['am_time_out']); ?></td>
              <td><?php echo htmlspecialchars($record['pm_time_in']); ?></td>
              <td><?php echo htmlspecialchars($record['pm_time_out']); ?></td>
              <?php if ($contract == 'Plantilla'): ?>
                <td><?php echo htmlspecialchars($record['ut_late']); ?></td>
              <?php else: ?>
                <td><?php echo htmlspecialchars($record['total_am']); ?></td>
                <td><?php echo htmlspecialchars($record['total_pm']); ?></td>
                <td><?php echo htmlspecialchars($record['total_day']); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8">No records found</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <a href="view_history.php" class="back-button">View History</a>



    <div id="map"></div>

    <div class="checkLoc">
      <div id="pulse" class="circle pulse blue">
        <i class="fa-solid fa-xl fa-map-location fa-bounce" style="color: #ffffff;"></i>
      </div>
    </div>



    <div id="wfh-info" style="display: flex; justify-content: center; align-items: center; height: 100px;">
      <p>Work From Home (WFH) Applied</p>
    </div>

    <div id="reason" style="display: none;"></div>


    <div class="action-buttons" style="display: none;">
      <button class="dtr-btn btn-green" id="btnIn" onclick="handleClockIn()" disabled>IN</button>
      <button class="dtr-btn btn-red" id="btnOut" onclick="handleClockOut()" disabled>OUT</button>
    </div>

    <a href="index.php" id="sign-out" class="sign-out" onclick="showConfirmationModal(event)">Sign Out</a>


    <div id="locationModal" class="modal">
      <div class="modal-content">
        <div class="animation-container">
          <div class="lds-ripple">
            <div></div>
            <div></div>
          </div>
          <p id="modalMessage">Checking Location. Please wait.</p>
        </div>
      </div>
    </div>
    <div id="locationModalOverlay" class="overlay"></div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
      <div class="modal-content">
        <div class="animation-container">
          <i class="fa fa-warning orange-color" style="font-size: 48px; color: orange;"></i>
        </div>
        <p><b>Warning!</b> Are you sure you want to <b>logout?</b></p>
        <div class="button-container">
          <button id="confirmBtn" onclick="confirmSubmission()">Confirm</button>
          <button id="cancelBtn" onclick="hideConfirmationModal()">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Overlay -->
    <div id="modalOverlay" class="overlay"></div>


    <script>
      if (<?php echo (strpos(strtolower($position), 'chief') !== false ? 'true' : 'false'); ?>){
                  // Hide the check location div and show WFH info
                  $('.checkLoc').hide();
          $('#pulse').hide();
          $('#wfh-info').show();
          $('.action-buttons').show();

      }
      function showConfirmationModal(event) {
        event.preventDefault(); // Prevent the default link action
        var modal = document.getElementById('confirmationModal');
        modal.style.display = 'block';
        var overlay = document.getElementById('modalOverlay');

        overlay.style.display = 'block';

      }

      function hideConfirmationModal() {
        var modal = document.getElementById('confirmationModal');
        modal.style.display = 'none';
        var overlay = document.getElementById('modalOverlay');

        overlay.style.display = 'none';

      }

      function confirmSubmission() {
        window.location.href = 'index.php'; // Redirect to index.php
      }

      $(document).ready(function() {
        var wfhApplied = <?php echo json_encode($wfh_applied); ?>;
        var disableCheckLocation = <?php echo json_encode($disable_check_location); ?>;
        var reason = <?php echo json_encode($reason); ?>;

        if (wfhApplied) {

          // Hide the check location div and show WFH info
          $('.checkLoc').hide();
          $('#pulse').hide();
          $('#wfh-info').show();
          $('.action-buttons').show();

        } else {
          $('#wfh-info').hide();
          if (disableCheckLocation) {
            // Hide the check location div and show the reason
            $('.checkLoc').hide();
            $('#reason').text(reason).show();
          } else {
            // Show the check location div
            $('.checkLoc').show();
            $('#reason').hide();
          }
        }
      });

      // Function to handle Clock In
      function handleClockIn() {

        $.ajax({
          url: 'get_time.php',
          method: 'GET',
          success: function(response) {
            const timeData = JSON.parse(response);
            const timeString = timeData.time;
            const ampm = timeData.ampm;

            // Check if AM In is already filled
            const amInField = document.querySelector('.dtr-table tbody tr td:nth-child(2)').innerText;
            const amOutField = document.querySelector('.dtr-table tbody tr td:nth-child(3)').innerText;
            const pmInField = document.querySelector('.dtr-table tbody tr td:nth-child(4)').innerText;
            const pmOutField = document.querySelector('.dtr-table tbody tr td:nth-child(5)').innerText;



            if (ampm === 'AM' && amInField === '') {
              // Clock in for AM
              document.querySelector('.dtr-table tbody tr td:nth-child(2)').innerText = timeString;

            } else if (ampm === 'PM' && pmInField === '') {
              // Clock in for PM 
              document.querySelector('.dtr-table tbody tr td:nth-child(4)').innerText = timeString;

            }

            // Disable IN button and enable OUT button
            document.getElementById("btnIn").disabled = true;
            document.getElementById("btnOut").disabled = false;

            // Send clock-in data to the server and update UI immediately
            $.ajax({
              url: 'save_dtr.php',
              method: 'POST',
              data: {
                action: 'clock_in',
                date: document.getElementById('currentDate').innerText,
                username: '<?php echo $username; ?>',
                fullname: '<?php echo $full_name; ?>',
                time: timeString,
                ampm: ampm,
                amInField: amInField,
                amOutField: amOutField,
                pmInField: pmInField,
                pmOutField: pmOutField

              },
              success: function(response) {
                console.log("Clock-in data saved:", response);
                // Optionally update the UI based on server response, if needed
                location.reload();
              }
            });
          }
        });
      }

      // Function to handle Clock Out
      function handleClockOut() {
        $.ajax({
          url: 'get_time.php',
          method: 'GET',
          success: function(response) {
            const timeData = JSON.parse(response);
            const timeString = timeData.time;
            const ampm = timeData.ampm;

            // Check if AM In is already filled
            const amInField = document.querySelector('.dtr-table tbody tr td:nth-child(2)').innerText;
            const amOutField = document.querySelector('.dtr-table tbody tr td:nth-child(3)').innerText;
            const pmInField = document.querySelector('.dtr-table tbody tr td:nth-child(4)').innerText;
            const pmOutField = document.querySelector('.dtr-table tbody tr td:nth-child(5)').innerText;


            if (amInField !== '' && amOutField === '') {
              // Clock out for AM
              document.querySelector('.dtr-table tbody tr td:nth-child(3)').innerText = timeString;
            } else if (ampm === 'PM' && pmOutField === '') {
              // Clock out for PM
              document.querySelector('.dtr-table tbody tr td:nth-child(5)').innerText = timeString;
            }

            // Disable OUT button
            document.getElementById("btnOut").disabled = true;

            // Send clock-out data to the server and update UI immediately
            $.ajax({
              url: 'save_dtr.php',
              method: 'POST',
              data: {
                action: 'clock_out',
                date: document.getElementById('currentDate').innerText,
                username: '<?php echo $username; ?>',
                fullname: '<?php echo $full_name; ?>',
                time: timeString,
                ampm: ampm,
                amInField: amInField,
                amOutField: amOutField,
                pmInField: pmInField,
                pmOutField: pmOutField
              },
              success: function(response) {
                console.log("Clock-out data saved:", response);
                // Optionally update the UI based on server response, if needed
                location.reload();
              }
            });
          }
        });
      }

      $(function() {
        // Function to check location and update the UI
        function checkLocation() {
          // Show the modal and overlay while checking location
          $('#locationModal').show();
          $('#locationModalOverlay').show();

          if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(success, error, {
              enableHighAccuracy: true,
              timeout: 5000,
              maximumAge: 0
            });
          } else {
            alert("Geolocation is not supported by this browser.");
          }
        }

        function success(position) {
          var latitude = position.coords.latitude;
          var longitude = position.coords.longitude;
          var accuracy = position.coords.accuracy;

          console.log("User Latitude:", latitude);
          console.log("User Longitude:", longitude);
          console.log("Accuracy (meters):", accuracy);

          // Define the target location coordinates and a radius in meters
          // DICT R1: 16.612546437734476, 120.31630147213215
          // Angel's House for testing: 16.59478992786831, 120.30606497668668
          // Lorma SJ: 16.66128385102643, 120.32833792079539
          // Kristin's House 15.979403, 120.808405
          //Roselyn's House 15.58248, 120.34176
          
          var targetLat = 15.58248;
          var targetLng = 120.34176;
          var radius = 500; // 50 meters radius

          // Calculate the distance between the user's location and the target location
          var distance = getDistanceFromLatLonInMeters(latitude, longitude, targetLat, targetLng);

          console.log("Calculated Distance (meters):", distance);

          // Hide the modal and overlay after checking location
          $('#locationModal').hide();
          $('#locationModalOverlay').hide();

          if (distance <= radius) {
            // If within the target location, hide the blue circle and show the buttons
            $('.circle.pulse.blue').hide();
            $('.action-buttons').show();

            // Show the map and display the user's location
            $('#map').show();
            $('#address').show();

            var map = L.map('map').setView([latitude, longitude], 18);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 19,
            }).addTo(map);

            var marker = L.marker([latitude, longitude]).addTo(map);
            marker.bindPopup("<b>You are here</b>").openPopup();

            // Reverse geocode to get the address
            getAddress(latitude, longitude);
          } else {
            alert("You are not within the target area.");
          }
        }

        function error(err) {
          console.warn(`ERROR(${err.code}): ${err.message}`);
          alert("Unable to retrieve your location.");

          // Hide the modal and overlay after checking location
          $('#locationModal').hide();
          $('#locationModalOverlay').hide();
        }

        // Calculate the distance between two points (latitude, longitude) in meters
        function getDistanceFromLatLonInMeters(lat1, lon1, lat2, lon2) {
          var R = 6371000; // Radius of the Earth in meters
          var dLat = deg2rad(lat2 - lat1);
          var dLon = deg2rad(lon2 - lon1);
          var a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
          var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
          var distance = R * c; // Distance in meters
          return distance;
        }

        function deg2rad(deg) {
          return deg * (Math.PI / 180);
        }

        // Function to get address from latitude and longitude
        function getAddress(lat, lon) {
          var url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`;
          $.getJSON(url, function(data) {
            var address = data.display_name;
            $('#address').text("Your Address: " + address);
          });
        }

        // Attach click event to the circle
        $('.circle.pulse.blue').click(function() {
          checkLocation();
        });
      });


      function togglePw(fieldId) {
        var field = document.getElementById(fieldId);
        if (field.type === "password") {
          field.type = "text";
        } else {
          field.type = "password";
        }
      }

      function getCurrentTimeInManila() {
        return new Intl.DateTimeFormat('en-PH', {
          timeZone: 'Asia/Manila',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: false
        }).format(new Date());
      }

      function getCurrentDateInManila() {
        return new Intl.DateTimeFormat('en-PH', {
          timeZone: 'Asia/Manila',
          year: 'numeric',
          month: '2-digit',
          day: '2-digit'
        }).format(new Date()).split('/').reverse().join('-'); // Format YYYY-MM-DD
      }

      function updateButtonStates() {
        // Fetching clock-in/clock-out data from PHP
        const amIn = "<?php echo $am_in; ?>";
        const amOut = "<?php echo $am_out; ?>";
        const pmIn = "<?php echo $pm_in; ?>";
        const pmOut = "<?php echo $pm_out; ?>";
        const amlate = "<?php echo calculate_am_late($am_in) ?>";
        const pmlate = "<?php echo calculate_pm_late($pm_in) ?>";




        console.log("AM IN:", amIn);
        console.log("AM OUT:", amOut);
        console.log("PM IN:", pmIn);
        console.log("PM OUT:", pmOut);
        console.log("AM Late:", amlate);
        console.log("PM Late:", pmlate);


        // Get button elements
        const btnIn = document.getElementById("btnIn");
        const btnOut = document.getElementById("btnOut");

        // Determine current time in Manila
        const nowManila = new Date(getCurrentDateInManila() + 'T' + getCurrentTimeInManila());

        // Logic to control button states
        if (amIn && amOut && (!pmIn || !pmOut)) {
          // Both AM clock-in and clock-out are done, disable both buttons until 12 noon
          if (nowManila.getHours() >= 12) {
            // Already past noon, enable IN button for PM
            btnIn.disabled = false;
            btnOut.disabled = true;
          } else {
            btnIn.disabled = true;
            btnOut.disabled = true;
            waitForNoon(btnIn, btnOut);
          }
        } else if (pmIn && pmOut) {
          // Both PM clock-in and clock-out are done, disable both buttons until midnight
          if (nowManila.getHours() >= 24) {
            // Already past midnight, reset button states
            btnIn.disabled = false;
            btnOut.disabled = true;
          } else {
            btnIn.disabled = true;
            btnOut.disabled = true;
            resetButtonsAtMidnight();
          }
        } else if ((!amIn || amIn === "") && (!pmIn || pmIn === "")) {
          // No AM/PM clock-in data, enable IN button and disable OUT button
          btnIn.disabled = false;
          btnOut.disabled = true;
        } else if (amIn && !amOut) {
          // AM clock-in but no AM clock-out, disable IN button and enable OUT button
          btnIn.disabled = true;
          btnOut.disabled = false;
        } else if (amOut && !pmIn) {
          // AM clock-out is done, disable both buttons until 12 noon
          btnIn.disabled = true;
          btnOut.disabled = true;
          waitForNoon(btnIn, btnOut);
        } else if (pmIn && !pmOut) {
          // PM clock-in but no PM clock-out, disable IN button and enable OUT button
          btnIn.disabled = true;
          btnOut.disabled = false;
        } else if (pmOut) {
          // Both AM and PM clock-outs are done, disable both buttons
          btnIn.disabled = true;
          btnOut.disabled = true;
        }
      }

      function waitForNoon(btnIn, btnOut) {
        // Get current date and time in Manila timezone
        const nowManila = new Date().toLocaleString("en-US", {
          timeZone: "Asia/Manila"
        });
        const currentDateTimeManila = new Date(nowManila);

        // Set noon time
        const noon = new Date(currentDateTimeManila);
        noon.setHours(12, 0, 0, 0); // Set time to 12 noon

        const timeUntilNoon = noon - currentDateTimeManila;

        if (timeUntilNoon > 0) {
          console.log("Waiting until noon to re-enable buttons...");
          setTimeout(function() {
            console.log("It's noon! Re-enabling buttons for PM.");
            btnIn.disabled = false;
            btnOut.disabled = true;
          }, timeUntilNoon);
        } else {
          // If it's already past noon, enable the IN button for PM
          btnIn.disabled = false;
          btnOut.disabled = true;
        }
      }


      function resetButtonsAtMidnight() {
        const nowManila = new Date(getCurrentDateInManila() + 'T' + getCurrentTimeInManila());
        const midnight = new Date(nowManila);
        midnight.setHours(24, 0, 0, 0); // Set time to midnight

        const timeUntilMidnight = midnight - nowManila;

        console.log("Time until midnight:", timeUntilMidnight);

        // Check if already refreshed today
        if (!localStorage.getItem('refreshedToday')) {
          setTimeout(function() {
            console.log("Midnight reached, reloading...");
            localStorage.setItem('refreshedToday', 'true'); // Mark as refreshed today
            location.reload();
          }, timeUntilMidnight);
        }
      }

      $(document).ready(function() {
        // Check if refreshed today
        const today = getCurrentDateInManila();
        const refreshedDate = localStorage.getItem('refreshedDate');

        if (refreshedDate !== today) {
          localStorage.removeItem('refreshedToday'); // Reset flag if a new day
          localStorage.setItem('refreshedDate', today);
        }

        updateButtonStates();
      });
    </script>
  </div>
</body>

</html>
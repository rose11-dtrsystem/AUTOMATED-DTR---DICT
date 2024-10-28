<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];

// Fetch the full name using the stored username
$stmt = $conn->prepare("SELECT fullname, contract, position FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($full_name, $contract, $position);
$stmt->fetch();
$stmt->close();

// Get today's date
$today_date = date("Y-m-d");

// Check if there's already a record for today
$sql_check_today = "SELECT COUNT(*) FROM august2024 WHERE username = ? AND date = ?";
$stmt_check_today = $conn->prepare($sql_check_today);
$stmt_check_today->bind_param("ss", $username, $today_date);
$stmt_check_today->execute();
$stmt_check_today->bind_result($record_count);
$stmt_check_today->fetch();
$stmt_check_today->close();

// If no record exists for today, insert a new one
if ($record_count == 0) {
    $sql_insert_today = "INSERT INTO august2024 (username, fullname, date) VALUES (?, ?, ?)";
    $stmt_insert_today = $conn->prepare($sql_insert_today);
    $stmt_insert_today->bind_param("sss", $username, $full_name, $today_date);
    $stmt_insert_today->execute();
    $stmt_insert_today->close();
}

// Pagination variables
$limit = 5; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch today's AM/PM clock-in and clock-out statuses
$sql_status = "SELECT am_time_in, am_time_out, pm_time_in, pm_time_out FROM august2024 WHERE username = ? AND date = ?";
$stmt_status = $conn->prepare($sql_status);
$stmt_status->bind_param("ss", $username, $today_date);
$stmt_status->execute();
$stmt_status->bind_result($am_in, $am_out, $pm_in, $pm_out);
$stmt_status->fetch();
$stmt_status->close();

// Fetch existing time records for the user with pagination
$sql_records = "SELECT * FROM august2024 WHERE username = ? ORDER BY DATE DESC LIMIT ? OFFSET ?";
$stmt_records = $conn->prepare($sql_records);
$stmt_records->bind_param("sii", $username, $limit, $offset);
$stmt_records->execute();
$result_records = $stmt_records->get_result();

// Get total number of records for pagination controls
$sql_total_records = "SELECT COUNT(*) FROM august2024 WHERE username = ?";
$stmt_total_records = $conn->prepare($sql_total_records);
$stmt_total_records->bind_param("s", $username);
$stmt_total_records->execute();
$stmt_total_records->bind_result($total_records);
$stmt_total_records->fetch();
$stmt_total_records->close();

// Function to calculate hours worked between two times
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


$stmt_records->close();
$conn->close();
$total_records = max(0, $total_records); // Ensure non-negative

// Calculate total number of pages
$total_pages = ceil($total_records / $limit);
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

        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <ul>
                    <?php if ($page > 1): ?>
                        <li><a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a></li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li>
                            <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li><a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
        <a href="dtr.php" class="back-button">Back to DTR</a>
    </div>
</body>

</html>
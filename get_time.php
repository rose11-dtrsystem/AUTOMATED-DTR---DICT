<?php
date_default_timezone_set('Asia/Manila');
echo json_encode([
    'time' => date('h:i:s'),
    'weekday' => date('D'),
    'hours' => date('h'),
    'minutes' => date('i'),
    'seconds' => date('s'),
    'ampm' => date('A'),
]);

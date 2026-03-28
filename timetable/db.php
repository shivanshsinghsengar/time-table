<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'timetable_db');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("<p style='color:red;font-family:sans-serif;padding:40px;'>
            <strong>DB Error:</strong> " . $conn->connect_error . "
            <br>Check credentials in db.php</p>");
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>

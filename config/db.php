<?php

define('DB_HOST', '127.0.1.31');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'my_bd');

function db_connect(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'DB connection failed'], JSON_UNESCAPED_UNICODE));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

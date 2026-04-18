<?php

define('DB_HOST', '127.127.126.25');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'my_bd');

function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('Ошибка подключения к БД: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

<?php

require_once("../screens/bd_connect.php");

$sql = "SELECT id, title, author, year FROM poems";
$result = $conn->query($sql);

$poems = [];

while ($row = $result->fetch_assoc()) {
    $poems[] = $row;
}

header('Content-Type: application/json');
echo json_encode($poems);
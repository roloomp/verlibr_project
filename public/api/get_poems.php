<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$type    = $_GET['type'] ?? 'daily';
$allowed = ['daily', 'editors'];

if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

$conn   = db_connect();
$column = $type === 'editors' ? 'is_editors' : 'is_daily';

$stmt = $conn->prepare("SELECT id, title, author, year FROM poems WHERE {$column} = 1 ORDER BY RAND() LIMIT 6");
$stmt->execute();
$result = $stmt->get_result();

$poems = [];
while ($row = $result->fetch_assoc()) {
    $poems[] = $row;
}

echo json_encode($poems, JSON_UNESCAPED_UNICODE);

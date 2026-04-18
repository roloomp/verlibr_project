<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Ошибка безопасности']);
    exit;
}

$poem_id = (int)($_POST['poem_id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($poem_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный id']);
    exit;
}

$conn = db_connect();

$stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND poem_id = ?");
$stmt->bind_param("ii", $user_id, $poem_id);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;

if ($exists) {
    $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND poem_id = ?");
    $stmt->bind_param("ii", $user_id, $poem_id);
    $stmt->execute();
    $action = 'removed';
} else {
    $stmt = $conn->prepare("INSERT INTO likes (user_id, poem_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $poem_id);
    $stmt->execute();
    $action = 'added';
}

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM likes WHERE poem_id = ?");
$stmt->bind_param("i", $poem_id);
$stmt->execute();
$count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

echo json_encode([
    'action' => $action,
    'count' => $count
]);
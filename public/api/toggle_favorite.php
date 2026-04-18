<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

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

$stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND poem_id = ?");
$stmt->bind_param("ii", $user_id, $poem_id);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;

if ($exists) {
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND poem_id = ?");
    $stmt->bind_param("ii", $user_id, $poem_id);
    $stmt->execute();
    echo json_encode(['action' => 'removed']);
} else {
    $stmt = $conn->prepare("INSERT INTO favorites (user_id, poem_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $poem_id);
    $stmt->execute();
    echo json_encode(['action' => 'added']);
}

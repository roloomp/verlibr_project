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

$review_id = (int)($_POST['review_id'] ?? 0);
$user_id   = (int)$_SESSION['user_id'];

if ($review_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный id']);
    exit;
}

$conn = db_connect();

$stmt = $conn->prepare("SELECT id FROM review_likes WHERE user_id = ? AND review_id = ?");
$stmt->bind_param("ii", $user_id, $review_id);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;

if ($exists) {
    $stmt = $conn->prepare("DELETE FROM review_likes WHERE user_id = ? AND review_id = ?");
    $stmt->bind_param("ii", $user_id, $review_id);
    $stmt->execute();
    $action = 'removed';
} else {
    $stmt = $conn->prepare("INSERT INTO review_likes (user_id, review_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $review_id);
    $stmt->execute();
    $action = 'added';
}

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM review_likes WHERE review_id = ?");
$stmt->bind_param("i", $review_id);
$stmt->execute();
$count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

echo json_encode(['action' => $action, 'count' => $count]);

<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$conn = db_connect();

$stmt = $conn->prepare("
    SELECT a.id, a.name, COUNT(p.id) AS poem_count
    FROM authors a
    LEFT JOIN poems p ON p.author_id = a.id
    GROUP BY a.id
    ORDER BY poem_count DESC
    LIMIT 8
");
$stmt->execute();
$result = $stmt->get_result();

$authors = [];
while ($row = $result->fetch_assoc()) {
    $authors[] = $row;
}

echo json_encode($authors, JSON_UNESCAPED_UNICODE);

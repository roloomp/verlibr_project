<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$conn = db_connect();

// Предполагаем поля: birth_year, death_year, avatar в таблице authors
// dates формируем как "1969 - н.д." или "1799 - 1837"
$stmt = $conn->prepare("
    SELECT
        a.id,
        a.name,
        a.avatar,
        CONCAT(
            COALESCE(a.birth_year, '?'),
            ' - ',
            IF(a.death_year IS NULL OR a.death_year = 0, 'н.д.', a.death_year)
        ) AS dates,
        COUNT(p.id) AS poem_count
    FROM authors a
    LEFT JOIN poems p ON p.author_id = a.id
    GROUP BY a.id
    ORDER BY poem_count DESC
    LIMIT 6
");
$stmt->execute();
$result = $stmt->get_result();

$authors = [];
while ($row = $result->fetch_assoc()) {
    $authors[] = $row;
}

echo json_encode($authors, JSON_UNESCAPED_UNICODE);

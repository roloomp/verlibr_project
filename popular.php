<?php
session_start();
require_once __DIR__ . '/config/db.php';

$conn = db_connect();
$logged_in = !empty($_SESSION['logged_in']);

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 24;
$offset   = ($page - 1) * $per_page;

$total       = (int)$conn->query("SELECT COUNT(*) FROM poems")->fetch_row()[0];
$total_pages = max(1, (int)ceil($total / $per_page));

$sql = "
    SELECT p.id, p.title, p.author, p.year,
           COUNT(r.id) AS rating_count,
           ROUND(AVG(CASE WHEN r.has_review = 1 THEN r.total_score END), 0) AS avg_with,
           ROUND(AVG(CASE WHEN r.has_review = 0 THEN r.total_score END), 0) AS avg_without
    FROM poems p
    LEFT JOIN ratings r ON r.poem_id = p.id
    GROUP BY p.id
    ORDER BY rating_count DESC, p.id DESC
    LIMIT $per_page OFFSET $offset
";
$poems = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Популярное — Верлибр</title>
    <link rel="stylesheet" href="public/css/app.css">
    <link rel="stylesheet" href="public/css/popular.css">
</head>
<body>
    <my-header></my-header>

    <?php if (!$logged_in): ?>
    <auth-buttons></auth-buttons>
    <?php endif; ?>

    <main class="popular-page">
        <h1 class="popular-title">Популярное</h1>

        <div class="popular-grid">
            <?php foreach ($poems as $i => $p):
                $num = ($page - 1) * $per_page + $i + 1;
            ?>
            <a href="poem.php?id=<?= $p['id'] ?>" class="popular-item">
                <div class="popular-num"><?= $num ?></div>
                <div class="popular-info">
                    <div class="popular-title-text"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="popular-meta"><?= htmlspecialchars($p['author']) ?> · <?= (int)$p['year'] ?></div>
                    <div class="popular-scores">
                        <span><?= $p['avg_with'] ?: '—' ?></span>
                        <span class="divider">/</span>
                        <span><?= $p['avg_without'] ?: '—' ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">‹</a><?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?><a href="?page=<?= $page + 1 ?>">›</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    </main>

    <script src="public/js/header.js"></script>
</body>
</html>

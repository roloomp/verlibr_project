<?php
session_start();
require_once __DIR__ . '/config/db.php';

$conn = db_connect();

$author_id   = (int)($_GET['id'] ?? 0);
$author_name = trim($_GET['name'] ?? '');

$author = null;

if ($author_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM authors WHERE id = ?");
    $stmt->bind_param("i", $author_id);
    $stmt->execute();
    $author = $stmt->get_result()->fetch_assoc();
} elseif ($author_name !== '') {
    $stmt = $conn->prepare("SELECT * FROM authors WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $author_name);
    $stmt->execute();
    $author = $stmt->get_result()->fetch_assoc();
}

$author_display_name = '';
$author_avatar       = '';
$author_bio          = '';
$author_dates        = '';

if ($author) {
    $author_display_name = $author['name'];
    $author_avatar       = $author['avatar'] ?? '';
    $author_bio          = $author['bio'] ?? '';
    $birth = $author['birth_year'] ?? '';
    $death = ($author['death_year'] ?? 0) ? $author['death_year'] : 'н.д.';
    $author_dates = $birth ? "$birth — $death" : '';
    $author_id    = (int)$author['id'];
} elseif ($author_name !== '') {
    $author_display_name = $author_name;
}

if (!$author_display_name) {
    http_response_code(404);
    die('Автор не найден');
}

if ($author_id > 0) {
    $stmt = $conn->prepare("
        SELECT p.id, p.title, p.year,
               ROUND(AVG(CASE WHEN r.has_review=1 THEN r.total_score END),0) AS avg_with,
               ROUND(AVG(CASE WHEN r.has_review=0 THEN r.total_score END),0) AS avg_without
        FROM poems p
        LEFT JOIN ratings r ON r.poem_id = p.id
        WHERE p.author_id = ?
        GROUP BY p.id
        ORDER BY p.year DESC
    ");
    $stmt->bind_param("i", $author_id);
} else {
    $stmt = $conn->prepare("
        SELECT p.id, p.title, p.year,
               ROUND(AVG(CASE WHEN r.has_review=1 THEN r.total_score END),0) AS avg_with,
               ROUND(AVG(CASE WHEN r.has_review=0 THEN r.total_score END),0) AS avg_without
        FROM poems p
        LEFT JOIN ratings r ON r.poem_id = p.id
        WHERE p.author = ?
        GROUP BY p.id
        ORDER BY p.year DESC
    ");
    $stmt->bind_param("s", $author_display_name);
}
$stmt->execute();
$poems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$poem_count = count($poems);

$logged_in = !empty($_SESSION['logged_in']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($author_display_name) ?> — Верлибр</title>
    <link rel="stylesheet" href="public/css/app.css">
    <link rel="stylesheet" href="public/css/author.css">
</head>
<body>
    <my-header></my-header>

    <?php if (!$logged_in): ?>
    <auth-buttons></auth-buttons>
    <?php endif; ?>

    <main class="author-page">

        <div class="author-header">
            <div class="author-header__avatar-wrap">
                <?php if ($author_avatar): ?>
                    <img class="author-header__avatar" src="<?= htmlspecialchars($author_avatar) ?>" alt="<?= htmlspecialchars($author_display_name) ?>">
                <?php else: ?>
                    <div class="author-header__avatar author-header__avatar--placeholder">
                        <?= mb_strtoupper(mb_substr($author_display_name, 0, 1, 'UTF-8'), 'UTF-8') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="author-header__info">
                <h1 class="author-header__name"><?= htmlspecialchars($author_display_name) ?></h1>
                <?php if ($author_dates): ?>
                <div class="author-header__dates"><?= htmlspecialchars($author_dates) ?></div>
                <?php endif; ?>
                <div class="author-header__count">Стихотворений: <?= $poem_count ?></div>
                <?php if ($author_bio): ?>
                <div class="author-header__bio"><?= nl2br(htmlspecialchars($author_bio)) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <hr class="divider">

        <div class="author-poems">
            <h2 class="author-poems__title">Стихотворения</h2>

            <?php if (empty($poems)): ?>
            <div class="author-poems__empty">Стихотворения не найдены</div>
            <?php else: ?>
            <div class="author-poems__grid">
                <?php foreach ($poems as $p): ?>
                <a href="poem.php?id=<?= $p['id'] ?>" class="author-poem-card">
                    <div class="author-poem-card__info">
                        <div class="author-poem-card__title"><?= htmlspecialchars($p['title']) ?></div>
                        <div class="author-poem-card__year"><?= htmlspecialchars((string)$p['year']) ?></div>
                    </div>
                    <div class="author-poem-card__scores">
                        <span class="author-poem-score" title="С рецензией"><?= $p['avg_with'] ?: '—' ?></span>
                        <span class="author-poem-score author-poem-score--alt" title="Без рецензии"><?= $p['avg_without'] ?: '—' ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <script src="public/js/header.js"></script>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

$logged_in = !empty($_SESSION['logged_in']);

$poems = [];
$total_pages = 1;
$page = 1;
$sort = 'новые';

if ($logged_in) {
    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];

    $per_page = 24;
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $per_page;

    $sort_map = [
        'новые'  => 'f.created_at DESC',
        'лучшие' => 'avg_score DESC',
        'старые' => 'f.created_at ASC',
    ];
    $sort = $_GET['sort'] ?? 'новые';
    if (!isset($sort_map[$sort])) $sort = 'новые';
    $order = $sort_map[$sort];

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM favorites f WHERE f.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $total_pages = max(1, (int)ceil($total / $per_page));

    $stmt = $conn->prepare("
        SELECT p.id, p.title, p.author, p.year,
               ROUND(AVG(CASE WHEN r.has_review = 1 THEN r.total_score END), 0) AS avg_with,
               ROUND(AVG(CASE WHEN r.has_review = 0 THEN r.total_score END), 0) AS avg_without,
               ROUND(AVG(r.total_score), 0) AS avg_score,
               f.created_at AS fav_date
        FROM favorites f
        JOIN poems p ON p.id = f.poem_id
        LEFT JOIN ratings r ON r.poem_id = p.id
        WHERE f.user_id = ?
        GROUP BY p.id, f.created_at
        ORDER BY {$order}
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $user_id, $per_page, $offset);
    $stmt->execute();
    $poems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Избранное — Верлибр</title>
    <link rel="stylesheet" href="public/css/app.css">
    <link rel="stylesheet" href="public/css/favorites.css">
</head>
<body>
    <my-header></my-header>

    <?php if (!$logged_in): ?>

    <!-- Кнопок входа сверху НЕТ — только модалка через auth-buttons -->
    <auth-buttons></auth-buttons>

    <main>
        <div class="fav-page">
            <div class="fav-header">
                <h1 class="fav-title">Избранное</h1>
            </div>
            <div class="fav-empty">
                <p style="margin-bottom: 16px;">Войдите в аккаунт, чтобы увидеть избранное</p>
                <div style="display:flex; gap:12px; justify-content:center">
                    <button class="auth-btn auth-btn--login" onclick="window.openAuthModal('login')">Войти</button>
                    <button class="auth-btn auth-btn--register" onclick="window.openAuthModal('register')">Регистрация</button>
                </div>
            </div>
        </div>
    </main>

    <?php else: ?>

    <main class="fav-page">
        <div class="fav-header">
            <h1 class="fav-title">Избранное</h1>
            <select class="fav-sort" onchange="
                const url = new URL(window.location);
                url.searchParams.set('sort', this.value);
                url.searchParams.set('page', 1);
                window.location = url;
            ">
                <option value="новые"  <?= $sort === 'новые'  ? 'selected' : '' ?>>новые</option>
                <option value="лучшие" <?= $sort === 'лучшие' ? 'selected' : '' ?>>лучшие</option>
                <option value="старые" <?= $sort === 'старые' ? 'selected' : '' ?>>старые</option>
            </select>
        </div>

        <?php if (empty($poems)): ?>
        <div class="fav-empty">Вы ещё ничего не добавили в избранное.</div>
        <?php else: ?>

        <div class="fav-grid">
            <?php foreach ($poems as $poem): ?>
            <div class="fav-item">
                <div class="fav-item__info">
                    <div class="fav-item__title">
                        <a href="poem.php?id=<?= $poem['id'] ?>"><?= htmlspecialchars($poem['title']) ?></a>
                    </div>
                    <div class="fav-item__author"><?= htmlspecialchars($poem['author']) ?></div>
                    <div class="fav-item__year"><?= htmlspecialchars((string)$poem['year']) ?></div>
                </div>
                <div class="fav-item__scores">
                    <span class="fav-score" title="С рецензией"><?= $poem['avg_with'] ?? '—' ?></span>
                    <span class="fav-score fav-score--alt" title="Без рецензии"><?= $poem['avg_without'] ?? '—' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?sort=<?= urlencode($sort) ?>&page=<?= $page - 1 ?>">‹</a>
            <?php endif; ?>
            <?php
            $pages_to_show = [];
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i === 1 || $i === $total_pages || abs($i - $page) <= 2) {
                    $pages_to_show[] = $i;
                }
            }
            $prev = null;
            foreach ($pages_to_show as $p):
                if ($prev !== null && $p - $prev > 1): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="?sort=<?= urlencode($sort) ?>&page=<?= $p ?>"
                   class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php
                $prev = $p;
            endforeach; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?sort=<?= urlencode($sort) ?>&page=<?= $page + 1 ?>">›</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; ?>
    </main>

    <?php endif; ?>

    <script src="public/js/header.js"></script>
</body>
</html>

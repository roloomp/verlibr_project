<?php
session_start();
require_once __DIR__ . '/config/db.php';

$conn = db_connect();
$logged_in = !empty($_SESSION['logged_in']);
$user_id   = $logged_in ? (int)$_SESSION['user_id'] : 0;

$q             = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$sort          = $_GET['sort'] ?? 'новые';
$min_rating    = isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? (int)$_GET['min_rating'] : 0;
$exclude_fav   = isset($_GET['exclude_fav']);
$exclude_rated = isset($_GET['exclude_rated']);

$tag_keys = ['tag_type', 'tag_genre', 'tag_mood', 'tag_versification', 'tag_foot', 'tag_stanza', 'tag_rhyme'];
$tag_vals = [];
foreach ($tag_keys as $k) {
    $tag_vals[$k] = trim($_GET[$k] ?? '');
}

$per_page = 12;
$offset   = ($page - 1) * $per_page;

$where   = [];
$params  = [];
$types   = '';

if ($q !== '') {
    $like     = "%$q%";
    $where[]  = "(p.title LIKE ? OR p.author LIKE ? OR p.content LIKE ?)";
    $params   = array_merge($params, [$like, $like, $like]);
    $types   .= 'sss';
}

foreach ($tag_keys as $k) {
    if ($tag_vals[$k] !== '') {
        $where[]  = "p.$k = ?";
        $params[] = $tag_vals[$k];
        $types   .= 's';
    }
}

if ($logged_in && $exclude_fav) {
    $where[]  = "p.id NOT IN (SELECT poem_id FROM favorites WHERE user_id = ?)";
    $params[] = $user_id;
    $types   .= 'i';
}
if ($logged_in && $exclude_rated) {
    $where[]  = "p.id NOT IN (SELECT poem_id FROM ratings WHERE user_id = ?)";
    $params[] = $user_id;
    $types   .= 'i';
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

switch ($sort) {
    case 'лучшие': $order_sql = 'avg_score DESC, p.id DESC'; break;
    case 'старые':  $order_sql = 'p.year ASC, p.id ASC'; break;
    default:        $order_sql = 'p.year DESC, p.id DESC';
}

$having_parts = [];
if ($min_rating > 0) {
    $having_parts[] = "COALESCE(ROUND(AVG(r.total_score), 0), 0) >= ?";
    $params[]       = $min_rating;
    $types         .= 'i';
}
$having_sql = count($having_parts) ? 'HAVING ' . implode(' AND ', $having_parts) : '';

$count_params = $params;
$count_types  = $types;
if ($min_rating > 0) {
    array_pop($count_params);
    $count_types = substr($count_types, 0, -1);
}

$count_sql  = "SELECT COUNT(DISTINCT p.id) FROM poems p $where_sql";
$stmt_cnt   = $conn->prepare($count_sql);
if (!empty($count_params)) $stmt_cnt->bind_param($count_types, ...$count_params);
$stmt_cnt->execute();
$total       = (int)$stmt_cnt->get_result()->fetch_row()[0];
$total_pages = max(1, (int)ceil($total / $per_page));

$data_sql = "
    SELECT p.id, p.title, p.author, p.year,
           ROUND(AVG(CASE WHEN r.has_review = 1 THEN r.total_score END), 0) AS avg_with,
           ROUND(AVG(CASE WHEN r.has_review = 0 THEN r.total_score END), 0) AS avg_without,
           COALESCE(ROUND(AVG(r.total_score), 0), 0) AS avg_score
    FROM poems p
    LEFT JOIN ratings r ON r.poem_id = p.id
    $where_sql
    GROUP BY p.id
    $having_sql
    ORDER BY $order_sql
    LIMIT $per_page OFFSET $offset
";

$stmt = $conn->prepare($data_sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$poems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$distinct = [];
foreach ($tag_keys as $k) {
    $res = $conn->query("SELECT DISTINCT `$k` FROM poems WHERE `$k` IS NOT NULL AND `$k` != '' ORDER BY `$k`");
    $distinct[$k] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск — Верлибр</title>
    <link rel="stylesheet" href="public/css/app.css">
    <link rel="stylesheet" href="public/css/search.css">
</head>
<body>
    <my-header></my-header>

    <?php if (!$logged_in): ?>
    <auth-buttons></auth-buttons>
    <?php endif; ?>

    <main class="search-page">
        <form method="GET" id="searchForm">
            <div class="search-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Строка, цитата, настроение или автор.." class="search-input-main">
                <button type="submit" class="search-btn">Найти</button>
            </div>

            <div class="search-layout">
                <section class="results-area">
                    <div class="results-header">
                        <span>Найдено: <?= $total ?></span>
                        <select class="sort-select" id="sortSelect">
                            <option value="новые"  <?= $sort === 'новые'  ? 'selected' : '' ?>>Сначала новые</option>
                            <option value="лучшие" <?= $sort === 'лучшие' ? 'selected' : '' ?>>По рейтингу</option>
                            <option value="старые" <?= $sort === 'старые' ? 'selected' : '' ?>>По дате (старые)</option>
                        </select>
                    </div>

                    <?php if (empty($poems)): ?>
                    <div class="no-results">Ничего не найдено. Попробуйте изменить запрос или снять фильтры.</div>
                    <?php else: ?>
                    <div class="poems-grid">
                        <?php foreach ($poems as $p): ?>
                        <a href="poem.php?id=<?= $p['id'] ?>" class="poem-card">
                            <div class="poem-card__info">
                                <div class="poem-card__title"><?= htmlspecialchars($p['title']) ?></div>
                                <div class="poem-card__author"><?= htmlspecialchars($p['author']) ?></div>
                                <div class="poem-card__year"><?= htmlspecialchars((string)$p['year']) ?></div>
                            </div>
                            <div class="poem-card__scores">
                                <span class="score-val" title="С рецензией"><?= $p['avg_with'] ?: '—' ?></span>
                                <span class="score-val score-val--alt" title="Без рецензии"><?= $p['avg_without'] ?: '—' ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">‹</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                               class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">›</a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                    <?php endif; ?>
                </section>

                <aside class="filters-sidebar">
                    <h3>Предпочтения</h3>

                    <?php if ($logged_in): ?>
                    <label class="filter-check">
                        <input type="checkbox" name="exclude_fav" <?= $exclude_fav ? 'checked' : '' ?>>
                        Не показывать избранное
                    </label>
                    <label class="filter-check">
                        <input type="checkbox" name="exclude_rated" <?= $exclude_rated ? 'checked' : '' ?>>
                        Не показывать с оценкой
                    </label>
                    <?php else: ?>
                    <p class="filter-notice">Войдите, чтобы скрыть прочитанное</p>
                    <?php endif; ?>

                    <div class="filter-group">
                        <label>Мин. рейтинг</label>
                        <input type="number" name="min_rating" min="0" max="90"
                               value="<?= $min_rating > 0 ? $min_rating : '' ?>"
                               class="filter-input" placeholder="от 0 до 90">
                    </div>

                    <?php
                    $filter_labels = [
                        'tag_type'          => 'Тип',
                        'tag_genre'         => 'Жанр',
                        'tag_mood'          => 'Настроение',
                        'tag_versification' => 'Стихосложение',
                        'tag_foot'          => 'Базовая стопа',
                        'tag_stanza'        => 'Строфа',
                        'tag_rhyme'         => 'Способ рифмовки',
                    ];
                    foreach ($filter_labels as $key => $label): ?>
                    <div class="filter-group">
                        <label><?= $label ?></label>
                        <select name="<?= $key ?>" class="filter-select">
                            <option value="">Все</option>
                            <?php foreach ($distinct[$key] as $row): ?>
                            <option value="<?= htmlspecialchars($row[$key]) ?>"
                                    <?= $tag_vals[$key] === $row[$key] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row[$key]) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn-apply">Применить</button>
                    <a href="search.php" class="btn-reset">Сбросить всё</a>
                </aside>
            </div>
        </form>
    </main>

    <script src="public/js/header.js"></script>
    <script>
    document.getElementById('sortSelect').addEventListener('change', function() {
        var url = new URL(window.location);
        url.searchParams.set('sort', this.value);
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    });
    </script>
</body>
</html>

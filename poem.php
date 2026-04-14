<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

$conn = db_connect();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die('Стихотворение не найдено');
}

// Загружаем стихотворение
$stmt = $conn->prepare("
    SELECT p.id, p.title, p.content, p.year, p.author,
           p.tag_type, p.tag_genre, p.tag_mood, p.tag_versification,
           p.tag_foot, p.tag_stanza, p.tag_rhyme
    FROM poems p
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$poem = $stmt->get_result()->fetch_assoc();

if (!$poem) {
    http_response_code(404);
    die('Стихотворение не найдено');
}

// Рейтинг
$stmt = $conn->prepare("
    SELECT
        ROUND(AVG(CASE WHEN has_review = 1 THEN total_score END), 0)   AS avg_with_review,
        ROUND(AVG(CASE WHEN has_review = 0 THEN total_score END), 0)   AS avg_without_review,
        COUNT(*) AS total_count
    FROM ratings
    WHERE poem_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$rating = $stmt->get_result()->fetch_assoc();

$avg_with    = $rating['avg_with_review']    ?? 0;
$avg_without = $rating['avg_without_review'] ?? 0;
$total_count = $rating['total_count']        ?? 0;

// Похожие
$stmt = $conn->prepare("
    SELECT id, title, author, year FROM poems
    WHERE id != ? ORDER BY RAND() LIMIT 3
");
$stmt->bind_param("i", $id);
$stmt->execute();
$similar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Рецензии
$sort = $_GET['sort'] ?? 'новые';
$order = $sort === 'лучшие' ? 'total_score DESC' : 'created_at DESC';

$stmt = $conn->prepare("
    SELECT r.id, r.total_score, r.review_title, r.review_text,
           r.created_at, r.has_review,
           r.score_rhyme, r.score_style, r.score_structure,
           r.score_individuality, r.score_atmosphere,
           u.name AS user_name, u.avatar AS user_avatar
    FROM ratings r
    JOIN profile u ON u.id = r.user_id
    WHERE r.poem_id = ? AND r.has_review = 1
    ORDER BY {$order}
    LIMIT 20
");
$stmt->bind_param("i", $id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$logged_in = !empty($_SESSION['logged_in']);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');

// Обработка отправки оценки
$review_error = '';
$review_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in) {
    $user_id     = (int)$_SESSION['user_id'];
    $has_review  = isset($_POST['review_text']) && trim($_POST['review_text']) !== '';
    $sc_rhyme    = min(10, max(0, (int)($_POST['score_rhyme']         ?? 0)));
    $sc_style    = min(10, max(0, (int)($_POST['score_style']         ?? 0)));
    $sc_struct   = min(10, max(0, (int)($_POST['score_structure']     ?? 0)));
    $sc_indiv    = min(10, max(0, (int)($_POST['score_individuality'] ?? 0)));
    $sc_atmo     = min(10, max(0, (int)($_POST['score_atmosphere']    ?? 0)));
    $total       = $sc_rhyme + $sc_style + $sc_struct + $sc_indiv + $sc_atmo;
    $rev_title   = trim($_POST['review_title'] ?? '');
    $rev_text    = trim($_POST['review_text']  ?? '');

    if ($has_review && mb_strlen($rev_text, 'UTF-8') < 300) {
        $review_error = 'Текст рецензии должен быть не менее 300 символов.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO ratings
                (poem_id, user_id, has_review, score_rhyme, score_style,
                 score_structure, score_individuality, score_atmosphere,
                 total_score, review_title, review_text, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                has_review=VALUES(has_review), score_rhyme=VALUES(score_rhyme),
                score_style=VALUES(score_style), score_structure=VALUES(score_structure),
                score_individuality=VALUES(score_individuality),
                score_atmosphere=VALUES(score_atmosphere),
                total_score=VALUES(total_score), review_title=VALUES(review_title),
                review_text=VALUES(review_text), created_at=NOW()
        ");
        $stmt->bind_param("iiiiiiiiiss",
            $id, $user_id, $has_review,
            $sc_rhyme, $sc_style, $sc_struct, $sc_indiv, $sc_atmo,
            $total, $rev_title, $rev_text
        );
        $stmt->execute();
        $review_ok = true;
        header("Location: poem.php?id={$id}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($poem['title']) ?> — Верлибр</title>
    <link rel="stylesheet" href="public/css/app.css">
    <link rel="stylesheet" href="public/css/poem.css">
</head>
<body>
    <my-header></my-header>

    <?php if (!$logged_in): ?>
    <div class="auth-topbar">
        <button class="auth-btn auth-btn--login" id="btn-login-top">Вход</button>
        <button class="auth-btn auth-btn--register" id="btn-register-top">Регистрация</button>
    </div>
    <?php endif; ?>

    <main class="poem-page">

        <!-- Левая часть: стихотворение -->
        <div class="poem-main">

            <h1 class="poem-title"><?= htmlspecialchars($poem['title']) ?></h1>
            <div class="poem-meta">
                <a href="author.php" class="poem-author"><?= htmlspecialchars($poem['author']) ?></a>
                <span class="poem-dot">·</span>
                <span class="poem-year"><?= htmlspecialchars((string)$poem['year']) ?> г.</span>
            </div>

            <div class="poem-content">
                <?= nl2br(htmlspecialchars($poem['content'])) ?>
            </div>

            <!-- Действия под стихом -->
            <div class="poem-actions">
                <button class="poem-action" title="Нравится">♡ <?= $total_count ?></button>
                <button class="poem-action" title="В избранное">☆</button>
                <div class="poem-share">
                    <button class="poem-action" title="ВКонтакте">VK</button>
                    <button class="poem-action" title="Одноклассники">OK</button>
                </div>
            </div>

            <!-- Оценить работу -->
            <div class="rate-section">
                <h2 class="rate-title">Оценить работу</h2>

                <div class="rate-tabs">
                    <button class="rate-tab active" data-tab="review">Рецензия</button>
                    <button class="rate-tab" data-tab="simple">Оценка без рецензии</button>
                </div>

                <?php if ($review_error): ?>
                    <div class="error-message" style="margin-bottom:10px"><?= htmlspecialchars($review_error) ?></div>
                <?php endif; ?>

                <?php if ($logged_in): ?>
                <form class="rate-form" method="POST">
                    <div class="rate-sliders">
                        <div class="slider-row">
                            <label>Рифмы / Образы</label>
                            <input type="range" name="score_rhyme" min="0" max="10" value="5" class="slider" data-out="out_rhyme">
                            <span class="slider-val" id="out_rhyme">5</span>
                        </div>
                        <div class="slider-row">
                            <label>Структура / Ритмика</label>
                            <input type="range" name="score_structure" min="0" max="10" value="5" class="slider" data-out="out_structure">
                            <span class="slider-val" id="out_structure">5</span>
                        </div>
                        <div class="slider-row">
                            <label>Реализация стиля</label>
                            <input type="range" name="score_style" min="0" max="10" value="5" class="slider" data-out="out_style">
                            <span class="slider-val" id="out_style">5</span>
                        </div>
                        <div class="slider-row">
                            <label>Индивидуальность / Харизма</label>
                            <input type="range" name="score_individuality" min="0" max="10" value="5" class="slider" data-out="out_individuality">
                            <span class="slider-val" id="out_individuality">5</span>
                        </div>
                        <div class="slider-row slider-row--full">
                            <label>Атмосфера / Вайб</label>
                            <input type="range" name="score_atmosphere" min="0" max="10" value="5" class="slider slider--accent" data-out="out_atmosphere">
                            <span class="slider-val" id="out_atmosphere">5</span>
                        </div>
                    </div>

                    <div class="review-fields" id="review-fields">
                        <input type="text" class="form__input" name="review_title" placeholder="Заголовок рецензии">
                        <textarea class="form__input review-textarea" name="review_text"
                            placeholder="Текст рецензии (от 300 до 8500 символов)"
                            maxlength="8500" id="review-text-area"></textarea>
                        <div class="review-footer">
                            <button type="button" class="clear-btn" id="clear-draft">🗑 Очистить черновик</button>
                            <span class="char-count"><span id="char-count">0</span>/8500</span>
                        </div>
                    </div>

                    <div class="rate-submit-row">
                        <div class="total-score" id="total-score">25<sup>/50</sup></div>
                        <button type="submit" class="rate-submit-btn" title="Отправить">✔</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="rate-login-msg">
                    <a href="#" id="login-to-rate">Войдите</a>, чтобы оставить оценку.
                </div>
                <?php endif; ?>
            </div>

            <!-- Рецензии пользователей -->
            <div class="reviews-section">
                <div class="reviews-header">
                    <h2 class="reviews-title">
                        Рецензии пользователей
                        <?php if (count($reviews) > 0): ?>
                        <span class="reviews-badge"><?= count($reviews) ?></span>
                        <?php endif; ?>
                    </h2>
                    <select class="reviews-sort" onchange="window.location='poem.php?id=<?= $id ?>&sort='+this.value">
                        <option value="новые" <?= $sort === 'новые' ? 'selected' : '' ?>>новые</option>
                        <option value="лучшие" <?= $sort === 'лучшие' ? 'selected' : '' ?>>лучшие</option>
                    </select>
                </div>

                <?php if (empty($reviews)): ?>
                <div class="reviews-empty">Нет рецензий</div>
                <?php endif; ?>

                <?php foreach ($reviews as $rev): ?>
                <div class="review-card">
                    <div class="review-card-header">
                        <div class="review-user">
                            <?php if ($rev['user_avatar']): ?>
                                <img class="review-avatar" src="<?= htmlspecialchars($rev['user_avatar']) ?>" alt="">
                            <?php else: ?>
                                <div class="review-avatar review-avatar--placeholder"></div>
                            <?php endif; ?>
                            <span class="review-username"><?= htmlspecialchars($rev['user_name']) ?></span>
                        </div>
                        <div class="review-scores">
                            <div class="review-total"><?= (int)$rev['total_score'] ?></div>
                            <div class="review-subscores">
                                <?= (int)$rev['score_rhyme'] ?>
                                <?= (int)$rev['score_style'] ?>
                                <?= (int)$rev['score_structure'] ?>
                                <?= (int)$rev['score_individuality'] ?>
                                <?= (int)$rev['score_atmosphere'] ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($rev['review_title']): ?>
                    <div class="review-rev-title"><?= htmlspecialchars($rev['review_title']) ?></div>
                    <?php endif; ?>
                    <div class="review-text"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></div>
                    <div class="review-date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></div>
                    <button class="review-like">♡ <?= rand(1,20) ?></button>
                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /poem-main -->

        <!-- Правая панель -->
        <aside class="poem-sidebar">

            <!-- Метки -->
            <div class="sidebar-block">
                <div class="sidebar-block__title">МЕТКИ</div>
                <?php
                $tags = [
                    'Тип'             => $poem['tag_type'],
                    'Жанр'            => $poem['tag_genre'],
                    'Настроение'      => $poem['tag_mood'],
                    'Стихосложение'   => $poem['tag_versification'],
                    'Базовая стопа'   => $poem['tag_foot'],
                    'Строфа'          => $poem['tag_stanza'],
                    'Способ рифмовки' => $poem['tag_rhyme'],
                ];
                foreach ($tags as $label => $value):
                    if (!$value) continue;
                ?>
                <div class="tag-row">
                    <span class="tag-label"><?= htmlspecialchars($label) ?>:</span>
                    <span class="tag-badge"><?= htmlspecialchars($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Рейтинг -->
            <div class="sidebar-block">
                <div class="sidebar-block__title">РЕЙТИНГ</div>
                <div class="rating-row">
                    <span class="rating-big"><?= $avg_with ?: '—' ?></span>
                    <span class="rating-max">/90</span>
                    <span class="rating-label">Средняя оценка с рецензией</span>
                </div>
                <div class="rating-row">
                    <span class="rating-big"><?= $avg_without ?: '—' ?></span>
                    <span class="rating-max">/90</span>
                    <span class="rating-label">Средняя оценка без рецензии</span>
                </div>
                <div class="rating-total">Всего оценок: <?= $total_count ?></div>
            </div>

            <!-- Похожее -->
            <div class="sidebar-block">
                <div class="sidebar-block__title">ПОХОЖЕЕ</div>
                <?php foreach ($similar as $i => $s): ?>
                <div class="similar-item">
                    <div class="similar-title"><a href="poem.php?id=<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></a></div>
                    <div class="similar-meta"><?= htmlspecialchars($s['author']) ?></div>
                    <div class="similar-year"><?= htmlspecialchars((string)$s['year']) ?></div>
                </div>
                <?php if ($i < count($similar) - 1): ?><hr class="sidebar-hr"><?php endif; ?>
                <?php endforeach; ?>
            </div>

        </aside>
    </main>

    <script src="public/js/header.js"></script>
    <script>
    // Слайдеры
    const sliders = document.querySelectorAll('.slider');
    function updateTotal() {
        let sum = 0;
        sliders.forEach(s => { sum += parseInt(s.value); });
        const maxTotal = sliders.length * 10;
        document.getElementById('total-score').innerHTML = sum + '<sup>/' + maxTotal + '</sup>';
    }
    sliders.forEach(s => {
        const outId = s.dataset.out;
        const out = document.getElementById(outId);
        s.addEventListener('input', () => {
            if (out) out.textContent = s.value;
            updateTotal();
        });
    });
    updateTotal();

    // Счётчик символов
    const textarea = document.getElementById('review-text-area');
    const charCount = document.getElementById('char-count');
    if (textarea) {
        textarea.addEventListener('input', () => {
            charCount.textContent = textarea.value.length;
        });
    }

    // Очистить черновик
    document.getElementById('clear-draft')?.addEventListener('click', () => {
        if (textarea) { textarea.value = ''; charCount.textContent = 0; }
        document.querySelector('input[name="review_title"]').value = '';
    });

    // Вкладки рецензия / без рецензии
    document.querySelectorAll('.rate-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.rate-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const isReview = tab.dataset.tab === 'review';
            document.getElementById('review-fields').style.display = isReview ? 'block' : 'none';
        });
    });
    </script>
</body>
</html>

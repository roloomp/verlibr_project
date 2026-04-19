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

$stmt = $conn->prepare("
    SELECT p.id, p.title, p.content, p.year, p.author, p.author_id,
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

$stmt = $conn->prepare("
    SELECT id, title, author, year FROM poems
    WHERE id != ? ORDER BY RAND() LIMIT 3
");
$stmt->bind_param("i", $id);
$stmt->execute();
$similar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM likes WHERE poem_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$like_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

$user_liked = false;
$user_favorited = false;
if (!empty($_SESSION['logged_in'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND poem_id = ?");
    $stmt->bind_param("ii", $uid, $id);
    $stmt->execute();
    $user_liked = $stmt->get_result()->num_rows > 0;

    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND poem_id = ?");
    $stmt->bind_param("ii", $uid, $id);
    $stmt->execute();
    $user_favorited = $stmt->get_result()->num_rows > 0;
}

$user_rating = null;
if (!empty($_SESSION['logged_in'])) {
    $uid2 = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM ratings WHERE poem_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $uid2);
    $stmt->execute();
    $user_rating = $stmt->get_result()->fetch_assoc();
}

$sort = $_GET['sort'] ?? 'новые';
$allowed_sort = ['новые', 'лучшие'];
if (!in_array($sort, $allowed_sort, true)) $sort = 'новые';
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

$review_likes_map  = [];
$user_review_likes = [];
if (!empty($reviews)) {
    $review_ids = array_map('intval', array_column($reviews, 'id'));
    $in = implode(',', $review_ids);

    $res = $conn->query("SELECT review_id, COUNT(*) AS cnt FROM review_likes WHERE review_id IN ({$in}) GROUP BY review_id");
    if ($res) while ($row = $res->fetch_assoc()) $review_likes_map[(int)$row['review_id']] = (int)$row['cnt'];

    if (!empty($_SESSION['logged_in'])) {
        $uid3 = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT review_id FROM review_likes WHERE user_id = ? AND review_id IN ({$in})");
        $stmt->bind_param("i", $uid3);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $user_review_likes[(int)$row['review_id']] = true;
    }
}

$logged_in = !empty($_SESSION['logged_in']);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in && isset($_POST['delete_rating'])) {
    $uid_del = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("DELETE FROM ratings WHERE poem_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $uid_del);
    $stmt->execute();
    header("Location: poem.php?id={$id}");
    exit;
}

$review_error = '';
$review_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in) {
    $user_id = (int)$_SESSION['user_id'];
    $has_review = isset($_POST['review_text']) && trim($_POST['review_text']) !== '';
    $sc_rhyme = min(18, max(0, (int)($_POST["score_rhyme"]         ?? 0)));
    $sc_style = min(18, max(0, (int)($_POST['score_style']         ?? 0)));
    $sc_struct = min(18, max(0, (int)($_POST['score_structure']     ?? 0)));
    $sc_indiv = min(18, max(0, (int)($_POST['score_individuality'] ?? 0)));
    $sc_atmo = min(18, max(0, (int)($_POST['score_atmosphere']    ?? 0)));
    $total = $sc_rhyme + $sc_style + $sc_struct + $sc_indiv + $sc_atmo;
    $rev_title = mb_substr(trim($_POST['review_title'] ?? ''), 0, 255, 'UTF-8');
    $rev_text = trim($_POST['review_text']  ?? '');

    if ($has_review && mb_strlen($rev_text, 'UTF-8') < 300) {
        $review_error = 'Текст рецензии должен быть не менее 300 символов.';
    } elseif ($has_review && mb_strlen($rev_text, 'UTF-8') > 8500) {
        $review_error = 'Текст рецензии слишком длинный (максимум 8500 символов).';
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
    <auth-buttons></auth-buttons>
    <?php endif; ?>

    <main class="poem-page">

        <div class="poem-main">

            <h1 class="poem-title"><?= htmlspecialchars($poem['title']) ?></h1>
            <div class="poem-meta">
                <?php if ($poem['author_id'] ?? null): ?>
                    <a href="author.php?id=<?= (int)$poem['author_id'] ?>" class="poem-author"><?= htmlspecialchars($poem['author']) ?></a>
                <?php else: ?>
                    <span class="poem-author"><?= htmlspecialchars($poem['author']) ?></span>
                <?php endif; ?>
                <span class="poem-dot">·</span>
                <span class="poem-year"><?= htmlspecialchars((string)$poem['year']) ?> г.</span>
            </div>

            <div class="poem-content">
                <pre class="poem-pre"><?= htmlspecialchars($poem['content']) ?></pre>
            </div>

            <div class="poem-actions">
                <button class="poem-action poem-action--like <?= $user_liked ? 'active' : '' ?>"
                        id="btn-like"
                        data-poem-id="<?= $id ?>"
                        data-logged-in="<?= $logged_in ? '1' : '0' ?>"
                        title="Нравится">
                    <?= $user_liked ? '♥' : '♡' ?> <span id="like-count"><?= $like_count ?></span>
                </button>
                <button class="poem-action poem-action--fav <?= $user_favorited ? 'active' : '' ?>"
                        id="btn-fav"
                        data-poem-id="<?= $id ?>"
                        data-logged-in="<?= $logged_in ? '1' : '0' ?>"
                        title="В избранное">
                    <?= $user_favorited ? '★' : '☆' ?>
                </button>
                <div class="poem-share">
                    <button class="poem-action" title="ВКонтакте">VK</button>
                    <button class="poem-action" title="Одноклассники">OK</button>
                </div>
            </div>

            <div class="rate-section">
                <h2 class="rate-title">Оценить работу</h2>

                <?php if ($review_error): ?>
                    <div class="error-message" style="margin-bottom:10px"><?= htmlspecialchars($review_error) ?></div>
                <?php endif; ?>

                <?php if (!$logged_in): ?>
                <div class="rate-login-msg">
                    <a href="#" id="login-to-rate">Войдите</a>, чтобы оставить оценку.
                </div>

                <?php elseif ($user_rating): ?>
                <div class="rate-existing">
                    <div class="rate-existing__header">
                        <span class="rate-existing__label">Ваша оценка</span>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Удалить вашу оценку?')">
                            <input type="hidden" name="delete_rating" value="1">
                            <button type="submit" class="rate-delete-btn" title="Удалить оценку">🗑 Удалить</button>
                        </form>
                    </div>
                    <div class="rate-existing__scores">
                        <div class="rate-existing__total"><?= (int)$user_rating['total_score'] ?><sup>/90</sup></div>
                        <div class="rate-existing__breakdown">
                            <div class="breakdown-row"><span>Рифмы / Образы</span><b><?= (int)$user_rating['score_rhyme'] ?></b></div>
                            <div class="breakdown-row"><span>Структура / Ритмика</span><b><?= (int)$user_rating['score_structure'] ?></b></div>
                            <div class="breakdown-row"><span>Реализация стиля</span><b><?= (int)$user_rating['score_style'] ?></b></div>
                            <div class="breakdown-row"><span>Индивидуальность / Харизма</span><b><?= (int)$user_rating['score_individuality'] ?></b></div>
                            <div class="breakdown-row"><span>Атмосфера / Вайб</span><b><?= (int)$user_rating['score_atmosphere'] ?></b></div>
                        </div>
                    </div>
                    <?php if ($user_rating['has_review']): ?>
                    <div class="rate-existing__review">
                        <?php if ($user_rating['review_title']): ?>
                        <div class="rate-existing__rev-title"><?= htmlspecialchars($user_rating['review_title']) ?></div>
                        <?php endif; ?>
                        <div class="rate-existing__rev-text"><?= nl2br(htmlspecialchars($user_rating['review_text'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php else: ?>
                <div class="rate-tabs">
                    <button class="rate-tab active" data-tab="review">Рецензия</button>
                    <button class="rate-tab" data-tab="simple">Оценка без рецензии</button>
                </div>
                <form class="rate-form" method="POST">
                    <div class="rate-sliders">
                        <div class="slider-row">
                            <label>Рифмы / Образы</label>
                            <input type="range" name="score_rhyme" min="0" max="18" value="9" class="slider" data-out="out_rhyme">
                            <span class="slider-val" id="out_rhyme">9</span>
                        </div>
                        <div class="slider-row">
                            <label>Структура / Ритмика</label>
                            <input type="range" name="score_structure" min="0" max="18" value="9" class="slider" data-out="out_structure">
                            <span class="slider-val" id="out_structure">9</span>
                        </div>
                        <div class="slider-row">
                            <label>Реализация стиля</label>
                            <input type="range" name="score_style" min="0" max="18" value="9" class="slider" data-out="out_style">
                            <span class="slider-val" id="out_style">9</span>
                        </div>
                        <div class="slider-row">
                            <label>Индивидуальность / Харизма</label>
                            <input type="range" name="score_individuality" min="0" max="18" value="9" class="slider" data-out="out_individuality">
                            <span class="slider-val" id="out_individuality">9</span>
                        </div>
                        <div class="slider-row slider-row--full">
                            <label>Атмосфера / Вайб</label>
                            <input type="range" name="score_atmosphere" min="0" max="18" value="9" class="slider slider--accent" data-out="out_atmosphere">
                            <span class="slider-val" id="out_atmosphere">9</span>
                        </div>
                    </div>

                    <div class="review-fields" id="review-fields">
                        <input type="text" class="form__input" name="review_title" placeholder="Заголовок рецензии" maxlength="255">
                        <textarea class="form__input review-textarea" name="review_text"
                            placeholder="Текст рецензии (от 300 до 8500 символов)"
                            maxlength="8500" id="review-text-area"></textarea>
                        <div class="review-footer">
                            <button type="button" class="clear-btn" id="clear-draft">🗑 Очистить черновик</button>
                            <span class="char-count"><span id="char-count">0</span>/8500</span>
                        </div>
                    </div>

                    <div class="rate-submit-row">
                        <div class="total-score" id="total-score">45<sup>/90</sup></div>
                        <button type="submit" class="rate-submit-btn" title="Отправить">✔</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

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
                    <?php
                    $rev_id = (int)$rev['id'];
                    $rev_liked = !empty($user_review_likes[$rev_id]);
                    $rev_cnt = $review_likes_map[$rev_id] ?? 0;
                    ?>
                    <button class="review-like <?= $rev_liked ? 'active' : '' ?>"
                            data-review-id="<?= $rev_id ?>"
                            data-logged-in="<?= $logged_in ? '1' : '0' ?>">
                        <?= $rev_liked ? '♥' : '♡' ?> <span><?= $rev_cnt ?></span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

        </div>

        <aside class="poem-sidebar">

            <div class="sidebar-block">
                <div class="sidebar-block__title">МЕТКИ</div>
                <?php
                $tags = [
                    'Тип' => $poem['tag_type'],
                    'Жанр' => $poem['tag_genre'],
                    'Настроение' => $poem['tag_mood'],
                    'Стихосложение' => $poem['tag_versification'],
                    'Базовая стопа' => $poem['tag_foot'],
                    'Строфа' => $poem['tag_stanza'],
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

            <div class="sidebar-block">
                <div class="sidebar-block__title">ПОХОЖЕЕ</div>
                <?php foreach ($similar as $i => $s): ?>
                <div class="similar-item">
                    <div class="similar-title"><a href="poem.php?id=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['title']) ?></a></div>
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
    function requireLogin() {
        if (typeof window.openAuthModal === 'function') {
            window.openAuthModal('login');
        }
    }

    document.getElementById('login-to-rate')?.addEventListener('click', (e) => {
        e.preventDefault();
        requireLogin();
    });

    const sliders = document.querySelectorAll('.slider');
    function updateTotal() {
        let sum = 0;
        sliders.forEach(s => { sum += parseInt(s.value); });
        const maxTotal = sliders.length * 18;
        document.getElementById('total-score').innerHTML = sum + '<sup>/' + maxTotal + '</sup>';
    }
    sliders.forEach(s => {
        const outId = s.dataset.out;
        const out = document.getElementById(outId);
        if (out) out.textContent = s.value;
        s.addEventListener('input', () => {
            if (out) out.textContent = s.value;
            updateTotal();
        });
    });
    updateTotal();

    const textarea = document.getElementById('review-text-area');
    const charCount = document.getElementById('char-count');
    if (textarea) {
        textarea.addEventListener('input', () => {
            charCount.textContent = textarea.value.length;
        });
    }

    document.getElementById('clear-draft')?.addEventListener('click', () => {
        if (textarea) { textarea.value = ''; charCount.textContent = 0; }
        const titleInput = document.querySelector('input[name="review_title"]');
        if (titleInput) titleInput.value = '';
    });

    document.querySelectorAll('.rate-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.rate-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const isReview = tab.dataset.tab === 'review';
            document.getElementById('review-fields').style.display = isReview ? 'block' : 'none';
        });
    });

    let csrfToken = '';
    fetch('public/api/get_csrf.php')
        .then(r => r.json())
        .then(data => { csrfToken = data.token || ''; })
        .catch(() => {});

    document.getElementById('btn-like')?.addEventListener('click', async function() {
        if (this.dataset.loggedIn !== '1') { requireLogin(); return; }
        const poemId = this.dataset.poemId;
        try {
            const fd = new FormData();
            fd.append('poem_id', poemId);
            const res = await fetch('public/api/toggle_like.php', { method: 'POST', body: fd });
            const data = await res.json();
            const liked = data.action === 'added';
            this.innerHTML = (liked ? '♥' : '♡') + ' <span id="like-count">' + data.count + '</span>';
            this.classList.toggle('active', liked);
        } catch(e) { console.error('like error', e); }
    });

    document.getElementById('btn-fav')?.addEventListener('click', async function() {
        if (this.dataset.loggedIn !== '1') { requireLogin(); return; }
        const poemId = this.dataset.poemId;
        try {
            const fd = new FormData();
            fd.append('poem_id', poemId);
            const res = await fetch('public/api/toggle_favorite.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                body: fd
            });
            const data = await res.json();
            if (data.error) { console.error('fav error', data.error); return; }
            const faved = data.action === 'added';
            this.textContent = faved ? '★' : '☆';
            this.classList.toggle('active', faved);
        } catch(e) { console.error('fav error', e); }
    });

    document.querySelectorAll('.review-like').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (this.dataset.loggedIn !== '1') { requireLogin(); return; }
            const reviewId = this.dataset.reviewId;
            try {
                const fd = new FormData();
                fd.append('review_id', reviewId);
                const res = await fetch('public/api/toggle_review_like.php', { method: 'POST', body: fd });
                const data = await res.json();
                const liked = data.action === 'added';
                this.innerHTML = (liked ? '♥' : '♡') + ' <span>' + data.count + '</span>';
                this.classList.toggle('active', liked);
            } catch(e) { console.error('review like error', e); }
        });
    });
    </script>
</body>
</html>

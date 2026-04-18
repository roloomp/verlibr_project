<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/csrf.php';

$logged_in = !empty($_SESSION['logged_in']);

if ($logged_in && isset($_POST['logout'])) {
    csrf_check();
    session_destroy();
    header('Location: /');
    exit;
}

$user          = null;
$my_ratings    = [];
$liked_reviews = [];
$reviews_only  = [];
$scores_only   = [];
$ratings_count = 0;
$reviews_count = 0;
$fav_count     = 0;
$avg_score     = '—';

if ($logged_in) {
    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        csrf_check();
        $ext     = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed, true)) {
            $dir = __DIR__ . '/public/uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $fname)) {
                $path = 'public/uploads/avatars/' . $fname;
                $stmt = $conn->prepare("UPDATE profile SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $path, $user_id);
                $stmt->execute();
            }
        }
    }

    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        csrf_check();
        $ext     = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed, true)) {
            $dir = __DIR__ . '/public/uploads/banners/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'banner_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['banner']['tmp_name'], $dir . $fname)) {
                $path = 'public/uploads/banners/' . $fname;
                $stmt = $conn->prepare("UPDATE profile SET banner = ? WHERE id = ?");
                $stmt->bind_param("si", $path, $user_id);
                $stmt->execute();
            }
        }
    }

    if (isset($_POST['save_bio'])) {
        csrf_check();
        $bio  = mb_substr(trim($_POST['bio'] ?? ''), 0, 300, 'UTF-8');
        $stmt = $conn->prepare("UPDATE profile SET bio = ? WHERE id = ?");
        $stmt->bind_param("si", $bio, $user_id);
        $stmt->execute();
    }

    $stmt = $conn->prepare("SELECT id, name, email, avatar, banner, bio, created_at FROM profile WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $ratings_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ? AND has_review = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reviews_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $fav_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    $stmt = $conn->prepare("SELECT ROUND(AVG(total_score), 1) AS avg FROM ratings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $avg_score = $stmt->get_result()->fetch_assoc()['avg'] ?? '—';

    $stmt = $conn->prepare("
        SELECT r.id, r.total_score, r.has_review, r.created_at,
               p.id AS poem_id, p.title, p.author
        FROM ratings r
        JOIN poems p ON p.id = r.poem_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $my_ratings   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $reviews_only = array_filter($my_ratings, fn($r) => (int)$r['has_review'] === 1);
    $scores_only  = array_filter($my_ratings, fn($r) => (int)$r['has_review'] === 0);

    $stmt = $conn->prepare("
        SELECT rv.id, rv.total_score, rv.review_title, rv.review_text,
               rv.created_at, u.name AS reviewer_name,
               p.id AS poem_id, p.title AS poem_title, p.author AS poem_author
        FROM review_likes rl
        JOIN ratings rv ON rv.id = rl.review_id
        JOIN profile u  ON u.id  = rv.user_id
        JOIN poems p    ON p.id  = rv.poem_id
        WHERE rl.user_id = ?
        ORDER BY rl.created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $liked_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$active_tab    = $_GET['tab']    ?? 'ratings';
$active_subtab = $_GET['subtab'] ?? 'reviews';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль — Верлибр</title>
    <link rel="stylesheet" href="public/css/app.css">
    <link rel="stylesheet" href="public/css/profile.css">
</head>
<body>
    <my-header></my-header>

    <?php if (!$logged_in): ?>
    <auth-buttons></auth-buttons>
    <main>
        <div class="profile-guest">
            <div class="profile-guest__icon">👤</div>
            <h1 class="profile-guest__title">Вы не авторизованы</h1>
            <p class="profile-guest__sub">Войдите или зарегистрируйтесь, чтобы увидеть свой профиль</p>
            <div class="profile-guest__btns">
                <button class="auth-btn auth-btn--login" onclick="window.openAuthModal('login')">Войти</button>
                <button class="auth-btn auth-btn--register" onclick="window.openAuthModal('register')">Регистрация</button>
            </div>
        </div>
    </main>

    <?php else: ?>
    <main class="profile-page">

        <div class="profile-top">
            <div class="profile-card">
                <form method="POST" enctype="multipart/form-data" id="avatar-form">
                    <?= csrf_field() ?>
                    <div class="profile-avatar-wrap">
                        <?php if (!empty($user['avatar'])): ?>
                            <img class="profile-avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="Аватар">
                        <?php else: ?>
                            <div class="profile-avatar profile-avatar--placeholder">
                                <?= mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="profile-avatar-upload"
                                onclick="document.getElementById('avatar-file-input').click()">✎</button>
                        <input type="file" name="avatar" id="avatar-file-input" accept="image/*"
                               style="display:none" onchange="this.form.submit()">
                    </div>
                </form>

                <div class="profile-card__name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="profile-card__date">Дата регистрации: <?= date('d.m.Y', strtotime($user['created_at'])) ?></div>

                <div class="profile-card__bio" id="bio-display">
                    <?php if (!empty($user['bio'])): ?>
                        <span><?= nl2br(htmlspecialchars($user['bio'])) ?></span>
                    <?php else: ?>
                        <span class="profile-card__bio--placeholder">Биография так называемая</span>
                    <?php endif; ?>
                    <button type="button" class="profile-card__bio-edit" onclick="toggleBioEdit()">✎</button>
                </div>

                <form method="POST" id="bio-form" style="display:none">
                    <?= csrf_field() ?>
                    <textarea class="profile-bio-textarea" name="bio" maxlength="300"
                              placeholder="Расскажите о себе (до 300 символов)"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <div class="bio-actions">
                        <button type="submit" name="save_bio" class="profile-bio-save">Сохранить</button>
                        <button type="button" class="profile-bio-cancel" onclick="toggleBioEdit()">Отмена</button>
                    </div>
                </form>

                <form method="POST" style="margin-top:auto; width:100%">
                    <?= csrf_field() ?>
                    <button type="submit" name="logout" class="profile-logout">Выйти</button>
                </form>
            </div>

            <div class="profile-banner-wrap">
                <?php if (!empty($user['banner'])): ?>
                    <img class="profile-banner" src="<?= htmlspecialchars($user['banner']) ?>" alt="Обложка">
                <?php else: ?>
                    <div class="profile-banner profile-banner--placeholder"></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="banner-form">
                    <?= csrf_field() ?>
                    <button type="button" class="profile-banner-upload"
                            onclick="document.getElementById('banner-file-input').click()">✎ Сменить обложку</button>
                    <input type="file" name="banner" id="banner-file-input" accept="image/*"
                           style="display:none" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <div class="profile-tabs">
            <a href="?tab=ratings" class="profile-tab <?= $active_tab === 'ratings' ? 'active' : '' ?>">Рецензии и оценки</a>
            <a href="?tab=stats"   class="profile-tab <?= $active_tab === 'stats'   ? 'active' : '' ?>">Статистика</a>
            <a href="?tab=liked"   class="profile-tab <?= $active_tab === 'liked'   ? 'active' : '' ?>">Понравилось</a>
        </div>

        <?php if ($active_tab === 'ratings'): ?>
            <div class="profile-subtabs">
                <a href="?tab=ratings&subtab=reviews" class="profile-subtab <?= $active_subtab === 'reviews' ? 'active' : '' ?>">Рецензии</a>
                <a href="?tab=ratings&subtab=scores"  class="profile-subtab <?= $active_subtab === 'scores'  ? 'active' : '' ?>">Оценки</a>
            </div>

            <?php if ($active_subtab === 'reviews'): ?>
                <?php if (empty($reviews_only)): ?>
                    <div class="profile-empty">Вы ещё не оставляли рецензий</div>
                <?php else: ?>
                    <div class="ratings-grid">
                        <?php foreach ($reviews_only as $r): ?>
                        <a href="poem.php?id=<?= $r['poem_id'] ?>" class="rating-item">
                            <div class="rating-item__info">
                                <div class="rating-item__title"><?= htmlspecialchars($r['title']) ?></div>
                                <div class="rating-item__author"><?= htmlspecialchars($r['author']) ?></div>
                            </div>
                            <div class="rating-item__right">
                                <span class="rating-item__score"><?= (int)$r['total_score'] ?></span>
                                <span class="rating-item__badge">рецензия</span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <?php if (empty($scores_only)): ?>
                    <div class="profile-empty">Вы ещё не ставили оценки без рецензии</div>
                <?php else: ?>
                    <div class="ratings-grid">
                        <?php foreach ($scores_only as $r): ?>
                        <a href="poem.php?id=<?= $r['poem_id'] ?>" class="rating-item">
                            <div class="rating-item__info">
                                <div class="rating-item__title"><?= htmlspecialchars($r['title']) ?></div>
                                <div class="rating-item__author"><?= htmlspecialchars($r['author']) ?></div>
                            </div>
                            <div class="rating-item__right">
                                <span class="rating-item__score"><?= (int)$r['total_score'] ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($active_tab === 'stats'): ?>
            <div class="profile-stats">
                <div class="stat-item"><div class="stat-num"><?= $ratings_count ?></div><div class="stat-label">Оценок всего</div></div>
                <div class="stat-item"><div class="stat-num"><?= $reviews_count ?></div><div class="stat-label">Рецензий написано</div></div>
                <div class="stat-item"><div class="stat-num"><?= $fav_count ?></div><div class="stat-label">В избранном</div></div>
                <div class="stat-item"><div class="stat-num"><?= $avg_score ?: '—' ?></div><div class="stat-label">Средний балл</div></div>
            </div>

        <?php elseif ($active_tab === 'liked'): ?>
            <?php if (empty($liked_reviews)): ?>
                <div class="profile-empty">Вы ещё не лайкали чужие рецензии</div>
            <?php else: ?>
                <div class="liked-reviews">
                    <?php foreach ($liked_reviews as $rev): ?>
                    <div class="liked-review-card">
                        <div class="liked-review-card__header">
                            <div class="liked-review-card__poem">
                                <a href="poem.php?id=<?= $rev['poem_id'] ?>"><?= htmlspecialchars($rev['poem_title']) ?></a>
                            </div>
                            <div class="liked-review-card__score"><?= (int)$rev['total_score'] ?></div>
                        </div>
                        <div class="liked-review-card__author"><?= htmlspecialchars($rev['poem_author']) ?> · <?= htmlspecialchars($rev['reviewer_name']) ?></div>
                        <?php if ($rev['review_title']): ?>
                        <div class="liked-review-card__title"><?= htmlspecialchars($rev['review_title']) ?></div>
                        <?php endif; ?>
                        <div class="liked-review-card__text"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>
    <?php endif; ?>

    <script src="public/js/header.js"></script>
    <script>
    function toggleBioEdit() {
        var display = document.getElementById('bio-display');
        var form    = document.getElementById('bio-form');
        var hidden  = form.style.display === 'none';
        display.style.display = hidden ? 'none' : 'flex';
        form.style.display    = hidden ? 'block' : 'none';
        if (hidden) form.querySelector('textarea').focus();
    }
    </script>
</body>
</html>

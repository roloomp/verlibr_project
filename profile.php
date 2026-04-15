<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

$logged_in = !empty($_SESSION['logged_in']);

// Обработка загрузки аватара
if ($logged_in && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $uid = (int)$_SESSION['user_id'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, $allowed_types) && $_FILES['avatar']['size'] < 2 * 1024 * 1024) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'public/uploads/avatars/' . $uid . '_' . time() . '.' . strtolower($ext);
        @mkdir(dirname($filename), 0755, true);
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filename)) {
            $conn = db_connect();
            $stmt = $conn->prepare("UPDATE profile SET avatar = ? WHERE id = ?");
            $stmt->bind_param("si", $filename, $uid);
            $stmt->execute();
        }
    }
    header('Location: profile.php');
    exit;
}

// Обработка сохранения описания
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_desc'])) {
    $uid = (int)$_SESSION['user_id'];
    $desc = trim($_POST['description'] ?? '');
    $desc = mb_substr($desc, 0, 500, 'UTF-8');
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE profile SET description = ? WHERE id = ?");
    $stmt->bind_param("si", $desc, $uid);
    $stmt->execute();
    header('Location: profile.php');
    exit;
}

// Данные для залогиненного
$user = null;
$ratings = [];
$liked_reviews = [];
$stats = ['ratings' => 0, 'reviews' => 0, 'likes_given' => 0, 'favs' => 0];

if ($logged_in) {
    $conn = db_connect();
    $uid = (int)$_SESSION['user_id'];

    // Данные пользователя
    $stmt = $conn->prepare("SELECT id, name, email, avatar, description FROM profile WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Статистика
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stats['ratings'] = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ? AND has_review = 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stats['reviews'] = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // likes таблица может не существовать — игнорируем ошибку
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM likes WHERE user_id = {$uid}");
    if ($res) $stats['likes_given'] = (int)$res->fetch_assoc()['cnt'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stats['favs'] = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // Свои оценки (последние 24)
    $stmt = $conn->prepare("
        SELECT r.id, r.total_score, r.has_review, r.created_at,
               p.id AS poem_id, p.title, p.author
        FROM ratings r
        JOIN poems p ON p.id = r.poem_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 24
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Понравившиеся рецензии
    $conn->query("CREATE TABLE IF NOT EXISTS review_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        review_id INT NOT NULL,
        created_at DATETIME DEFAULT NOW(),
        UNIQUE KEY uq_rev_like (user_id, review_id)
    )");
    $stmt = $conn->prepare("
        SELECT rv.id, rv.total_score, rv.review_title, rv.review_text,
               p.id AS poem_id, p.title AS poem_title, p.author AS poem_author,
               u.name AS reviewer_name
        FROM review_likes rl
        JOIN ratings rv ON rv.id = rl.review_id
        JOIN poems p ON p.id = rv.poem_id
        JOIN profile u ON u.id = rv.user_id
        WHERE rl.user_id = ? AND rv.has_review = 1
        ORDER BY rl.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $liked_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
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


    <main>
        <div class="profile-guest">
            <div class="profile-guest__icon">👤</div>
            <h1 class="profile-guest__title">Вы не вошли в аккаунт</h1>
            <p class="profile-guest__sub">Войдите или зарегистрируйтесь, чтобы увидеть свой профиль</p>
            <div class="profile-guest__btns">
                <button class="auth-btn auth-btn--login" onclick="window.openAuthModal('login')">Войти</button>
                <button class="auth-btn auth-btn--register" onclick="window.openAuthModal('register')">Регистрация</button>
            </div>
        </div>
    </main>

    <?php else: ?>

    <main class="profile-page">

        <!-- Шапка профиля -->
        <div class="profile-header">

            <!-- Аватар -->
            <div class="profile-avatar-wrap">
                <?php if (!empty($user['avatar'])): ?>
                    <img class="profile-avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="Аватар">
                <?php else: ?>
                    <div class="profile-avatar--placeholder">
                        <?= mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" style="display:inline">
                    <button type="button" class="profile-avatar-upload" title="Сменить фото"
                            onclick="document.getElementById('avatar-file-input').click()">✎</button>
                    <input type="file" id="avatar-file-input" name="avatar" accept="image/*"
                           onchange="this.form.submit()">
                </form>
            </div>

            <!-- Инфо -->
            <div class="profile-info">
                <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>

                <!-- Описание -->
                <div id="desc-view" class="profile-desc-wrap">
                    <?php if (!empty($user['description'])): ?>
                        <span class="profile-desc"><?= nl2br(htmlspecialchars($user['description'])) ?></span>
                    <?php else: ?>
                        <span class="profile-desc profile-desc--placeholder">Нет описания</span>
                    <?php endif; ?>
                    <button class="profile-desc-edit" onclick="toggleDescEdit(true)">✎</button>
                </div>
                <div id="desc-edit" style="display:none">
                    <form method="POST">
                        <textarea class="profile-desc-textarea" name="description"
                                  maxlength="500" placeholder="Расскажите о себе (до 500 символов)"><?= htmlspecialchars($user['description'] ?? '') ?></textarea>
                        <div>
                            <button type="submit" name="save_desc" value="1" class="profile-desc-save">Сохранить</button>
                            <button type="button" class="profile-desc-edit" onclick="toggleDescEdit(false)" style="margin-left:8px">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Выход -->
            <a href="logout.php" class="profile-logout"
               onclick="return confirm('Выйти из аккаунта?')">Выйти</a>
        </div>

        <!-- Статистика -->
        <div class="profile-stats">
            <div class="stat-item">
                <div class="stat-num"><?= $stats['ratings'] ?></div>
                <div class="stat-label">Оценок</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?= $stats['reviews'] ?></div>
                <div class="stat-label">Рецензий</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?= $stats['likes_given'] ?></div>
                <div class="stat-label">Лайков отдано</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?= $stats['favs'] ?></div>
                <div class="stat-label">В избранном</div>
            </div>
        </div>

        <!-- Мои оценки -->
        <h2 class="profile-section-title">Мои оценки</h2>

        <?php if (empty($ratings)): ?>
        <div class="profile-empty">Вы ещё не оценили ни одного стихотворения</div>
        <?php else: ?>
        <div class="ratings-grid">
            <?php foreach ($ratings as $r): ?>
            <div class="rating-item">
                <div class="rating-item__info">
                    <div class="rating-item__title">
                        <a href="poem.php?id=<?= $r['poem_id'] ?>"><?= htmlspecialchars($r['title']) ?></a>
                    </div>
                    <div class="rating-item__author"><?= htmlspecialchars($r['author']) ?></div>
                </div>
                <div class="rating-item__right">
                    <span class="rating-item__score"><?= (int)$r['total_score'] ?></span>
                    <?php if ($r['has_review']): ?>
                    <span class="rating-item__badge">рецензия</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Понравившиеся рецензии -->
        <h2 class="profile-section-title">Понравившиеся рецензии</h2>

        <?php if (empty($liked_reviews)): ?>
        <div class="profile-empty">Вы ещё не лайкали рецензии</div>
        <?php else: ?>
        <div class="liked-reviews">
            <?php foreach ($liked_reviews as $lr): ?>
            <div class="liked-review-card">
                <div class="liked-review-card__header">
                    <div class="liked-review-card__poem">
                        <a href="poem.php?id=<?= $lr['poem_id'] ?>"><?= htmlspecialchars($lr['poem_title']) ?></a>
                    </div>
                    <div class="liked-review-card__score"><?= (int)$lr['total_score'] ?></div>
                </div>
                <div class="liked-review-card__author">
                    <?= htmlspecialchars($lr['poem_author']) ?> · <?= htmlspecialchars($lr['reviewer_name']) ?>
                </div>
                <?php if ($lr['review_title']): ?>
                <div class="liked-review-card__title"><?= htmlspecialchars($lr['review_title']) ?></div>
                <?php endif; ?>
                <div class="liked-review-card__text"><?= htmlspecialchars($lr['review_text']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>

    <script>
    function toggleDescEdit(show) {
        document.getElementById('desc-view').style.display = show ? 'none' : 'flex';
        document.getElementById('desc-edit').style.display = show ? 'block' : 'none';
    }
    </script>

    <?php endif; ?>

    <script src="public/js/header.js"></script>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/csrf.php';

$auth_error = '';
$action = $_POST['action'] ?? '';

if ($action === 'login') {
    csrf_check();
    $email    = trim($_POST['login_email'] ?? '');
    $password = $_POST['login_password'] ?? '';

    if ($email === '' || $password === '') {
        $auth_error = 'Заполните все поля.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $auth_error = 'Некорректный email.';
    } elseif (mb_strlen($password) < 7) {
        $auth_error = 'Пароль слишком короткий (минимум 7 символов).';
    } else {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT id, name, password FROM profile WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows !== 1) {
            $auth_error = 'Пользователь не найден.';
        } else {
            $user = $res->fetch_assoc();
            if (!password_verify($password, $user['password'])) {
                $auth_error = 'Неверный пароль.';
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['logged_in'] = true;
                header('Location: /');
                exit;
            }
        }
    }
}

if ($action === 'register') {
    csrf_check();
    $email     = trim($_POST['register_email'] ?? '');
    $name      = trim($_POST['register_nickname'] ?? '');
    $password  = $_POST['register_password'] ?? '';
    $password2 = $_POST['register_verify_password'] ?? '';

    if ($email === '' || $name === '' || $password === '') {
        $auth_error = 'Заполните все поля.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $auth_error = 'Некорректный email.';
    } elseif (mb_strlen($password) < 7) {
        $auth_error = 'Пароль слишком короткий (минимум 7 символов).';
    } elseif ($password !== $password2) {
        $auth_error = 'Пароли не совпадают.';
    } else {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT id FROM profile WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $auth_error = 'Этот email уже зарегистрирован.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO profile (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hashed);
            $stmt->execute();
            $_SESSION['user_id']   = $conn->insert_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['logged_in'] = true;
            header('Location: /');
            exit;
        }
    }
}

$logged_in = !empty($_SESSION['logged_in']);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');
$csrf      = csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Верлибр — поэзия на каждый день</title>
    <link rel="stylesheet" href="public/css/app.css">
</head>
<body>
    <my-header></my-header>

    <?php if (!$logged_in): ?>
    <div class="auth-topbar">
        <button class="auth-btn auth-btn--login" id="btn-login-top">Вход</button>
        <button class="auth-btn auth-btn--register" id="btn-register-top">Регистрация</button>
    </div>

    <div class="overlay" id="overlay">
        <div class="modal" role="dialog" aria-modal="true">
            <button class="modal__close" id="btn-close" aria-label="Закрыть">✕</button>

            <form class="form-panel <?= ($auth_error && $action === 'login') ? 'active' : '' ?>" id="panel-login" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <div class="form__title">Вход</div>
                <div class="form__subtitle">Введите свои данные для входа в аккаунт</div>

                <label class="form__label">Email <span class="required">*</span></label>
                <input class="form__input" type="email" name="login_email" placeholder="Ваш email" required>

                <div class="form__label">
                    Пароль <span class="required">*</span>
                    <button type="button" class="form__link-btn">Забыли пароль?</button>
                </div>
                <input class="form__input" type="password" name="login_password" placeholder="Ваш пароль" required>

                <?php if ($auth_error && $action === 'login'): ?>
                    <div class="error-message"><?= htmlspecialchars($auth_error) ?></div>
                <?php endif; ?>

                <button class="form__btn form__btn--primary" type="submit">Войти</button>
                <button class="form__btn form__btn--secondary" id="btn-go-register" type="button">Зарегистрироваться</button>
            </form>

            <form class="form-panel <?= ($auth_error && $action === 'register') ? 'active' : '' ?>" id="panel-register" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
                <div class="form__title">Создать аккаунт</div>

                <label class="form__label">Email <span class="required">*</span></label>
                <div class="form__hint">Будет также логином для авторизации</div>
                <input class="form__input" type="email" name="register_email" placeholder="mail@example.com" required>

                <label class="form__label">Отображаемое имя <span class="required">*</span></label>
                <div class="form__hint">Ваш никнейм</div>
                <input class="form__input" type="text" name="register_nickname" required>

                <label class="form__label">Пароль <span class="required">*</span></label>
                <input class="form__input" type="password" name="register_password" required>

                <label class="form__label">Подтвердите пароль <span class="required">*</span></label>
                <input class="form__input" type="password" name="register_verify_password" required>

                <?php if ($auth_error && $action === 'register'): ?>
                    <div class="error-message"><?= htmlspecialchars($auth_error) ?></div>
                <?php endif; ?>

                <button class="form__btn form__btn--primary" type="submit">Создать аккаунт</button>
                <p class="form__footer">
                    Уже есть аккаунт?
                    <button class="form__footer-btn" id="btn-go-login" type="button">Войти</button>
                </p>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <main>
        <section class="hero">
            <p class="hero__sub">— ПОЭЗИЯ НА КАЖДЫЙ ДЕНЬ</p>
            <p class="hero__title">Находите стихи, которые <span class="accent">говорят</span> с вами</p>
        </section>

        <section class="search-section">
            <div class="search-wrapper">
                <input type="text" placeholder="Строка, цитата, настроение или автор.." class="search-input" id="main-search">
                <a href="search.php" class="search-icon" id="search-go" aria-label="Найти">
                    <img src="public/assets/icons/search.png" alt="">
                </a>
            </div>
        </section>

        <section class="tags">
            <button class="tag" onclick="window.location='search.php?q=Александр+Пушкин'">Александр Пушкин</button>
            <button class="tag" onclick="window.location='search.php?q=Весна'">Весна</button>
            <button class="tag" onclick="window.location='search.php?q=И+грянул+бой'">И грянул бой, Полтавской бой!</button>
        </section>

        <hr class="divider">

        <section class="columns">
            <article class="column">
                <h2>Находки дня</h2>
                <div id="poems-day"></div>
            </article>
            <article class="column">
                <h2>Выбор редакции</h2>
                <div id="poems-editor"></div>
            </article>
            <article class="column">
                <h2>Лучшие авторы</h2>
                <div class="authors-list"></div>
            </article>
        </section>
    </main>

    <script src="public/js/header.js"></script>
    <script src="public/js/main.js"></script>
    <script>
    document.getElementById('search-go').addEventListener('click', function(e) {
        var q = document.getElementById('main-search').value.trim();
        if (q) {
            e.preventDefault();
            window.location = 'search.php?q=' + encodeURIComponent(q);
        }
    });
    document.getElementById('main-search').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var q = this.value.trim();
            if (q) window.location = 'search.php?q=' + encodeURIComponent(q);
        }
    });

    (function() {
        var overlay = document.getElementById('overlay');
        if (!overlay) return;
        var modal  = overlay.querySelector('.modal');
        var panels = overlay.querySelectorAll('.form-panel');

        function openModal(tab) {
            panels.forEach(function(p) { p.classList.remove('active'); });
            overlay.querySelector('#panel-' + tab).classList.add('active');
            overlay.classList.add('open');
        }
        function closeModal() {
            overlay.classList.remove('open');
        }

        document.getElementById('btn-login-top')   ?.addEventListener('click', function() { openModal('login'); });
        document.getElementById('btn-register-top') ?.addEventListener('click', function() { openModal('register'); });
        document.getElementById('btn-close')        ?.addEventListener('click', closeModal);
        document.getElementById('btn-go-register')  ?.addEventListener('click', function() { openModal('register'); });
        document.getElementById('btn-go-login')     ?.addEventListener('click', function() { openModal('login'); });

        overlay.addEventListener('click', function(e) {
            if (!modal.contains(e.target)) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        <?php if ($auth_error): ?>
        openModal('<?= $action === 'register' ? 'register' : 'login' ?>');
        <?php endif; ?>
    })();
    </script>
</body>
</html>

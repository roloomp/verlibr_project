<?php
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

$auth_error = '';
$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $result = handle_login(
        db_connect(),
        trim($_POST['login_email']    ?? ''),
        $_POST['login_password'] ?? ''
    );
    if (!$result['ok']) {
        $auth_error = $result['error'];
    } else {
        header('Location: /');
        exit;
    }
} elseif ($action === 'register') {
    $result = handle_register(
        db_connect(),
        trim($_POST['register_email']           ?? ''),
        trim($_POST['register_nickname']        ?? ''),
        $_POST['register_password']        ?? '',
        $_POST['register_verify_password'] ?? ''
    );
    if (!$result['ok']) {
        $auth_error = $result['error'];
    } else {
        header('Location: /');
        exit;
    }
}

$logged_in = !empty($_SESSION['logged_in']);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');
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
        <button class="auth-btn auth-btn--login"    id="btn-login-top">Вход</button>
        <button class="auth-btn auth-btn--register" id="btn-register-top">Регистрация</button>
    </div>

    <div class="overlay" id="overlay">
        <div class="modal" role="dialog" aria-modal="true">
            <button class="modal__close" id="btn-close" aria-label="Закрыть">✕</button>

            <!-- Форма входа -->
            <form class="form-panel <?= ($auth_error && ($action === 'login' || $action === '')) ? 'active' : '' ?>" id="panel-login" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form__title">Вход</div>
                <div class="form__subtitle">Введите свои данные для входа в аккаунт</div>

                <label class="form__label">Email <span class="required">*</span></label>
                <input class="form__input" type="email" placeholder="Ваш email" name="login_email" required>

                <div class="form__label">
                    Пароль <span class="required">*</span>
                    <button type="button" class="form__link-btn">Забыли пароль?</button>
                </div>
                <input class="form__input" type="password" placeholder="Ваш пароль" name="login_password" required>

                <?php if ($auth_error && $action === 'login'): ?>
                    <div class="error-message"><?= htmlspecialchars($auth_error) ?></div>
                <?php endif; ?>

                <button class="form__btn form__btn--primary" type="submit">Войти</button>
                <button class="form__btn form__btn--secondary" id="btn-go-register" type="button">Зарегистрироваться</button>
            </form>

            <!-- Форма регистрации -->
            <form class="form-panel <?= ($auth_error && $action === 'register') ? 'active' : '' ?>" id="panel-register" method="POST">
                <input type="hidden" name="action" value="register">
                <div class="form__title">Создать аккаунт</div>

                <label class="form__label">Email <span class="required">*</span></label>
                <div class="form__hint">Будет также логином для авторизации</div>
                <input class="form__input" type="email" placeholder="mail@example.com" name="register_email" required>

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
                <input
                    type="text"
                    name="main-screen-search-input"
                    placeholder="Строка, цитата, настроение или автор.."
                    class="search-input"
                    aria-label="Поиск стихотворений"
                >
                <a href="search.php" class="search-icon" aria-label="Найти">
                    <img src="public/assets/icons/search.png" alt="">
                </a>
            </div>
        </section>

        <section class="tags" aria-label="Популярные запросы">
            <button class="tag">Александр Пушкин</button>
            <button class="tag">Весна</button>
            <button class="tag">И грянул бой, Полтавской бой!</button>
        </section>

        <hr class="divider">

        <section class="columns" aria-label="Подборки">
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
    (function() {
        const overlay    = document.getElementById('overlay');
        if (!overlay) return;

        const modal      = overlay.querySelector('.modal');
        const panels     = overlay.querySelectorAll('.form-panel');

        function openModal(tab) {
            panels.forEach(p => p.classList.remove('active'));
            overlay.querySelector('#panel-' + tab).classList.add('active');
            overlay.classList.add('open');
        }
        function closeModal() {
            overlay.classList.remove('open');
        }

        document.getElementById('btn-login-top')   ?.addEventListener('click', () => openModal('login'));
        document.getElementById('btn-register-top') ?.addEventListener('click', () => openModal('register'));
        document.getElementById('btn-close')        ?.addEventListener('click', closeModal);
        document.getElementById('btn-go-register')  ?.addEventListener('click', () => openModal('register'));
        document.getElementById('btn-go-login')     ?.addEventListener('click', () => openModal('login'));

        overlay.addEventListener('click', (e) => {
            if (!modal.contains(e.target)) closeModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });

        // Открыть модал при ошибке
        <?php if ($auth_error): ?>
        openModal('<?= $action === 'register' ? 'register' : 'login' ?>');
        <?php endif; ?>
    })();
    </script>
</body>
</html>

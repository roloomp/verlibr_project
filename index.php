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
    <auth-buttons data-error="<?= htmlspecialchars($auth_error) ?>"></auth-buttons>
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
                <a href="#" class="search-icon" aria-label="Найти">
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
                <h2>Выбор Редакции</h2>
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
</body>
</html>

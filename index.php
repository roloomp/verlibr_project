<?php
session_start();
require_once __DIR__ . '/config/csrf.php';

$logged_in = !empty($_SESSION['logged_in']);
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
    <auth-buttons></auth-buttons>
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
            <button class="tag" onclick="window.location='search.php?q=Канал+Грибоедова'">Канал Грибоедова</button>
            <button class="tag" onclick="window.location='search.php?q=Ночь'">Ночь</button>
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
    </script>
</body>
</html>

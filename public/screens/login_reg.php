<?php
$host = '127.127.126.25';
$user = 'root';
$pass = '';
$base = 'test_sql';
$conn = mysqli_connect($host, $user, $pass, $base);

if (isset($_POST['login'])) {
    $login_email = $_POST['user_email'];
    $sql = "SELECT `email`, `password` FROM `profile` WHERE `email` = '$login_email'";
    echo $sql;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>example.local</title>
    <link rel="stylesheet" href="public/css/index.css">
    <link rel="stylesheet" href="public/css/header.css">
    <link rel="stylesheet" href="public/css/test.css">
    <script src="public/components/header.js"></script>
    <script src="public/components/test.js"></script>
</head>
<body>
    <my-header></my-header>
    <auth-buttons></auth-buttons>
    
    <div class="overlay" id="overlay" onclick="handleOverlayClick(event)">
        <div class="modal">
            <button class="close-btn" onclick="closeModal()">✕</button>

            <div class="modal-tabs">
                <button class="tab" id="tab-login" onclick="switchTab('login')">Вход</button>
                <button class="tab" id="tab-register" onclick="switchTab('register')">Регистрация</button>
            </div>

            <div class="form-panel" id="panel-login" method="POST">
                <input type="email" name="user_email" placeholder="Email" required>
                <input type="password" name="user_password" placeholder="Пароль" required>
                <button name="login">Войти</button>
                <button>Зарегестрироваться</button>
            </div>

            <div class="form-panel" id="panel-register" method="POST">
                <input type="email" name="new_user_email" placeholder="Email">
                <input type="text" name="new_user_name" placeholder="Имя">
                <input type="password" name="new_user_password" placeholder="Пароль">
                <input type="password" name="new_user_repeat_pass" placeholder="Повторите пароль">
                <button name="register">Создать аккаунт</button>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();
$host = '127.127.126.25';
$user = 'root';
$pass = '';
$base = 'test_sql';
$conn = mysqli_connect($host, $user, $pass, $base);

$action = $_POST['action'] ?? '';
switch ($action) {
    case 'login':
        $email = $_POST['login_email'] ?? '';
        $password = $_POST['login_password'] ?? '';
        if ($password == '' || $email == '') {
            echo "<script>console.log('Ошибка');</script>";
            break;
        }
        if (mb_strlen($password, 'UTF-8') < 7) {
            echo "<script>console.log('Короткий пароль');</script>";
            break;
        }

        $sql = "SELECT id, name, description, email, password FROM profile WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['logged_in'] = true;
                echo "<script>console.log('УСПЕХ');</script>";
                echo "<script>console.log('Email: " . $email . "');</script>";
                echo "<script>console.log('Password: " . $password . "');</script>";
            }
            else {
                echo "<script>console.log('Неверный пароль');</script>";
                break;
            }
        }
        else {
            echo "<script>console.log('Пользователь не найден');</script>";
            break;
        }
        break;
    case 'register':
        $email = $_POST['register_email'] ?? '';
        $name = $_POST['register_nickname'] ?? '';
        $password = $_POST['register_password'] ?? '';
        $verify_password = $_POST['register_verify_password'] ?? '';

        if ($email === '' || $name === '' || $password === '') {
            echo "<script>console.log('Ошибка');</script>";
            break;
        }
        if ($password !== $verify_password) {
            echo "<script>console.log('Пароли не совпадают');</script>";
            break;
        }
        $check = mysqli_query($conn, "SELECT id FROM profile WHERE email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            echo "<script>console.log('Этот email уже зарегистрирован');</script>";
            break;
        }

        $insert = mysqli_query($conn,
            "INSERT INTO profile (name, email, password) VALUES ('$name', '$email', '$password')"
        );
        $_SESSION['user_id'] = mysqli_insert_id($conn);
        $_SESSION['logged_in'] = true;

        echo "<script>console.log('Email: " . $email . "');</script>";
        echo "<script>console.log('Name: " . $name . "');</script>";
        echo "<script>console.log('Password: " . $password . "');</script>";
        echo "<script>console.log('Verify Password: " . $verify_password . "');</script>";
        break;
    default:
        break;
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
    <link rel="stylesheet" href="public/css/main-screen.css">
    <script src="/public/components/main_screen.js"></script>
</head>
<body>
    <my-header></my-header>
    <?php if (empty($_SESSION['logged_in'])): ?>
    <auth-buttons></auth-buttons>
    <?php endif; ?>
    <div class="main-text">
        <p class="low-text">— ПОЭЗИЯ НА КАЖДЫЙ ДЕНЬ</p>
        <p class="big-text">Находите стихи, которые <span style="color: #CC7D00;">говорят</span> с вами</p>
    </div>
    <div class="input-search">
        <form>
            <div class="search-wrapper">
            <input type="text" 
                name="main-screen-search-input" 
                placeholder="Строка, цитата, настроение или автор.."
                class="search-input">
            <a href="#" class="search-icon">
                <img src="public/source/search.png" alt="search">
            </a>
            </div>
        </form>
    </div>
    <div class="tags">
        <button class="tag">Александр Пушкин</button>
        <button class="tag">Весна</button>
        <button class="tag">И грянул бой, Полтавской бой!</button>
    </div>
    <hr class="line">
    <div class="container">
        <div class="column">
            <h3>Находки дня</h3>
            <div id="poems-day"></div>
        </div>
        <div class="column">
            <h3>Выбор Редакции</h3>
            <div id="poems-editor"></div>
        </div>
        <div class="column">
            <h3>Лучшие авторы</h3>
            <main-screen-author-item></main-screen-author-item>
            <hr>
            <main-screen-author-item></main-screen-author-item>
            <hr>
            <main-screen-author-item></main-screen-author-item>
            <hr>
            <main-screen-author-item></main-screen-author-item>
            <hr>
            <main-screen-author-item></main-screen-author-item>
            <hr>
            <main-screen-author-item></main-screen-author-item>
            <hr>
            <main-screen-author-item></main-screen-author-item>
            <hr>
            <main-screen-author-item></main-screen-author-item>
        </div>
    </div>
</body>
</html>
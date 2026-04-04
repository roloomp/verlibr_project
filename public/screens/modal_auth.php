<?php
// "База данных" — обычный массив
$users = [
    'admin' => '12345',
    'demo' => 'demo123'
];


// РЕГИСТРАЦИЯ
if (isset($_POST['register'])) {
    $new_login = trim($_POST['new_login']);
    $new_pass = $_POST['new_password'];
    
    if (empty($new_login) || empty($new_pass)) {
        $message = 'Ошибка: Заполните все поля!';
    } elseif (isset($users[$new_login])) {
        $message = 'Ошибка: Такой логин уже существует!';
    } else {
        $users[$new_login] = $new_pass;
        $message = 'Успех: Регистрация прошла успешно! Теперь войдите.';
    }
}

// ВХОД
if (isset($_POST['login'])) {
    $login = $_POST['login'];
    $password = $_POST['password'];
    
    if (isset($users[$login]) && $users[$login] === $password) {
        $message = 'Успех: Добро пожаловать, ' . $login . '!';
    } else {
        $message = 'Ошибка: Неверный логин или пароль!';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Регистрация и вход</title>
</head>
<body>

<h2>Регистрация и вход</h2>

<?php
if ($message) {
    echo '<p><strong>' . $message . '</strong></p>';
}
?>

<h3>Регистрация</h3>
<form method="POST">
    Логин: <input type="text" name="new_login" required><br><br>
    Пароль: <input type="password" name="new_password" required><br><br>
    <button type="submit" name="register">Зарегистрироваться</button>
</form>

<hr>

<h3>Вход</h3>
<form method="POST">
    Логин: <input type="text" name="login" required><br><br>
    Пароль: <input type="password" name="password" required><br><br>
    <button type="submit" name="login">Войти</button>
</form>

<hr>
<p><strong>Тестовые пользователи:</strong> admin / 12345 | demo / demo123</p>

</body>
</html>
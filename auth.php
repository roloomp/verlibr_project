<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/csrf.php';

// Куда вернуть после логина/регистрации
$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';

// Защита от редиректа на чужой сайт
$host = parse_url($redirect, PHP_URL_HOST);
if ($host && $host !== $_SERVER['HTTP_HOST']) {
    $redirect = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$action = $_POST['action'] ?? '';

// CSRF проверка
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['auth_error'] = 'Ошибка безопасности. Попробуйте ещё раз.';
    header('Location: ' . $redirect);
    exit;
}

$conn = db_connect();

if ($action === 'login') {
    $email    = trim($_POST['login_email']    ?? '');
    $password = trim($_POST['login_password'] ?? '');

    $result = handle_login($conn, $email, $password);

    if (!$result['ok']) {
        $_SESSION['auth_error'] = $result['error'];
        header('Location: ' . $redirect);
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

if ($action === 'register') {
    $email     = trim($_POST['register_email']           ?? '');
    $name      = trim($_POST['register_nickname']        ?? '');
    $password  = trim($_POST['register_password']        ?? '');
    $password2 = trim($_POST['register_verify_password'] ?? '');

    $result = handle_register($conn, $email, $name, $password, $password2);

    if (!$result['ok']) {
        $_SESSION['auth_error'] = $result['error'];
        header('Location: ' . $redirect);
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

// Неизвестный action
header('Location: ' . $redirect);
exit;

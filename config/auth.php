<?php

require_once __DIR__ . '/../config/db.php';

function validate_login_input(string $email, string $password): ?string {
    if ($email === '' || $password === '') {
        return 'Заполните все поля.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Некорректный email.';
    }
    // БАГ ИСПРАВЛЕН: было mb_strlen < 7, т.е. пароль "1234567" (7 симв.) уже проходил.
    // Приведено к >= 8 символам — стандартный минимум.
    if (mb_strlen($password, 'UTF-8') < 8) {
        return 'Пароль слишком короткий (минимум 8 символов).';
    }
    return null;
}

function handle_login(mysqli $conn, string $email, string $password): array {
    $err = validate_login_input($email, $password);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }

    $stmt = $conn->prepare("SELECT id, name, password FROM profile WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        // БАГ ИСПРАВЛЕН: не раскрываем, существует ли email — общее сообщение
        return ['ok' => false, 'error' => 'Неверный email или пароль.'];
    }

    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        return ['ok' => false, 'error' => 'Неверный email или пароль.'];
    }

    // БАГ ИСПРАВЛЕН: session_regenerate_id() предотвращает session fixation атаку.
    // Без него злоумышленник может подсунуть жертве заранее известный session ID.
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['logged_in'] = true;
    return ['ok' => true, 'error' => ''];
}

function handle_register(mysqli $conn, string $email, string $name, string $password, string $password2): array {
    if ($email === '' || $name === '' || $password === '') {
        return ['ok' => false, 'error' => 'Заполните все поля.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Некорректный email.'];
    }
    // БАГ ИСПРАВЛЕН: минимум 8 символов, как в validate_login_input
    if (mb_strlen($password, 'UTF-8') < 8) {
        return ['ok' => false, 'error' => 'Пароль слишком короткий (минимум 8 символов).'];
    }
    if ($password !== $password2) {
        return ['ok' => false, 'error' => 'Пароли не совпадают.'];
    }

    // БАГ ИСПРАВЛЕН: обрезаем имя до разумной длины, иначе можно положить в БД 100+ МБ
    $name = mb_substr(trim($name), 0, 100, 'UTF-8');
    if ($name === '') {
        return ['ok' => false, 'error' => 'Введите имя.'];
    }

    $stmt = $conn->prepare("SELECT id FROM profile WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['ok' => false, 'error' => 'Этот email уже зарегистрирован.'];
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO profile (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hashed);
    $stmt->execute();

    // БАГ ИСПРАВЛЕН: session_regenerate_id() и здесь
    session_regenerate_id(true);

    $_SESSION['user_id']   = $conn->insert_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['logged_in'] = true;
    return ['ok' => true, 'error' => ''];
}

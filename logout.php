<?php
session_start();

// БАГ ИСПРАВЛЕН: после session_destroy() редиректили на profile.php.
// Но profile.php требует авторизации — гость получал страницу "вы не авторизованы".
// Логичнее отправлять на главную.
session_destroy();

// БАГ ИСПРАВЛЕН: добавлен setcookie для явного сброса session cookie.
// session_destroy() убивает данные на сервере, но cookie в браузере остаётся
// до закрытия вкладки. Явный сброс — хорошая практика.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

header('Location: /');
exit;

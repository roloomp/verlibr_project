<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$error = $_SESSION['auth_error'] ?? '';
$tab   = $_SESSION['auth_tab']   ?? 'login';

unset($_SESSION['auth_error'], $_SESSION['auth_tab']);

echo json_encode([
    'error' => $error,
    'tab'   => $tab,
], JSON_UNESCAPED_UNICODE);

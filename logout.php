<?php
// logout.php
session_start();

// Регенерация ID сессии перед уничтожением для безопасности
session_regenerate_id(true);

// Очистка всех данных сессии
$_SESSION = array();

// Удаление cookie сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Уничтожение сессии
session_destroy();

// Перенаправление на главную
header('Location: index.php');
exit;
?>
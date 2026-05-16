<?php
// router.php - Безопасная маршрутизация без использования пользовательских параметров в include
session_start();
require_once 'config.php';

// Установка защитных заголовков
setSecurityHeaders();

// Белый список разрешенных маршрутов (только статические значения)
$allowedRoutes = [
    'index' => 'index.php',
    'login' => 'login.php',
    'logout' => 'logout.php',
    'edit' => 'edit.php',
    'admin' => 'admin.php',
    'admin_edit' => 'admin_edit.php',
    'register' => 'index.php'
];

// Получение маршрута из параметра (только из GET)
$route = isset($_GET['route']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['route']) : 'index';

// Безопасное включение файла через белый список
if (isset($allowedRoutes[$route])) {
    $filePath = __DIR__ . '/' . $allowedRoutes[$route];
    
    // Дополнительная проверка: файл должен существовать и находиться в директории проекта
    if (file_exists($filePath) && strpos(realpath($filePath), realpath(__DIR__)) === 0) {
        require_once $filePath;
    } else {
        http_response_code(404);
        require_once __DIR__ . '/404.php';
    }
} else {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
}
?>
<?php
// create_admin.php - Создание администратора (запустить один раз)
require_once 'config.php';

// Защита от повторного запуска
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users");
$checkStmt->execute();
$adminCount = $checkStmt->fetchColumn();

if ($adminCount > 0) {
    die("
    <!DOCTYPE html>
    <html>
    <head><title>Защита</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; display: inline-block; }
    </style>
    </head>
    <body>
        <div class='warning'>
            <h2>⚠️ Безопасность</h2>
            <p>Администратор уже существует в системе.</p>
            <p>Этот скрипт нельзя запускать повторно по соображениям безопасности.</p>
            <p><a href='admin.php'>Перейти в панель администратора</a></p>
        </div>
    </body>
    </html>
    ");
}

$login = 'admin';
$password = 'admin123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO admin_users (login, password_hash) VALUES (?, ?)");
    $stmt->execute([$login, $password_hash]);
    
    echo "
    <!DOCTYPE html>
    <html>
    <head><title>Администратор создан</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .success { background: #d4edda; color: #155724; padding: 30px; border-radius: 15px; display: inline-block; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .success h1 { margin-top: 0; }
        .credentials { background: white; padding: 15px; border-radius: 8px; margin: 20px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
        .warning { color: #856404; font-size: 14px; margin-top: 20px; }
        a { color: #667eea; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
    </head>
    <body>
        <div class='success'>
            <h1>✅ Администратор создан!</h1>
            <div class='credentials'>
                <p><strong>Учетные данные:</strong></p>
                <p>Логин: <code>admin</code></p>
                <p>Пароль: <code>admin123</code></p>
            </div>
            <p><a href='admin.php'>Перейти в панель администратора</a></p>
            <p class='warning'>⚠️ Для безопасности рекомендуется удалить файл create_admin.php после использования</p>
        </div>
    </body>
    </html>
    ";
    
} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) {
        echo "
        <!DOCTYPE html>
        <html>
        <head><title>Администратор уже существует</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; }
            .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; display: inline-block; }
        </style>
        </head>
        <body>
            <div class='warning'>
                <h2>⚠️ Администратор уже существует!</h2>
                <p>Пользователь с логином 'admin' уже зарегистрирован.</p>
                <p><a href='admin.php'>Перейти в панель администратора</a></p>
            </div>
        </body>
        </html>
        ";
    } else {
        showError("Ошибка при создании администратора", $e->getMessage());
        echo "<p><a href='index.php'>Вернуться на главную</a></p>";
    }
}
?>
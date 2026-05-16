<?php
// login.php
require_once 'config.php';

// Установка защитных заголовков
setSecurityHeaders();

// Rate Limiter класс (встроен прямо в файл для простоты)
class RateLimiter {
    private $pdo;
    private $maxAttempts = 5;
    private $decayMinutes = 15;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createTableIfNotExists();
    }
    
    private function createTableIfNotExists() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    attempt_time DATETIME NOT NULL,
                    INDEX idx_ip_time (ip_address, attempt_time)
                )
            ");
        } catch (PDOException $e) {
            // Таблица уже существует или ошибка - игнорируем
        }
    }
    
    public function checkLimit($ip) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip, $this->decayMinutes]);
        $attempts = $stmt->fetchColumn();
        
        return $attempts < $this->maxAttempts;
    }
    
    public function recordAttempt($ip) {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())
        ");
        $stmt->execute([$ip]);
    }
    
    public function clearAttempts($ip) {
        $stmt = $this->pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
    }
}

$error = '';
$rateLimiter = new RateLimiter($pdo);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Проверка лимита попыток
if (!$rateLimiter->checkLimit($clientIp)) {
    $error = "Слишком много неудачных попыток входа. Попробуйте через 15 минут.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        $error = "Введите логин и пароль";
        $rateLimiter->recordAttempt($clientIp);
    } else {
        // Поиск пользователя в БД
        $stmt = $pdo->prepare("
            SELECT au.id, au.application_id, au.login, au.password_hash, 
                   a.full_name, a.phone, a.email, a.birth_date, a.gender, a.biography
            FROM application_users au
            JOIN application a ON au.application_id = a.id
            WHERE au.login = ?
        ");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Успешный вход - очищаем попытки
            $rateLimiter->clearAttempts($clientIp);
            
            // Регенерация ID сессии для защиты от фиксации сессии
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['application_id'] = $user['application_id'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_data'] = [
                'full_name' => $user['full_name'],
                'phone' => $user['phone'],
                'email' => $user['email'],
                'birth_date' => $user['birth_date'],
                'gender' => $user['gender'],
                'biography' => $user['biography']
            ];
            
            // Получаем языки пользователя
            $lang_stmt = $pdo->prepare("
                SELECT pl.name FROM application_languages al
                JOIN programming_languages pl ON al.language_id = pl.id
                WHERE al.application_id = ?
            ");
            $lang_stmt->execute([$user['application_id']]);
            $languages = $lang_stmt->fetchAll(PDO::FETCH_COLUMN);
            $_SESSION['user_data']['languages'] = $languages;
            
            header('Location: edit.php');
            exit;
        } else {
            $error = "Неверный логин или пароль";
            $rateLimiter->recordAttempt($clientIp);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Лабораторная работа 5</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-background {
            width: 600px;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .login-container h2 {
            color: #333;
            margin-top: 0;
            background: white;
            border-radius: 15px;
            padding: 20px;
        }
        .back-link {
            height: 40px;
            font-size: 24px;
            display: block;
            text-align: center;
            margin-top: 20px;
            color: black;
            background: white;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Вход для редактирования</h2>
        
        <?php if ($error): ?>
            <div class="error-message" style="margin-bottom: 20px;"><?php echo escapeHtml($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" class="form-background">
            <?php echo csrfField(); ?>
            
            <div class="form-group">
                <label for="login">Логин:</label>
                <input type="text" id="login" name="login" required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Войти</button>
        </form>
        
        <a href="index.php" class="back-link">← Вернуться к форме</a>
    </div>
</body>
</html>
<?php
// config.php
session_start(); // Добавлено для CSRF и сессий

define('DB_HOST', 'localhost');
define('DB_USER', 'u82092');
define('DB_PASS', '1557612');
define('DB_NAME', 'u82092');

// Режим отладки (выключить в production)
define('DEBUG_MODE', false);

// Настройка отображения ошибок (скрытие информации)
ini_set('display_errors', DEBUG_MODE ? 1 : 0);
ini_set('display_startup_errors', DEBUG_MODE ? 1 : 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Удаление информации о версии PHP
header_remove('X-Powered-By');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    if (DEBUG_MODE) {
        die("Ошибка подключения к БД: " . $e->getMessage());
    } else {
        error_log("Database connection error: " . $e->getMessage());
        die("Ошибка подключения к базе данных. Администратор уведомлен.");
    }
}

// ==================== ЗАЩИТА ОТ XSS ====================

// Функция для экранирования HTML-символов
function escapeHtml($string) {
    if ($string === null) return '';
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Функция для экранирования JavaScript
function escapeJs($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// Функция для очистки вывода в атрибутах
function escapeAttr($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ==================== ЗАЩИТА ОТ CSRF ====================

// Генерация CSRF-токена
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Проверка CSRF-токена
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Вывод поля CSRF в форме
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . escapeAttr(generateCsrfToken()) . '">';
}

// ==================== ЗАЩИТА ОТ SQL INJECTION ====================

// Безопасное получение целочисленного ID
function getSafeInt($value, $default = 0) {
    if (is_numeric($value) && $value > 0 && $value <= PHP_INT_MAX) {
        return (int)$value;
    }
    return $default;
}

// Валидация поля сортировки через белый список
function validateSortField($field, $allowedFields, $default = 'id') {
    if (in_array($field, $allowedFields, true)) {
        return $field;
    }
    return $default;
}

// Валидация направления сортировки
function validateSortOrder($order, $default = 'DESC') {
    $allowed = ['ASC', 'DESC'];
    return in_array(strtoupper($order), $allowed, true) ? strtoupper($order) : $default;
}

// Валидация имени языка программирования
function validateLanguageName($langName, $allowedLanguages) {
    return in_array($langName, $allowedLanguages, true) ? $langName : null;
}

// ==================== ЗАЩИТА ОТ INFORMATION DISCLOSURE ====================

// Безопасный вывод ошибок
function showError($message, $debugInfo = null) {
    if (DEBUG_MODE === true) {
        echo '<div class="error">' . escapeHtml($message);
        if ($debugInfo) {
            echo '<br><small>' . escapeHtml($debugInfo) . '</small>';
        }
        echo '</div>';
    } else {
        error_log("Error: " . $message . " | " . ($debugInfo ?? ''));
        echo '<div class="error">Произошла ошибка. Администратор уведомлен.</div>';
    }
}

// ==================== ЗАЩИТНЫЕ HTTP-ЗАГОЛОВКИ ====================

function setSecurityHeaders() {
    // Защита от MIME-типов
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        
        // Защита от clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // XSS-защита браузера
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Удаление информации о сервере
        header_remove('Server');
    }
}

// ==================== ЗАЩИТА ОТ INCLUDE ====================

// Безопасное включение файлов через белый список
function safeInclude($page, $allowedPages) {
    if (in_array($page, $allowedPages, true)) {
        $filePath = __DIR__ . '/pages/' . $page . '.php';
        if (file_exists($filePath) && strpos(realpath($filePath), __DIR__) === 0) {
            include $filePath;
            return true;
        }
    }
    return false;
}

// ==================== ОСНОВНЫЕ ФУНКЦИИ ПРОЕКТА ====================

// Функция для генерации логина
function generateLogin($full_name) {
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    if (strlen($clean_name) < 4) {
        $clean_name = 'user';
    }
    $login = substr($clean_name, 0, 4);
    $login .= rand(100, 999);
    return strtolower($login);
}

// Функция для генерации пароля
function generatePassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

// Функция для получения всех языков
function getAllLanguages($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
    return $stmt->fetchAll();
}

// Функция для получения языков пользователя
function getUserLanguages($pdo, $application_id) {
    $stmt = $pdo->prepare("
        SELECT pl.name FROM application_languages al
        JOIN programming_languages pl ON al.language_id = pl.id
        WHERE al.application_id = ?
    ");
    $stmt->execute([$application_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Функция для получения всех заявок (с безопасной сортировкой)
function getAllApplications($pdo, $sort = 'created_at', $order = 'DESC') {
    $allowedSort = ['id', 'full_name', 'created_at', 'email', 'birth_date'];
    $allowedOrder = ['ASC', 'DESC'];
    
    $sort = validateSortField($sort, $allowedSort, 'created_at');
    $order = validateSortOrder($order, 'DESC');
    
    $stmt = $pdo->query("
        SELECT a.* FROM application a 
        ORDER BY $sort $order
    ");
    return $stmt->fetchAll();
}

// Функция для статистики по языкам
function getLanguageStats($pdo) {
    $stmt = $pdo->query("
        SELECT pl.name, COUNT(al.language_id) as count
        FROM programming_languages pl
        LEFT JOIN application_languages al ON pl.id = al.language_id
        GROUP BY pl.id
        ORDER BY count DESC, pl.name
    ");
    return $stmt->fetchAll();
}

// Функция для проверки администратора через БД
function checkAdminAuth($pdo) {
    if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
        return false;
    }
    
    $login = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    
    $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE login = ?");
    $stmt->execute([$login]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        return true;
    }
    return false;
}

// Функция для HTTP-аутентификации администратора
function authenticateAdmin($pdo) {
    if (!checkAdminAuth($pdo)) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        echo '<!DOCTYPE html>
        <html>
        <head><title>401 Требуется авторизация</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; background: #f5f5f5; }
            .error { background: #fee; color: #c33; padding: 20px; border-radius: 8px; display: inline-block; }
        </style>
        </head>
        <body>
            <div class="error">
                <h1>401 Требуется авторизация</h1>
                <p>Доступ разрешен только администраторам</p>
            </div>
        </body>
        </html>';
        exit();
    }
}

// Белый список разрешенных языков программирования
function getAllowedLanguages() {
    return ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 
            'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
}
?>
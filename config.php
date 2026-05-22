<?php
// config.php
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'u82092');
define('DB_PASS', '1557612');
define('DB_NAME', 'u82092');
define('DEBUG_MODE', false);


// Настройка отображения ошибок
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

// ==================== ЗАЩИТНЫЕ ФУНКЦИИ ====================

function escapeHtml($string) {
    if ($string === null) return '';
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function escapeJs($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function escapeAttr($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . escapeAttr(generateCsrfToken()) . '">';
}

function getSafeInt($value, $default = 0) {
    if (is_numeric($value) && $value > 0 && $value <= PHP_INT_MAX) {
        return (int)$value;
    }
    return $default;
}

function setSecurityHeaders() {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header_remove('Server');
        header_remove('X-Powered-By');
    }
}

// ==================== ВАЛИДАЦИОННЫЕ ФУНКЦИИ ====================

function getAllowedLanguages() {
    return ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 
            'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
}

function validateFullName($full_name) {
    $full_name = trim($full_name);
    if (empty($full_name)) {
        return ['valid' => false, 'error' => 'ФИО обязательно для заполнения'];
    }
    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]{2,150}$/u', $full_name)) {
        return ['valid' => false, 'error' => 'ФИО должно содержать только буквы, пробелы и дефисы (от 2 до 150 символов)'];
    }
    return ['valid' => true, 'value' => $full_name];
}

function validatePhone($phone) {
    $phone = trim($phone);
    if (empty($phone)) {
        return ['valid' => false, 'error' => 'Телефон обязателен для заполнения'];
    }
    if (!preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) {
        return ['valid' => false, 'error' => 'Телефон должен содержать только цифры, +, - и пробелы (от 10 до 20 символов)'];
    }
    return ['valid' => true, 'value' => $phone];
}

function validateEmail($email) {
    $email = trim($email);
    if (empty($email)) {
        return ['valid' => false, 'error' => 'Email обязателен для заполнения'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Введите корректный email адрес'];
    }
    return ['valid' => true, 'value' => $email];
}

function validateBirthDate($birth_date) {
    if (empty($birth_date)) {
        return ['valid' => false, 'error' => 'Дата рождения обязательна для заполнения'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        return ['valid' => false, 'error' => 'Неверный формат даты. Используйте ГГГГ-ММ-ДД'];
    }
    return ['valid' => true, 'value' => $birth_date];
}

function validateGender($gender) {
    if (empty($gender)) {
        return ['valid' => false, 'error' => 'Выберите пол'];
    }
    if (!in_array($gender, ['male', 'female'])) {
        return ['valid' => false, 'error' => 'Недопустимое значение пола'];
    }
    return ['valid' => true, 'value' => $gender];
}

function validateLanguages($languages, $allowedLanguages) {
    if (empty($languages) || !is_array($languages)) {
        return ['valid' => false, 'error' => 'Выберите хотя бы один язык программирования'];
    }
    
    $validLanguages = [];
    foreach ($languages as $langName) {
        if (in_array($langName, $allowedLanguages, true)) {
            $validLanguages[] = $langName;
        }
    }
    
    if (empty($validLanguages)) {
        return ['valid' => false, 'error' => 'Выбраны недопустимые языки программирования'];
    }
    
    return ['valid' => true, 'value' => $validLanguages];
}

function validateBiography($biography) {
    $biography = trim($biography ?? '');
    if (strlen($biography) > 5000) {
        return ['valid' => false, 'error' => 'Биография не должна превышать 5000 символов'];
    }
    return ['valid' => true, 'value' => $biography];
}

function validateApplicationData($data) {
    $errors = [];
    $validData = [];
    $allowedLanguages = getAllowedLanguages();
    
    $result = validateFullName($data['full_name'] ?? '');
    if (!$result['valid']) $errors['full_name'] = $result['error'];
    else $validData['full_name'] = $result['value'];
    
    $result = validatePhone($data['phone'] ?? '');
    if (!$result['valid']) $errors['phone'] = $result['error'];
    else $validData['phone'] = $result['value'];
    
    $result = validateEmail($data['email'] ?? '');
    if (!$result['valid']) $errors['email'] = $result['error'];
    else $validData['email'] = $result['value'];
    
    $result = validateBirthDate($data['birth_date'] ?? '');
    if (!$result['valid']) $errors['birth_date'] = $result['error'];
    else $validData['birth_date'] = $result['value'];
    
    $result = validateGender($data['gender'] ?? '');
    if (!$result['valid']) $errors['gender'] = $result['error'];
    else $validData['gender'] = $result['value'];
    
    $result = validateLanguages($data['languages'] ?? [], $allowedLanguages);
    if (!$result['valid']) $errors['languages'] = $result['error'];
    else $validData['languages'] = $result['value'];
    
    $result = validateBiography($data['biography'] ?? '');
    if (!$result['valid']) $errors['biography'] = $result['error'];
    else $validData['biography'] = $result['value'];
    
    return ['errors' => $errors, 'valid_data' => $validData];
}

// ==================== БИЗНЕС-ФУНКЦИИ ====================

function generateLogin($full_name) {
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    if (strlen($clean_name) < 4) {
        $clean_name = 'user';
    }
    $login = substr($clean_name, 0, 4);
    $login .= rand(100, 999);
    return strtolower($login);
}

function generatePassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

function getAllLanguages($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
    return $stmt->fetchAll();
}

function getUserLanguages($pdo, $application_id) {
    $stmt = $pdo->prepare("
        SELECT pl.name FROM application_languages al
        JOIN programming_languages pl ON al.language_id = pl.id
        WHERE al.application_id = ?
    ");
    $stmt->execute([$application_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function createApplication($pdo, $validData) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO application (full_name, phone, email, birth_date, gender, biography, contract_accepted, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $validData['full_name'],
            $validData['phone'],
            $validData['email'],
            $validData['birth_date'],
            $validData['gender'],
            $validData['biography']
        ]);
        
        $application_id = $pdo->lastInsertId();
        
        $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($validData['languages'] as $lang_name) {
            $lang_id_stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
            $lang_id_stmt->execute([$lang_name]);
            $lang_id = $lang_id_stmt->fetchColumn();
            if ($lang_id) {
                $lang_stmt->execute([$application_id, $lang_id]);
            }
        }
        
        $login = generateLogin($validData['full_name']);
        $password = generatePassword();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $user_stmt = $pdo->prepare("INSERT INTO application_users (application_id, login, password_hash) VALUES (?, ?, ?)");
        $user_stmt->execute([$application_id, $login, $password_hash]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'application_id' => $application_id,
            'login' => $login,
            'password' => $password,
            'profile_url' => '/edit.php'
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Create application error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка при сохранении данных'];
    }
}

function updateApplication($pdo, $application_id, $validData) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE application 
            SET full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, biography = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $validData['full_name'],
            $validData['phone'],
            $validData['email'],
            $validData['birth_date'],
            $validData['gender'],
            $validData['biography'],
            $application_id
        ]);
        
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$application_id]);
        
        $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($validData['languages'] as $lang_name) {
            $lang_id_stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
            $lang_id_stmt->execute([$lang_name]);
            $lang_id = $lang_id_stmt->fetchColumn();
            if ($lang_id) {
                $lang_stmt->execute([$application_id, $lang_id]);
            }
        }
        
        $pdo->commit();
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update application error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка при обновлении данных'];
    }
}

function getApplication($pdo, $application_id) {
    $stmt = $pdo->prepare("SELECT * FROM application WHERE id = ?");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch();
    
    if (!$application) {
        return null;
    }
    
    $application['languages'] = getUserLanguages($pdo, $application_id);
    
    return $application;
}

function authenticateUser($pdo, $login, $password) {
    $stmt = $pdo->prepare("
        SELECT au.id, au.application_id, au.login, au.password_hash
        FROM application_users au
        WHERE au.login = ?
    ");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    
    return null;
}

function getAuthenticatedUser($pdo) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
        return null;
    }
    
    return authenticateUser($pdo, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
}

// API Response функции (только здесь, не в api.php)
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendJsonError($message, $statusCode = 400) {
    sendJsonResponse(['error' => $message], $statusCode);
}

setSecurityHeaders();
?>
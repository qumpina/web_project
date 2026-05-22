<?php
// api.php - REST API Production Ready
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// CORS и заголовки
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Получение метода и пути
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$pathParts = explode('/', $path);
$resourceId = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;

// Получение входных данных
$input = getInputData();

// Логирование запроса (для отладки, можно отключить в production)
if (DEBUG_MODE) {
    error_log("API: $method " . ($resourceId ? "/api/$resourceId" : "/api"));
}

// Маршрутизация
try {
    switch ($method) {
        case 'POST':
            handlePost($pdo, $input);
            break;
            
        case 'PUT':
            if (!$resourceId) {
                sendJsonError('ID анкеты не указан', 400);
            }
            handlePut($pdo, $resourceId, $input);
            break;
            
        case 'GET':
            if (!$resourceId) {
                sendJsonError('ID анкеты не указан. Используйте GET /api/{id}', 400);
            }
            handleGet($pdo, $resourceId);
            break;
            
        default:
            sendJsonError('Метод не поддерживается', 405);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendJsonError('Внутренняя ошибка сервера', 500);
}

/**
 * Получение входных данных (JSON, form-data, x-www-form-urlencoded)
 */
function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $inputData = file_get_contents('php://input');
    
    // JSON
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode($inputData, true);
        return $data ?: null;
    }
    
    // Form-data или x-www-form-urlencoded
    if (strpos($contentType, 'multipart/form-data') !== false || 
        strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        
        if (!empty($_POST)) {
            $data = $_POST;
            if (isset($data['languages']) && is_string($data['languages'])) {
                $data['languages'] = explode(',', $data['languages']);
            }
            return $data;
        }
    }
    
    // Парсим строку запроса
    parse_str($inputData, $data);
    if (!empty($data)) {
        if (isset($data['languages']) && is_string($data['languages'])) {
            $data['languages'] = explode(',', $data['languages']);
        }
        return $data;
    }
    
    return null;
}

/**
 * Обработка POST запроса - создание новой анкеты
 */
function handlePost($pdo, $data) {
    // Проверка наличия данных
    if (!$data) {
        sendJsonError('Данные не получены. Отправьте JSON или form-data', 400);
    }
    
    // Валидация
    $validation = validateApplicationData($data);
    
    if (!empty($validation['errors'])) {
        sendJsonResponse([
            'success' => false,
            'errors' => $validation['errors']
        ], 400);
    }
    
    // Создание анкеты
    $result = createApplication($pdo, $validation['valid_data']);
    
    if ($result['success']) {
        sendJsonResponse([
            'success' => true,
            'message' => 'Анкета успешно создана',
            'data' => [
                'application_id' => $result['application_id'],
                'login' => $result['login'],
                'password' => $result['password'],
                'profile_url' => $result['profile_url']
            ]
        ], 201);
    } else {
        sendJsonError($result['error'], 500);
    }
}

/**
 * Обработка PUT запроса - обновление анкеты (требуется авторизация)
 */
function handlePut($pdo, $id, $data) {
    // Проверка авторизации
    $user = getAuthenticatedUser($pdo);
    
    if (!$user) {
        sendJsonResponse([
            'error' => 'Требуется авторизация',
            'message' => 'Для редактирования анкеты необходимо авторизоваться'
        ], 401);
    }
    
    // Проверка прав
    if ($user['application_id'] != $id) {
        sendJsonError('Доступ запрещен', 403);
    }
    
    // Проверка данных
    if (!$data) {
        sendJsonError('Данные не получены', 400);
    }
    
    // Валидация
    $validation = validateApplicationData($data);
    
    if (!empty($validation['errors'])) {
        sendJsonResponse([
            'success' => false,
            'errors' => $validation['errors']
        ], 400);
    }
    
    // Обновление
    $result = updateApplication($pdo, $id, $validation['valid_data']);
    
    if ($result['success']) {
        sendJsonResponse([
            'success' => true,
            'message' => 'Анкета успешно обновлена'
        ], 200);
    } else {
        sendJsonError($result['error'], 500);
    }
}

/**
 * Обработка GET запроса - получение анкеты (требуется авторизация)
 */
function handleGet($pdo, $id) {
    // Проверка авторизации
    $user = getAuthenticatedUser($pdo);
    
    if (!$user) {
        sendJsonResponse([
            'error' => 'Требуется авторизация',
            'message' => 'Для просмотра анкеты необходимо авторизоваться'
        ], 401);
    }
    
    // Проверка прав
    if ($user['application_id'] != $id) {
        sendJsonError('Доступ запрещен', 403);
    }
    
    // Получение данных
    $application = getApplication($pdo, $id);
    
    if (!$application) {
        sendJsonError('Анкета не найдена', 404);
    }
    
    // Удаляем чувствительные данные
    unset($application['contract_accepted']);
    
    sendJsonResponse([
        'success' => true,
        'data' => $application
    ], 200);
}
?>
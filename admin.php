<?php
// admin.php - Панель администратора
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// Установка защитных заголовков
setSecurityHeaders();

// ========== HTTP BASIC AUTHENTICATION ==========
$valid_admin_login = 'admin';
$valid_admin_password = 'admin123';

$auth_user = $_SERVER['PHP_AUTH_USER'] ?? '';
$auth_pass = $_SERVER['PHP_AUTH_PW'] ?? '';

// Проверка авторизации
if (empty($auth_user) || empty($auth_pass) || $auth_user !== $valid_admin_login || $auth_pass !== $valid_admin_password) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel - Lab7"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>401 Требуется авторизация</title>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                margin: 0;
                padding: 20px;
            }
            .auth-box {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 400px;
            }
            .auth-box h1 {
                color: #333;
                margin-bottom: 20px;
            }
            .auth-box .credentials {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .auth-box code {
                background: #e9ecef;
                padding: 3px 8px;
                border-radius: 4px;
            }
            .retry-btn {
                display: inline-block;
                padding: 12px 24px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }
        </style>
    </head>
    <body>
        <div class="auth-box">
            <h1>🔐 Требуется авторизация</h1>
            <p>Для доступа к панели администратора введите логин и пароль.</p>
            <div class="credentials">
                <p><strong>Учетные данные:</strong></p>
                <p>Логин: <code>admin</code></p>
                <p>Пароль: <code>admin123</code></p>
            </div>
            <a href="admin.php" class="retry-btn">🔄 Попробовать снова</a>
        </div>
    </body>
    </html>';
    exit();
}

// Если дошли сюда - авторизация успешна
$message = '';
$error = '';

// Удаление записи
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if ($id > 0) {
        if (function_exists('verifyCsrfToken') && verifyCsrfToken($csrf_token)) {
            try {
                $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM application_users WHERE application_id = ?")->execute([$id]);
                $stmt = $pdo->prepare("DELETE FROM application WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Запись #$id успешно удалена";
            } catch (PDOException $e) {
                $error = "Ошибка при удалении: " . $e->getMessage();
            }
        } else {
            $error = "Ошибка безопасности: неверный CSRF-токен";
        }
    }
}

// Получение параметров сортировки
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Получение всех заявок
$applications = getAllApplications($pdo, $sort, $order);
$total_count = count($applications);
$language_stats = getLanguageStats($pdo);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора - Лабораторная 7</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 { color: #333; }
        .stats-container {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .total-card { background: #2c3e50; }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e1e1e1; }
        th { background: #f8f9fa; }
        tr:hover { background: #f5f5f5; }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #e9ecef;
            border-radius: 12px;
            font-size: 12px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👑 Панель администратора</h1>
            <div>Вы вошли как: <strong><?php echo htmlspecialchars($auth_user); ?></strong>
            <a href="index.html" style="margin-left: 15px; color: #667eea;">Выйти</a></div>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="stats-container">
            <h2>📊 Статистика по языкам программирования</h2>
            <div class="stats-grid">
                <?php foreach ($language_stats as $stat): ?>
                    <div class="stat-card">
                        <div class="lang-name"><?php echo htmlspecialchars($stat['name']); ?></div>
                        <div class="lang-count"><?php echo htmlspecialchars($stat['count']); ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="stat-card total-card">
                    <div>📋 Всего заявок: <?php echo htmlspecialchars($total_count); ?></div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><a href="?sort=id&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">ID</a></th>
                        <th><a href="?sort=full_name&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">ФИО</a></th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Языки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="8" style="text-align: center;">Нет данных</td></tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <?php $user_langs = getUserLanguages($pdo, $app['id']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['id']); ?></td>
                                <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['phone']); ?></td>
                                <td><?php echo htmlspecialchars($app['email']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($app['birth_date'])); ?></td>
                                <td><?php echo $app['gender'] === 'male' ? 'Мужской' : 'Женский'; ?></td>
                                <td>
                                    <?php foreach ($user_langs as $lang): ?>
                                        <span class="badge"><?php echo htmlspecialchars($lang); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <a href="admin_edit.php?id=<?php echo $app['id']; ?>" class="btn btn-edit">✏️</a>
                                    <a href="?delete=<?php echo $app['id']; ?>&csrf_token=<?php echo urlencode(generateCsrfToken()); ?>" class="btn btn-delete" onclick="return confirm('Удалить?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <a href="index.html" class="back-link">← Вернуться на главную</a>
    </div>
</body>
</html>
<?php
// Функции для admin.php
function getAllApplications($pdo, $sort, $order) {
    $allowedSort = ['id', 'full_name', 'created_at', 'email', 'birth_date'];
    $sort = in_array($sort, $allowedSort) ? $sort : 'created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    $stmt = $pdo->query("SELECT * FROM application ORDER BY $sort $order");
    return $stmt->fetchAll();
}

function getLanguageStats($pdo) {
    $stmt = $pdo->query("
        SELECT pl.name, COUNT(al.language_id) as count
        FROM programming_languages pl
        LEFT JOIN application_languages al ON pl.id = al.language_id
        GROUP BY pl.id
        ORDER BY count DESC
    ");
    return $stmt->fetchAll();
}
?>
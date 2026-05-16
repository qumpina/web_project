<?php
// admin.php - Панель администратора
require_once 'config.php';

// Установка защитных заголовков
setSecurityHeaders();

// ========== HTTP BASIC AUTHENTICATION (прямая проверка) ==========
// Временно используем статическую проверку, пока не настроена БД
$valid_admin_login = 'admin';
$valid_admin_password = 'admin123';

// Получаем данные авторизации
$auth_user = $_SERVER['PHP_AUTH_USER'] ?? '';
$auth_pass = $_SERVER['PHP_AUTH_PW'] ?? '';

// Проверка авторизации
if (empty($auth_user) || empty($auth_pass) || $auth_user !== $valid_admin_login || $auth_pass !== $valid_admin_password) {
    // Отправляем заголовки для HTTP авторизации
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel - Lab7"');
    
    // Показываем форму авторизации (если браузер не показывает свою)
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
            .auth-box p {
                color: #666;
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
                font-family: monospace;
            }
            .retry-btn {
                display: inline-block;
                padding: 12px 24px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                margin-top: 10px;
            }
            .retry-btn:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="auth-box">
            <h1>🔐 Требуется авторизация</h1>
            <p>Для доступа к панели администратора необходимо ввести логин и пароль.</p>
            <div class="credentials">
                <p><strong>Учетные данные:</strong></p>
                <p>Логин: <code>admin</code></p>
                <p>Пароль: <code>admin123</code></p>
            </div>
            <a href="admin.php" class="retry-btn">🔄 Попробовать снова</a>
            <p style="margin-top: 20px; font-size: 12px; color: #999;">
                Если окно входа не появилось, проверьте настройки браузера.
            </p>
        </div>
    </body>
    </html>';
    exit();
}

// Если дошли сюда - авторизация успешна
// Обработка действий
$message = '';
$error = '';

// Удаление записи
if (isset($_GET['delete'])) {
    $id = getSafeInt($_GET['delete']);
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if ($id > 0) {
        if (verifyCsrfToken($csrf_token)) {
            try {
                // Сначала удаляем связанные записи
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
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
            gap: 15px;
        }
        
        .header h1 {
            color: #333;
            font-size: 1.8em;
        }
        
        .header .admin-info {
            color: #666;
        }
        
        .header .admin-info strong {
            color: #667eea;
        }
        
        .stats-container {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .stats-container h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
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
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-card .lang-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .stat-card .lang-count {
            font-size: 28px;
            font-weight: bold;
        }
        
        .total-card {
            background: #2c3e50;
        }
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow-x: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        th a {
            color: #333;
            text-decoration: none;
        }
        
        th a:hover {
            color: #667eea;
        }
        
        tr:hover {
            background: #f5f5f5;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #333;
        }
        
        .btn-edit:hover {
            background: #e0a800;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .back-link:hover {
            background: #5a6268;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #e9ecef;
            border-radius: 12px;
            font-size: 12px;
            margin: 2px;
        }
        
        .empty-row td {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            th, td {
                padding: 8px 10px;
                font-size: 12px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👑 Панель администратора</h1>
            <div class="admin-info">
                Вы вошли как: <strong><?php echo escapeHtml($_SERVER['PHP_AUTH_USER']); ?></strong>
                <a href="index.php" style="margin-left: 15px; color: #667eea;">Выйти</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo escapeHtml($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo escapeHtml($error); ?></div>
        <?php endif; ?>
        
        <!-- Статистика -->
        <div class="stats-container">
            <h2>📊 Статистика по языкам программирования</h2>
            <div class="stats-grid">
                <?php foreach ($language_stats as $stat): ?>
                    <div class="stat-card">
                        <div class="lang-name"><?php echo escapeHtml($stat['name']); ?></div>
                        <div class="lang-count"><?php echo escapeHtml($stat['count']); ?></div>
                        <div style="font-size: 12px; opacity: 0.8;">пользователей</div>
                    </div>
                <?php endforeach; ?>
                <div class="stat-card total-card">
                    <div class="lang-name">📋 Всего</div>
                    <div class="lang-count"><?php echo escapeHtml($total_count); ?></div>
                    <div style="font-size: 12px; opacity: 0.8;">заявок</div>
                </div>
            </div>
        </div>
        
        <!-- Таблица с данными -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><a href="?sort=id&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">ID <?php echo $sort === 'id' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?></a></th>
                        <th><a href="?sort=full_name&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">ФИО <?php echo $sort === 'full_name' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?></a></th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Языки программирования</th>
                        <th><a href="?sort=created_at&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Дата создания <?php echo $sort === 'created_at' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?></a></th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr class="empty-row">
                            <td colspan="9">Нет данных для отображения</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <?php $user_langs = getUserLanguages($pdo, $app['id']); ?>
                            <tr>
                                <td><?php echo escapeHtml($app['id']); ?></td>
                                <td><?php echo escapeHtml($app['full_name']); ?></td>
                                <td><?php echo escapeHtml($app['phone']); ?></td>
                                <td><?php echo escapeHtml($app['email']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($app['birth_date'])); ?></td>
                                <td>
                                    <?php 
                                    $genders = ['male' => 'Мужской', 'female' => 'Женский'];
                                    echo escapeHtml($genders[$app['gender']] ?? $app['gender']);
                                    ?>
                                </td>
                                <td>
                                    <?php foreach ($user_langs as $lang): ?>
                                        <span class="badge"><?php echo escapeHtml($lang); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($app['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="admin_edit.php?id=<?php echo escapeAttr($app['id']); ?>" class="btn btn-edit">✏️ Редактировать</a>
                                    <a href="?delete=<?php echo escapeAttr($app['id']); ?>&csrf_token=<?php echo urlencode(generateCsrfToken()); ?>" class="btn btn-delete" onclick="return confirm('Удалить запись #<?php echo escapeJs($app['id']); ?>?')">🗑️ Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <a href="index.php" class="back-link">← Вернуться на главную</a>
    </div>
</body>
</html>
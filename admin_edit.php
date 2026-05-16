<?php
// admin_edit.php - Редактирование записи администратором
require_once 'config.php';

// Установка защитных заголовков
setSecurityHeaders();

// ========== HTTP BASIC AUTHENTICATION ==========
$valid_admin_login = 'admin';
$valid_admin_password = 'admin123';

$auth_user = $_SERVER['PHP_AUTH_USER'] ?? '';
$auth_pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if (empty($auth_user) || empty($auth_pass) || $auth_user !== $valid_admin_login || $auth_pass !== $valid_admin_password) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel - Lab7"');
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
            <p>Используйте логин: <strong>admin</strong> и пароль: <strong>admin123</strong></p>
        </div>
    </body>
    </html>';
    exit();
}

$id = isset($_GET['id']) ? getSafeInt($_GET['id']) : 0;
if (!$id) {
    header('Location: admin.php');
    exit;
}

// Получение данных заявки
$stmt = $pdo->prepare("SELECT * FROM application WHERE id = ?");
$stmt->execute([$id]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: admin.php');
    exit;
}

// Получение языков пользователя
$user_languages = getUserLanguages($pdo, $id);
$all_languages = getAllLanguages($pdo);

$error = '';
$success = '';

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Ошибка безопасности: неверный токен. Попробуйте обновить страницу.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $birth_date = $_POST['birth_date'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $biography = trim($_POST['biography'] ?? '');
        $languages = $_POST['languages'] ?? [];
        
        // Валидация
        $errors = [];
        $allowedLanguages = getAllowedLanguages();
        
        if (empty($full_name)) {
            $errors[] = "ФИО обязательно";
        } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]{2,150}$/u', $full_name)) {
            $errors[] = "ФИО должно содержать только буквы, пробелы и дефисы";
        }
        
        if (empty($phone)) {
            $errors[] = "Телефон обязателен";
        } elseif (!preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) {
            $errors[] = "Неверный формат телефона";
        }
        
        if (empty($email)) {
            $errors[] = "Email обязателен";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Неверный формат email";
        }
        
        if (empty($birth_date)) {
            $errors[] = "Дата рождения обязательна";
        }
        
        if (!in_array($gender, ['male', 'female'])) {
            $errors[] = "Выберите пол";
        }
        
        if (empty($languages)) {
            $errors[] = "Выберите хотя бы один язык";
        } else {
            $validLanguages = [];
            foreach ($languages as $langName) {
                if (in_array($langName, $allowedLanguages, true)) {
                    $validLanguages[] = $langName;
                }
            }
            if (empty($validLanguages)) {
                $errors[] = "Выбраны недопустимые языки программирования";
            }
            $languages = $validLanguages;
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE application 
                    SET full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, biography = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $id]);
                
                $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
                
                $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                foreach ($languages as $lang_name) {
                    $lang_id_stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
                    $lang_id_stmt->execute([$lang_name]);
                    $lang_id = $lang_id_stmt->fetchColumn();
                    if ($lang_id) {
                        $lang_stmt->execute([$id, $lang_id]);
                    }
                }
                
                $pdo->commit();
                $success = "Данные успешно обновлены!";
                
                $application['full_name'] = $full_name;
                $application['phone'] = $phone;
                $application['email'] = $email;
                $application['birth_date'] = $birth_date;
                $application['gender'] = $gender;
                $application['biography'] = $biography;
                $user_languages = $languages;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Ошибка при сохранении: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование записи #<?php echo escapeHtml($id); ?> - Админка</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .admin-header h1 { color: #333; margin: 0; }
        .admin-info { color: #666; font-size: 14px; }
        .admin-info strong { color: #667eea; }
        .subtitle { color: #666; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .required::after { content: " *"; color: #e74c3c; }
        input[type="text"], input[type="tel"], input[type="email"], input[type="date"], select, textarea {
            width: 100%; padding: 12px; border: 2px solid #e1e1e1; border-radius: 8px;
            font-size: 16px; transition: border-color 0.3s; box-sizing: border-box;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #667eea; }
        select[multiple] { height: 150px; }
        textarea { resize: vertical; min-height: 100px; }
        .radio-group { display: flex; gap: 20px; flex-wrap: wrap; align-items: center; }
        .radio-group div { display: flex; align-items: center; gap: 5px; }
        .radio-group input[type="radio"] { width: auto; }
        .field-hint { color: #888; font-size: 12px; margin-top: 5px; }
        .buttons { display: flex; gap: 15px; margin-top: 25px; }
        button {
            flex: 1; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 8px; font-size: 16px;
            font-weight: 600; cursor: pointer; transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); }
        .cancel-btn {
            background: #6c757d; text-align: center; text-decoration: none;
            display: flex; align-items: center; justify-content: center;
        }
        .cancel-btn:hover { background: #5a6268; transform: translateY(-2px); }
        .message { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        @media (max-width: 600px) {
            .container { padding: 20px; }
            .buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>✏️ Редактирование записи #<?php echo escapeHtml($id); ?></h1>
            <div class="admin-info">
                Вы вошли как: <strong><?php echo escapeHtml($_SERVER['PHP_AUTH_USER']); ?></strong>
            </div>
        </div>
        <div class="subtitle">Редактирование данных пользователя</div>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo escapeHtml($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <?php echo csrfField(); ?>
            
            <div class="form-group">
                <label for="full_name" class="required">ФИО:</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?php echo escapeAttr($application['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone" class="required">Телефон:</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo escapeAttr($application['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email" class="required">Email:</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo escapeAttr($application['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="birth_date" class="required">Дата рождения:</label>
                <input type="date" id="birth_date" name="birth_date" 
                       value="<?php echo escapeAttr($application['birth_date']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="required">Пол:</label>
                <div class="radio-group">
                    <div>
                        <input type="radio" name="gender" value="male" 
                               <?php echo $application['gender'] === 'male' ? 'checked' : ''; ?>>
                        <label>Мужской</label>
                    </div>
                    <div>
                        <input type="radio" name="gender" value="female" 
                               <?php echo $application['gender'] === 'female' ? 'checked' : ''; ?>>
                        <label>Женский</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="languages" class="required">Любимые языки программирования:</label>
                <select id="languages" name="languages[]" multiple size="6" required>
                    <?php foreach ($all_languages as $lang): ?>
                        <option value="<?php echo escapeAttr($lang['name']); ?>" 
                            <?php echo in_array($lang['name'], $user_languages) ? 'selected' : ''; ?>>
                            <?php echo escapeHtml($lang['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="field-hint">Удерживайте Ctrl для выбора нескольких языков</div>
            </div>
            
            <div class="form-group">
                <label for="biography">Биография:</label>
                <textarea id="biography" name="biography" rows="5"><?php echo escapeHtml($application['biography'] ?? ''); ?></textarea>
            </div>
            
            <div class="buttons">
                <button type="submit">💾 Сохранить изменения</button>
                <a href="admin.php" class="cancel-btn" style="text-decoration: none; color: white;">❌ Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>
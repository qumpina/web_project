<?php
// index.php
require_once 'config.php';

// Установка защитных заголовков
setSecurityHeaders();

// Функция для установки Cookie на год
function setUserDataCookie($data) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        setcookie("user_$key", $value, time() + 365*24*60*60, '/', '', false, true);
    }
}

// Функция для получения данных из Cookie
function getUserDataFromCookie() {
    $data = [];
    $fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'biography', 'languages'];
    foreach ($fields as $field) {
        if (isset($_COOKIE["user_$field"])) {
            if ($field === 'languages') {
                $data[$field] = explode(',', $_COOKIE["user_$field"]);
            } else {
                $data[$field] = $_COOKIE["user_$field"];
            }
        }
    }
    return $data;
}

// Функция валидации с регулярными выражениями
function validateData($data) {
    $errors = [];
    $valid_data = [];
    $allowedLanguages = getAllowedLanguages();
    
    // 1. ФИО
    $full_name = trim($data['full_name'] ?? '');
    if (empty($full_name)) {
        $errors['full_name'] = "Поле ФИО обязательно для заполнения";
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]{2,150}$/u', $full_name)) {
        $errors['full_name'] = "ФИО должно содержать только буквы, пробелы и дефисы (от 2 до 150 символов)";
    } else {
        $valid_data['full_name'] = $full_name;
    }
    
    // 2. Телефон
    $phone = trim($data['phone'] ?? '');
    if (empty($phone)) {
        $errors['phone'] = "Поле Телефон обязательно для заполнения";
    } elseif (!preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) {
        $errors['phone'] = "Телефон должен содержать только цифры, +, - и пробелы (от 10 до 20 символов)";
    } else {
        $valid_data['phone'] = $phone;
    }
    
    // 3. Email
    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = "Поле Email обязательно для заполнения";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors['email'] = "Введите корректный email адрес";
    } else {
        $valid_data['email'] = $email;
    }
    
    // 4. Дата рождения
    $birth_date = $data['birth_date'] ?? '';
    if (empty($birth_date)) {
        $errors['birth_date'] = "Поле Дата рождения обязательно для заполнения";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors['birth_date'] = "Неверный формат даты. Используйте ГГГГ-ММ-ДД";
    } else {
        $valid_data['birth_date'] = $birth_date;
    }
    
    // 5. Пол
    $gender = $data['gender'] ?? '';
    $valid_genders = ['male', 'female'];
    if (empty($gender)) {
        $errors['gender'] = "Выберите пол";
    } elseif (!in_array($gender, $valid_genders)) {
        $errors['gender'] = "Выбрано недопустимое значение пола";
    } else {
        $valid_data['gender'] = $gender;
    }
    
    // 6. Языки программирования (с белым списком)
    $languages = $data['languages'] ?? [];
    if (empty($languages)) {
        $errors['languages'] = "Выберите хотя бы один язык программирования";
    } else {
        $validLanguages = [];
        foreach ($languages as $langName) {
            $validLang = validateLanguageName($langName, $allowedLanguages);
            if ($validLang) {
                $validLanguages[] = $validLang;
            }
        }
        if (empty($validLanguages)) {
            $errors['languages'] = "Выбраны недопустимые языки программирования";
        } else {
            $valid_data['languages'] = $validLanguages;
        }
    }
    
    // 7. Биография
    $biography = trim($data['biography'] ?? '');
    if (strlen($biography) > 5000) {
        $errors['biography'] = "Биография не должна превышать 5000 символов";
    } else {
        $valid_data['biography'] = $biography;
    }
    
    // 8. Чекбокс контракта
    if (!isset($data['contract'])) {
        $errors['contract'] = "Необходимо ознакомиться с контрактом";
    }
    
    return ['errors' => $errors, 'valid_data' => $valid_data];
}

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = "Ошибка безопасности. Пожалуйста, обновите страницу.";
    } else {
        $result = validateData($_POST);
        $errors = $result['errors'];
        $valid_data = $result['valid_data'];
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Вставка в БД
                $stmt = $pdo->prepare("
                    INSERT INTO application (full_name, phone, email, birth_date, gender, biography, contract_accepted)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $valid_data['full_name'],
                    $valid_data['phone'],
                    $valid_data['email'],
                    $valid_data['birth_date'],
                    $valid_data['gender'],
                    $valid_data['biography'] ?? '',
                    1
                ]);
                
                $application_id = $pdo->lastInsertId();
                
                // Вставка языков
                if (!empty($valid_data['languages'])) {
                    $lang_stmt = $pdo->prepare("
                        INSERT INTO application_languages (application_id, language_id)
                        VALUES (?, ?)
                    ");
                    
                    foreach ($valid_data['languages'] as $lang_name) {
                        $lang_id_stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
                        $lang_id_stmt->execute([$lang_name]);
                        $lang_id = $lang_id_stmt->fetchColumn();
                        
                        if ($lang_id) {
                            $lang_stmt->execute([$application_id, $lang_id]);
                        }
                    }
                }
                
                // Генерация логина и пароля
                $login = generateLogin($valid_data['full_name']);
                $password = generatePassword();
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Сохранение в таблицу пользователей
                $user_stmt = $pdo->prepare("
                    INSERT INTO application_users (application_id, login, password_hash)
                    VALUES (?, ?, ?)
                ");
                $user_stmt->execute([$application_id, $login, $password_hash]);
                
                $pdo->commit();
                
                // Сохраняем данные в Cookies на год
                setUserDataCookie($valid_data);
                
                $_SESSION['success'] = "Данные успешно сохранены!";
                $_SESSION['generated_login'] = $login;
                $_SESSION['generated_password'] = $password;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                showError("Ошибка при сохранении", $e->getMessage());
                $errors['database'] = "Ошибка при сохранении данных";
            }
        }
    }
    
    // Если есть ошибки, сохраняем их в Cookies
    if (!empty($errors)) {
        setcookie('form_errors', json_encode($errors), 0, '/', '', false, true);
        foreach ($_POST as $key => $value) {
            if ($key !== 'contract' && $key !== 'csrf_token') {
                if (is_array($value)) {
                    setcookie("temp_$key", implode(',', $value), 0, '/', '', false, true);
                } else {
                    setcookie("temp_$key", $value, 0, '/', '', false, true);
                }
            }
        }
        header('Location: index.php');
        exit;
    }
}

// Получение данных из Cookies
$form_errors = [];
if (isset($_COOKIE['form_errors'])) {
    $form_errors = json_decode($_COOKIE['form_errors'], true) ?? [];
    setcookie('form_errors', '', time() - 3600, '/');
}

$temp_data = [];
if (!empty($form_errors)) {
    foreach ($_COOKIE as $key => $value) {
        if (strpos($key, 'temp_') === 0) {
            $field = substr($key, 5);
            if ($field === 'languages') {
                $temp_data[$field] = explode(',', $value);
            } else {
                $temp_data[$field] = $value;
            }
            setcookie($key, '', time() - 3600, '/');
        }
    }
}

$user_data = getUserDataFromCookie();
$form_data = !empty($temp_data) ? $temp_data : $user_data;

$success_message = $_SESSION['success'] ?? '';
$generated_login = $_SESSION['generated_login'] ?? '';
$generated_password = $_SESSION['generated_password'] ?? '';
unset($_SESSION['success'], $_SESSION['generated_login'], $_SESSION['generated_password']);

$languages_list = getAllowedLanguages();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета - Лабораторная работа 5</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h2>Анкета</h2>
        <?php if (isset($_SESSION['user_login'])): ?>
            <div class="user-info">
                Вы вошли как: <strong><?php echo escapeHtml($_SESSION['user_login']); ?></strong>
                <a href="edit.php" class="btn-link">Редактировать данные</a>
                <a href="logout.php" class="btn-link">Выйти</a>
            </div>
        <?php else: ?>
            <div class="user-info">
                <a href="login.php" class="btn-link">Войти для редактирования</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($success_message): ?>
        <div class="message success">
            <?php echo escapeHtml($success_message); ?>
            <?php if ($generated_login): ?>
                <div class="credentials">
                    <p><strong>Ваши данные для входа:</strong></p>
                    <p>Логин: <code><?php echo escapeHtml($generated_login); ?></code></p>
                    <p>Пароль: <code><?php echo escapeHtml($generated_password); ?></code></p>
                    <p class="warning">Сохраните эти данные! Они понадобятся для редактирования анкеты.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <form id="user-form" method="POST" action="index.php">
        <?php echo csrfField(); ?>
        
        <!-- ФИО -->
        <div class="form-group <?php echo isset($form_errors['full_name']) ? 'has-error' : ''; ?>">
            <label for="fio" class="required">ФИО:</label>
            <input type="text" 
                   placeholder="Иванов Иван Иванович" 
                   id="fio" 
                   name="full_name" 
                   value="<?php echo escapeAttr($form_data['full_name'] ?? ''); ?>"
                   required 
                   maxlength="150">
            <div class="field-hint">Только русские и английские буквы, пробелы и дефисы. От 2 до 150 символов.</div>
            <?php if (isset($form_errors['full_name'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['full_name']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Телефон -->
        <div class="form-group <?php echo isset($form_errors['phone']) ? 'has-error' : ''; ?>">
            <label for="phone" class="required">Телефон:</label>
            <input type="tel" 
                   placeholder="+7 (938) 500-57-74" 
                   id="phone" 
                   name="phone" 
                   value="<?php echo escapeAttr($form_data['phone'] ?? ''); ?>"
                   required>
            <div class="field-hint">Только цифры, символы +, - и пробелы. От 10 до 20 символов.</div>
            <?php if (isset($form_errors['phone'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['phone']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group <?php echo isset($form_errors['email']) ? 'has-error' : ''; ?>">
            <label for="email" class="required">E-mail:</label>
            <input type="email" 
                   placeholder="test@gmail.com" 
                   id="email" 
                   name="email" 
                   value="<?php echo escapeAttr($form_data['email'] ?? ''); ?>"
                   required>
            <div class="field-hint">Формат: имя@домен.ру</div>
            <?php if (isset($form_errors['email'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['email']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Дата рождения -->
        <div class="form-group <?php echo isset($form_errors['birth_date']) ? 'has-error' : ''; ?>">
            <label for="birthdate" class="required">Дата рождения:</label>
            <input type="date" 
                   id="birthdate" 
                   name="birth_date" 
                   value="<?php echo escapeAttr($form_data['birth_date'] ?? ''); ?>"
                   required>
            <div class="field-hint">Формат: ГГГГ-ММ-ДД</div>
            <?php if (isset($form_errors['birth_date'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['birth_date']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Пол -->
        <div class="form-group <?php echo isset($form_errors['gender']) ? 'has-error' : ''; ?>">
            <label class="required">Пол:</label>
            <div class="radio-group">
                <div>
                    <input type="radio" 
                           id="male" 
                           name="gender" 
                           value="male" 
                           <?php echo (isset($form_data['gender']) && $form_data['gender'] === 'male') ? 'checked' : ''; ?>
                           required>
                    <label for="male">Мужской</label>
                </div>
                <div>
                    <input type="radio" 
                           id="female" 
                           name="gender" 
                           value="female"
                           <?php echo (isset($form_data['gender']) && $form_data['gender'] === 'female') ? 'checked' : ''; ?>>
                    <label for="female">Женский</label>
                </div>
            </div>
            <div class="field-hint">Выберите один из вариантов</div>
            <?php if (isset($form_errors['gender'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['gender']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Языки программирования -->
        <div class="form-group <?php echo isset($form_errors['languages']) ? 'has-error' : ''; ?>">
            <label for="language" class="required">Любимые языки программирования:</label>
            <select id="language" name="languages[]" multiple size="6" required>
                <?php
                $selected_langs = $form_data['languages'] ?? [];
                foreach ($languages_list as $lang):
                ?>
                    <option value="<?php echo escapeAttr($lang); ?>" 
                        <?php echo in_array($lang, $selected_langs) ? 'selected' : ''; ?>>
                        <?php echo escapeHtml($lang); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="field-hint">Выберите один или несколько языков. Удерживайте Ctrl (Cmd на Mac) для множественного выбора.</div>
            <?php if (isset($form_errors['languages'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['languages']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Биография -->
        <div class="form-group <?php echo isset($form_errors['biography']) ? 'has-error' : ''; ?>">
            <label for="bio">Биография:</label>
            <textarea id="bio" name="biography" rows="5" placeholder="Расскажите немного о себе..."><?php echo escapeHtml($form_data['biography'] ?? ''); ?></textarea>
            <div class="field-hint">Необязательное поле. Максимум 5000 символов.</div>
            <?php if (isset($form_errors['biography'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['biography']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Контракт -->
        <div class="form-group <?php echo isset($form_errors['contract']) ? 'has-error' : ''; ?>">
            <div class="checkbox-group">
                <input type="checkbox" id="agreement" name="contract" required>
                <label for="agreement" class="required">С контрактом ознакомлен(а)</label>
            </div>
            <div class="field-hint">Необходимо подтвердить ознакомление с условиями контракта</div>
            <?php if (isset($form_errors['contract'])): ?>
                <div class="error-message"><?php echo escapeHtml($form_errors['contract']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Кнопка отправки -->
        <div class="form-group">
            <button type="submit">Сохранить</button>
        </div>
    </form>
</body>
</html>
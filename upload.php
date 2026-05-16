<?php
// upload.php - Класс для безопасной загрузки файлов
require_once 'config.php';

class SecureUploader {
    private $allowedTypes;
    private $maxFileSize;
    private $uploadPath;
    private $allowedExtensions;
    
    /**
     * Конструктор
     * @param array $allowedTypes - разрешенные MIME-типы
     * @param int $maxFileSize - максимальный размер файла в байтах
     */
    public function __construct($allowedTypes = [], $maxFileSize = 5242880) {
        $this->allowedTypes = $allowedTypes ?: [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt'
        ];
        $this->allowedExtensions = array_values($this->allowedTypes);
        $this->maxFileSize = $maxFileSize;
        $this->uploadPath = __DIR__ . '/uploads/';
        
        // Создание директории для загрузок
        if (!file_exists($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Валидация загруженного файла
     * @param array $file - элемент из $_FILES
     * @return array - результат валидации
     */
    public function validateFile($file) {
        // 1. Проверка наличия ошибок загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => "Файл превышает максимальный размер (upload_max_filesize)",
                UPLOAD_ERR_FORM_SIZE => "Файл превышает максимальный размер (MAX_FILE_SIZE)",
                UPLOAD_ERR_PARTIAL => "Файл был загружен не полностью",
                UPLOAD_ERR_NO_FILE => "Файл не был загружен",
                UPLOAD_ERR_NO_TMP_DIR => "Отсутствует временная папка",
                UPLOAD_ERR_CANT_WRITE => "Не удалось записать файл на диск",
                UPLOAD_ERR_EXTENSION => "Загрузка файла остановлена расширением PHP"
            ];
            $errorMsg = $errors[$file['error']] ?? "Неизвестная ошибка загрузки";
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // 2. Проверка размера
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'error' => "Файл слишком большой. Максимум: " . ($this->maxFileSize / 1024 / 1024) . " MB"];
        }
        
        // 3. Проверка MIME-типа через finfo (надежный способ)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!isset($this->allowedTypes[$mimeType])) {
            return ['success' => false, 'error' => "Тип файла запрещен. Разрешены: " . implode(', ', array_keys($this->allowedTypes))];
        }
        
        $expectedExtension = $this->allowedTypes[$mimeType];
        
        // 4. Проверка расширения файла
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if ($extension !== $expectedExtension) {
            return ['success' => false, 'error' => "Несоответствие типа и расширения файла"];
        }
        
        // 5. Проверка на двойное расширение (обходные маневры)
        if (preg_match('/\.[^.]+\./', $originalName)) {
            return ['success' => false, 'error' => "Некорректное имя файла (обнаружено двойное расширение)"];
        }
        
        // 6. Проверка на потенциально опасные символы в имени
        if (preg_match('/[^a-zA-Z0-9._-]/', $originalName)) {
            return ['success' => false, 'error' => "Имя файла содержит недопустимые символы"];
        }
        
        return ['success' => true];
    }
    
    /**
     * Сохранение файла с безопасным именем
     * @param array $file - элемент из $_FILES
     * @return array - результат сохранения
     */
    public function saveFile($file) {
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Генерация безопасного имени
        $extension = $this->allowedTypes[finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name'])];
        $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = $this->uploadPath . $safeName;
        
        // Перемещение файла
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Установка правильных прав
            chmod($targetPath, 0644);
            
            return [
                'success' => true,
                'filename' => $safeName,
                'original_name' => $file['name'],
                'path' => '/uploads/' . $safeName,
                'size' => $file['size'],
                'mime_type' => $file['type']
            ];
        }
        
        return ['success' => false, 'error' => "Ошибка при сохранении файла на сервере"];
    }
    
    /**
     * Удаление файла
     * @param string $filename - имя файла
     * @return bool - результат удаления
     */
    public function deleteFile($filename) {
        // Защита от path traversal
        $filename = basename($filename);
        $filePath = $this->uploadPath . $filename;
        
        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
    
    /**
     * Получение информации о файле
     * @param string $filename - имя файла
     * @return array|null - информация о файле
     */
    public function getFileInfo($filename) {
        $filename = basename($filename);
        $filePath = $this->uploadPath . $filename;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        return [
            'name' => $filename,
            'path' => '/uploads/' . $filename,
            'size' => filesize($filePath),
            'mime_type' => mime_content_type($filePath),
            'modified' => filemtime($filePath)
        ];
    }
    
    /**
     * Получение списка всех загруженных файлов
     * @return array - список файлов
     */
    public function getAllFiles() {
        $files = [];
        $iterator = new FilesystemIterator($this->uploadPath);
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => '/uploads/' . $file->getFilename(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime()
                ];
            }
        }
        
        return $files;
    }
    
    /**
     * Проверка, является ли файл изображением
     * @param string $filename - имя файла
     * @return bool
     */
    public function isImage($filename) {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $imageTypes);
    }
}

// Пример использования (раскомментировать при необходимости):
/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    session_start();
    require_once 'config.php';
    
    // Проверка CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Ошибка CSRF");
    }
    
    $uploader = new SecureUploader();
    $result = $uploader->saveFile($_FILES['file']);
    
    if ($result['success']) {
        echo "Файл успешно загружен: " . $result['path'];
    } else {
        echo "Ошибка: " . $result['error'];
    }
    exit;
}
*/
?>
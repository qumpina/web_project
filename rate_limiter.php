<?php
// rate_limiter.php - Класс для ограничения количества попыток (защита от брутфорса)

class RateLimiter {
    private $pdo;
    private $maxAttempts;
    private $decayMinutes;
    private $tableName;
    
    /**
     * Конструктор
     * @param PDO $pdo - соединение с БД
     * @param int $maxAttempts - максимальное количество попыток
     * @param int $decayMinutes - время блокировки в минутах
     */
    public function __construct($pdo, $maxAttempts = 5, $decayMinutes = 15) {
        $this->pdo = $pdo;
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->tableName = 'login_attempts';
        $this->createTableIfNotExists();
    }
    
    /**
     * Создание таблицы для хранения попыток (если не существует)
     */
    private function createTableIfNotExists() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `attempt_time` DATETIME NOT NULL,
                    `user_agent` VARCHAR(255) DEFAULT NULL,
                    INDEX idx_ip_time (`ip_address`, `attempt_time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            // Таблица уже существует или ошибка - логируем
            error_log("RateLimiter: " . $e->getMessage());
        }
    }
    
    /**
     * Проверка, не превышен ли лимит попыток
     * @param string $ip - IP-адрес
     * @return bool - true если можно выполнить попытку
     */
    public function checkLimit($ip) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM {$this->tableName} 
            WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip, $this->decayMinutes]);
        $attempts = $stmt->fetchColumn();
        
        return $attempts < $this->maxAttempts;
    }
    
    /**
     * Запись неудачной попытки
     * @param string $ip - IP-адрес
     * @param string|null $userAgent - User-Agent (опционально)
     */
    public function recordAttempt($ip, $userAgent = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (ip_address, attempt_time, user_agent) 
            VALUES (?, NOW(), ?)
        ");
        $stmt->execute([$ip, $userAgent]);
    }
    
    /**
     * Очистка попыток для IP (при успешном входе)
     * @param string $ip - IP-адрес
     */
    public function clearAttempts($ip) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE ip_address = ?");
        $stmt->execute([$ip]);
    }
    
    /**
     * Очистка старых попыток (можно вызывать по крону)
     * @param int $hours - срок хранения в часах
     */
    public function cleanOldAttempts($hours = 24) {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName} 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$hours]);
        return $stmt->rowCount();
    }
    
    /**
     * Получение количества оставшихся попыток
     * @param string $ip - IP-адрес
     * @return int - количество оставшихся попыток
     */
    public function getRemainingAttempts($ip) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM {$this->tableName} 
            WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip, $this->decayMinutes]);
        $attempts = $stmt->fetchColumn();
        
        $remaining = $this->maxAttempts - $attempts;
        return $remaining > 0 ? $remaining : 0;
    }
    
    /**
     * Получение времени до сброса блокировки
     * @param string $ip - IP-адрес
     * @return int|null - секунды до сброса или null если нет блокировки
     */
    public function getBlockExpirationTime($ip) {
        if ($this->checkLimit($ip)) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT attempt_time FROM {$this->tableName} 
            WHERE ip_address = ? 
            ORDER BY attempt_time DESC 
            LIMIT 1
        ");
        $stmt->execute([$ip]);
        $lastAttempt = $stmt->fetchColumn();
        
        if ($lastAttempt) {
            $expiration = strtotime($lastAttempt) + ($this->decayMinutes * 60);
            $remaining = $expiration - time();
            return $remaining > 0 ? $remaining : 0;
        }
        
        return null;
    }
}
?>
<?php
// security_headers.php - Установка защитных HTTP-заголовков
// Этот файл подключается в начале каждого скрипта

function setSecurityHeaders() {
    // Защита от MIME-типов (не позволяет браузеру определять MIME-тип)
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        
        // Защита от clickjacking (запрет встраивания в iframe)
        header('X-Frame-Options: SAMEORIGIN');
        
        // XSS-защита браузера
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Security Policy (базовая)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Strict Transport Security (для HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Удаление информации о сервере
        header_remove('Server');
        header_remove('X-Powered-By');
        
        // Permission Policy (ограничение возможностей браузера)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    }
}

// Функция для проверки реферера (дополнительная CSRF-защита)
function checkReferer($allowedHosts = []) {
    if (empty($_SERVER['HTTP_REFERER'])) {
        return false;
    }
    
    $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    
    // Добавляем текущий хост в разрешенные
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    $allowedHosts[] = $currentHost;
    
    return in_array($refererHost, $allowedHosts);
}

// Функция для генерации nonce (для CSP)
function generateNonce() {
    if (empty($_SESSION['csp_nonce'])) {
        $_SESSION['csp_nonce'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csp_nonce'];
}

// Вызов установки заголовков
setSecurityHeaders();
?>
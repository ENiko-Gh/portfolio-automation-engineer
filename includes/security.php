<?php
/**
 * includes/security.php
 * Incluir al inicio de CADA archivo api/*.php
 * Proporciona: validación, rate limiting, sesión segura, logging
 *
 * Uso: require_once __DIR__ . '/../includes/security.php';
 */

// ── SESIÓN SEGURA ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),  // true en producción
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── OCULTAR INFO DEL SERVIDOR ──────────────────────────────────
header_remove('X-Powered-By');
header_remove('Server');

// ── RATE LIMITING POR IP ───────────────────────────────────────
// Límite: 60 requests por minuto por IP
// Usa archivos temporales (no necesita Redis)
function checkRateLimit(string $identifier = '', int $limit = 60, int $window = 60): bool {
    $ip      = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0];
    $ip      = filter_var(trim($ip), FILTER_VALIDATE_IP) ?: '0.0.0.0';
    $key     = $identifier ?: $ip;
    $file    = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
    $now     = time();
    $blocked = false;

    $data = ['requests' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $data = json_decode($raw, true) ?? $data;
    }

    // Verificar si está bloqueado
    if ($data['blocked_until'] > $now) {
        http_response_code(429);
        header('Retry-After: ' . ($data['blocked_until'] - $now));
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please wait.',
            'retry_after' => $data['blocked_until'] - $now,
        ]);
        exit;
    }

    // Limpiar requests fuera de la ventana
    $data['requests'] = array_filter($data['requests'], fn($t) => $t > ($now - $window));

    // Contar requests en ventana actual
    if (count($data['requests']) >= $limit) {
        $data['blocked_until'] = $now + 300; // bloquear 5 minutos
        @file_put_contents($file, json_encode($data), LOCK_EX);
        error_log("Rate limit exceeded: {$ip}");
        http_response_code(429);
        echo json_encode(['success'=>false,'message'=>'Rate limit exceeded. Try again in 5 minutes.']);
        exit;
    }

    $data['requests'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

// ── VALIDACIÓN DE INPUT ────────────────────────────────────────
function sanitizeString(?string $val, int $maxLen = 500): string {
    if ($val === null) return '';
    $val = strip_tags(trim($val));
    $val = htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_substr($val, 0, $maxLen);
}

function sanitizeEmail(?string $email): string {
    $clean = filter_var(trim($email ?? ''), FILTER_SANITIZE_EMAIL);
    return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : '';
}

function sanitizeInt($val, int $min = 0, int $max = PHP_INT_MAX): ?int {
    $int = filter_var($val, FILTER_VALIDATE_INT);
    if ($int === false) return null;
    return max($min, min($max, (int)$int));
}

function sanitizePhone(?string $phone): string {
    if (!$phone) return '';
    // Permitir +, dígitos, espacios, guiones, paréntesis
    $clean = preg_replace('/[^\d\+\s\-\(\)]/', '', $phone);
    return mb_substr(trim($clean), 0, 20);
}

// ── DETECCIÓN DE ATAQUES BÁSICOS ──────────────────────────────
function detectSQLInjection(string $input): bool {
    $patterns = [
        '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bDROP\b|\bDELETE\b|\bUPDATE\b)/i',
        '/(--)|(\/\*)/',
        '/(\bOR\b|\bAND\b)\s+[\d\'"]/',
        '/;\s*\b(SELECT|DROP|DELETE|INSERT|UPDATE)\b/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) return true;
    }
    return false;
}

function detectXSS(string $input): bool {
    $patterns = [
        '/<script[^>]*>/i',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<iframe/i',
        '/<object/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) return true;
    }
    return false;
}

// Verificar todo el request de una vez
function validateRequest(array $data): void {
    $raw = json_encode($data);
    if (detectSQLInjection($raw)) {
        error_log("SQL injection attempt from " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Invalid request.']);
        exit;
    }
    if (detectXSS($raw)) {
        error_log("XSS attempt from " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Invalid request.']);
        exit;
    }
}

// ── VERIFICAR ORIGEN (CORS manual) ───────────────────────────
function validateOrigin(array $allowedDomains = []): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

    // En localhost siempre permitir
    if (str_contains($origin, 'localhost') || str_contains($origin, '127.0.0.1')) {
        return;
    }

    if (empty($allowedDomains)) return; // Sin restricción configurada

    $allowed = false;
    foreach ($allowedDomains as $domain) {
        if (str_contains($origin, $domain)) { $allowed = true; break; }
    }

    if (!$allowed && !empty($origin)) {
        error_log("CORS block: {$origin}");
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Forbidden.']);
        exit;
    }
}

// ── TOKEN CSRF para formularios ────────────────────────────────
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return !empty($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// ── LOG DE SEGURIDAD ───────────────────────────────────────────
function securityLog(string $event, array $context = []): void {
    $ip   = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0];
    $line = date('Y-m-d H:i:s') . " [{$event}] IP:{$ip} " . json_encode($context);
    $logFile = __DIR__ . '/../logs/security.log';
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

<?php
declare(strict_types=1);

/**
 * Bootstrap compartido para todos los endpoints SOA.
 * Cada entry point require_once este archivo.
 */

// --- Dotenv (.env) ---
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'JWT_SECRET']);

// --- Config ---
config([
    'database' => [
        'host'     => $_ENV['DB_HOST'] ?? 'localhost',
        'name'     => $_ENV['DB_NAME'] ?? 'vuno_db_shipping',
        'username' => $_ENV['DB_USER'] ?? '',
        'password' => $_ENV['DB_PASS'] ?? '',
        'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],
    'jwt' => [
        'secret'     => $_ENV['JWT_SECRET'] ?? '',
        'expires_in' => (int) ($_ENV['JWT_EXPIRES_IN'] ?? 86400),
        'issuer'     => $_ENV['JWT_ISSUER'] ?? 'shipping.vunotek.com',
    ],
    'app' => [
        'allowed_origins' => array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '')),
        'debug'           => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        'timezone'        => $_ENV['APP_TIMEZONE'] ?? 'America/Managua',
    ],
]);

// --- Timezone ---
date_default_timezone_set(config()['app']['timezone']);

// --- Global Exception Handler ---
set_exception_handler(function (\Throwable $e): void {
    apiLog('ERROR', $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    $debug = config()['app']['debug'] ?? false;
    $message = $debug ? $e->getMessage() : 'Error interno del servidor';
    jsonError($message, 500);
});

// --- Composer Autoloader ---
require_once __DIR__ . '/vendor/autoload.php';

// --- PSR-4 Autoloader ---
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// --- CORS ---
setCorsHeaders();

// --- Security Headers ---
setSecurityHeaders();

// --- Helpers ---
function config(?array $merge = null): array
{
    static $config = null;

    if ($merge !== null) {
        $config = $merge;
        return $config;
    }

    return $config ?? [];
}

function setCorsHeaders(): void
{
    $config = config();
    $allowedOrigins = $config['app']['allowed_origins'] ?? [];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function setSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-XSS-Protection: 0');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $code = 400, array $errors = []): never
{
    $response = ['success' => false, 'message' => $message];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    $debug = config()['app']['debug'] ?? false;
    if ($debug && $code >= 500) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $response['debug'] = [
            'message' => $message,
            'trace'   => array_map(fn($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'), $trace),
        ];
    }

    jsonResponse($response, $code);
}

function jsonSuccess(mixed $data = null, string $message = '', int $code = 200): never
{
    $response = ['success' => true];
    if ($message !== '') {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response, $code);
}

// --- Middleware Helpers ---

function requireMethod(string ...$methods): void
{
    $current = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($current, $methods, true)) {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

function requireAuthMiddleware(): array
{
    return requireAuth();
}

// --- Input Validation ---

function validateEmail(string $email): string
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Email inválido', 422, ['email' => 'Formato de email inválido']);
    }
    if (mb_strlen($email) > 255) {
        jsonError('Email inválido', 422, ['email' => 'El email no puede exceder 255 caracteres']);
    }
    return $email;
}

function validatePassword(string $password, bool $isCreation = true): string
{
    if (mb_strlen($password) > 128) {
        jsonError('Contraseña inválida', 422, ['password' => 'La contraseña no puede exceder 128 caracteres']);
    }

    if ($isCreation && mb_strlen($password) < 8) {
        jsonError('Contraseña inválida', 422, ['password' => 'La contraseña debe tener al menos 8 caracteres']);
    }

    return $password;
}

function sanitizeString(string $value, int $maxLen = 255): string
{
    $value = trim($value);
    if (mb_strlen($value) > $maxLen) {
        jsonError('Dato inválido', 422, ['field' => "El valor no puede exceder {$maxLen} caracteres"]);
    }
    return $value;
}

function requireAuth(): array
{
    $token = $_COOKIE['admin_token'] ?? '';

    if ($token === '') {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
        }
    }

    if ($token === '') {
        jsonError('Token de autenticación requerido', 401);
    }

    $payload = verifyJwt($token);

    if ($payload === null) {
        jsonError('Token inválido o expirado', 401);
    }

    return $payload;
}

function hasPermission(array $payload, string $module, string $action): bool
{
    $perms = $payload['permissions'] ?? [];
    if (!isset($perms[$module]) || !is_array($perms[$module])) {
        return false;
    }
    return in_array($action, $perms[$module], true);
}

function createJwt(array $payload): string
{
    $config = config();
    $secret = $config['jwt']['secret'];
    $expiresIn = $config['jwt']['expires_in'] ?? 86400;
    $issuer = $config['jwt']['issuer'] ?? 'shipping.vunotek.com';

    $now = time();

    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

    $claims = array_merge($payload, [
        'iss' => $issuer,
        'iat' => $now,
        'exp' => $now + $expiresIn,
    ]);

    $body = base64UrlEncode(json_encode($claims));
    $signature = base64UrlEncode(hash_hmac('sha256', "$header.$body", $secret, true));

    return "$header.$body.$signature";
}

function verifyJwt(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $body, $signature] = $parts;
    $config = config();
    $secret = $config['jwt']['secret'];

    $expectedSig = base64UrlEncode(hash_hmac('sha256', "$header.$body", $secret, true));

    if (!hash_equals($expectedSig, $signature)) {
        return null;
    }

    $claims = json_decode(base64_decode(strtr($body, '-_', '+/')), true);

    if ($claims === null || !isset($claims['exp']) || $claims['exp'] < time()) {
        return null;
    }

    return $claims;
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonError('Datos JSON inválidos');
    }

    return $data;
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

// --- Rate Limiting (file-based) ---

function getClientIp(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rateLimit(string $key, int $maxAttempts, int $windowSeconds): void
{
    $ip = getClientIp();
    $dir = sys_get_temp_dir() . '/shipping-ratelimit';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', "{$key}_{$ip}") . '.json';

    $now = time();
    $attempts = [];

    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $attempts = json_decode($raw, true) ?: [];
    }

    $attempts = array_filter($attempts, fn(int $ts) => $ts > $now - $windowSeconds);
    $attempts = array_values($attempts);

    if (count($attempts) >= $maxAttempts) {
        $retryAfter = $attempts[0] + $windowSeconds - $now;
        header('Retry-After: ' . $retryAfter);
        apiLog('WARNING', 'Rate limit exceeded', [
            'key' => $key,
            'ip' => $ip,
            'attempts' => count($attempts),
            'max' => $maxAttempts,
        ]);
        jsonError('Demasiadas solicitudes. Intentá de nuevo en ' . ceil($retryAfter / 60) . ' minutos.', 429);
    }

    $attempts[] = $now;
    file_put_contents($file, json_encode($attempts), LOCK_EX);
}

// --- Logging ---

function apiLog(string $level, string $message, array $context = []): void
{
    $dir = sys_get_temp_dir() . '/shipping-api-log';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $date = date('Y-m-d');
    $file = $dir . "/api-{$date}.log";
    $time = date('Y-m-d H:i:s');
    $ip = getClientIp();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $uri = $_SERVER['REQUEST_URI'] ?? '-';

    $line = "[{$time}] [{$level}] [{$ip}] {$method} {$uri} — {$message}";
    if (!empty($context)) {
        $line .= ' ' . json_encode($context);
    }
    $line .= "\n";

    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

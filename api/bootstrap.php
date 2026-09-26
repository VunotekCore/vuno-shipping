<?php
declare(strict_types=1);

/**
 * Bootstrap compartido para todos los endpoints SOA.
 * Cada entry point require_once este archivo.
 */

// --- Composer Autoloader (must be first) ---
require_once __DIR__ . '/vendor/autoload.php';

// --- Exception Handler (before Dotenv so bootstrap errors are caught) ---
set_exception_handler(function (\Throwable $e): void {
    $message = $e->getMessage();
    $trace = $e->getTraceAsString();
    error_log("[shipping-api] ERROR: {$message}\n{$trace}");
    jsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
});

// --- Dotenv (.env) ---
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
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
        'base_url'        => $_ENV['APP_URL'] ?? 'http://localhost:4321',
    ],
    'imagekit' => [
        'private_key'  => $_ENV['IMAGEKIT_PRIVATE_KEY'] ?? '',
        'url_endpoint' => $_ENV['IMAGEKIT_URL_ENDPOINT'] ?? '',
    ],
    'mail' => [
        'host'       => $_ENV['MAIL_HOST'] ?? '',
        'port'       => $_ENV['MAIL_PORT'] ?? '587',
        'username'   => $_ENV['MAIL_USER'] ?? '',
        'password'   => $_ENV['MAIL_PASS'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from'       => $_ENV['MAIL_FROM'] ?? '',
        'from_name'  => $_ENV['MAIL_FROM_NAME'] ?? 'Vuno Web Shipping',
    ],
    'push' => [
        'vapid_public_key'  => $_ENV['VAPID_PUBLIC_KEY'] ?? '',
        'vapid_private_key' => $_ENV['VAPID_PRIVATE_KEY'] ?? '',
        'vapid_subject'     => $_ENV['VAPID_SUBJECT'] ?? 'mailto:notificaciones@vunotek.com',
    ],
    'trusted_proxies' => array_map('trim', explode(',', $_ENV['TRUSTED_PROXIES'] ?? '')),
]);

// --- Timezone ---
date_default_timezone_set(config()['app']['timezone']);

// --- CORS ---
setCorsHeaders();

// --- Security Headers ---
setSecurityHeaders();

// --- Helpers ---
function config(?array $replace = null): array
{
    static $config = null;

    if ($replace !== null) {
        $config = $replace;
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
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
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
    header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'");

    if (isSecureRequest()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function isSecureRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($forwardedProto === 'https') {
        return true;
    }
    return false;
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

function requireAuthMiddleware(bool $validateCsrf = false): array
{
    $payload = requireAuth();
    if ($validateCsrf) {
        validateCsrfToken();
    }
    return $payload;
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

function validatePassword(string $password): string
{
    if (mb_strlen($password) > 128) {
        jsonError('Contraseña inválida', 422, ['password' => 'La contraseña no puede exceder 128 caracteres']);
    }

    if (mb_strlen($password) < 8) {
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

function isValidNicaraguaCoord(mixed $latitude, mixed $longitude): bool
{
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return false;
    }
    $lat = (float) $latitude;
    $lng = (float) $longitude;
    return $lat >= 10.6 && $lat <= 15.1 && $lng >= -88.0 && $lng <= -82.5;
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

    if (isTokenBlacklisted($payload['jti'] ?? '')) {
        jsonError('Token revocado', 401);
    }

    return $payload;
}

function isTokenBlacklisted(string $jti): bool
{
    if ($jti === '') {
        return false;
    }

    try {
        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare('SELECT 1 FROM token_blacklist WHERE jti = :jti AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['jti' => $jti]);
        return $stmt->fetch() !== false;
    } catch (\Throwable) {
        apiLog('CRITICAL', 'Token blacklist DB failure — failing closed');
        return true;
    }
}

function blacklistToken(string $jti, int $expiresAt): void
{
    if ($jti === '') {
        return;
    }

    try {
        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare(
            'INSERT IGNORE INTO token_blacklist (jti, expires_at) VALUES (:jti, :expires_at)'
        );
        $stmt->execute([
            'jti' => $jti,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ]);
    } catch (\Throwable $e) {
        apiLog('ERROR', 'Failed to blacklist token: ' . $e->getMessage());
    }
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
    $jti = bin2hex(random_bytes(16));

    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

    $claims = array_merge($payload, [
        'iss' => $issuer,
        'iat' => $now,
        'exp' => $now + $expiresIn,
        'jti' => $jti,
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
    $issuer = $config['jwt']['issuer'] ?? 'shipping.vunotek.com';

    $expectedSig = base64UrlEncode(hash_hmac('sha256', "$header.$body", $secret, true));

    if (!hash_equals($expectedSig, $signature)) {
        return null;
    }

    $claims = json_decode(base64_decode(strtr($body, '-_', '+/')), true);

    if ($claims === null || !isset($claims['exp']) || $claims['exp'] < time()) {
        return null;
    }

    if (!isset($claims['iss']) || $claims['iss'] !== $issuer) {
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

    if (mb_strlen($raw) > 1048576) {
        jsonError('Payload demasiado grande', 413);
    }

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
    $trustedProxies = config()['trusted_proxies'] ?? [];

    if (empty($trustedProxies)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteAddr, $trustedProxies, true)) {
        $ip = $remoteAddr;
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function rateLimit(string $key, int $maxAttempts, int $windowSeconds): void
{
    $ip = getClientIp();
    $dir = sys_get_temp_dir() . '/shipping-ratelimit';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', "{$key}_{$ip}") . '.json';

    $fp = fopen($file, 'c+');
    if ($fp === false) {
        return;
    }

    chmod($file, 0600);
    flock($fp, LOCK_EX);

    $now = time();
    $attempts = [];

    $raw = stream_get_contents($fp);
    if ($raw !== false && $raw !== '') {
        $attempts = json_decode($raw, true) ?: [];
    }

    $attempts = array_filter($attempts, fn(int $ts) => $ts > $now - $windowSeconds);
    $attempts = array_values($attempts);

    if (count($attempts) >= $maxAttempts) {
        flock($fp, LOCK_UN);
        fclose($fp);
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

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($attempts));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
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

// --- CSRF (Doble Submit Cookie) ---

function generateCsrfToken(): string
{
    $token = bin2hex(random_bytes(32));
    $expiresIn = config()['jwt']['expires_in'] ?? 86400;
    setcookie('csrf_token', $token, [
        'expires'  => time() + $expiresIn,
        'path'     => '/',
        'secure'   => isSecureRequest(),
        'httponly'  => false,
        'samesite' => 'Strict',
    ]);
    return $token;
}

function validateCsrfToken(): void
{
    $cookieToken = $_COOKIE['csrf_token'] ?? '';
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if ($cookieToken === '' || $headerToken === '' || !hash_equals($cookieToken, $headerToken)) {
        jsonError('Token CSRF inválido', 403);
    }
}

// --- Refresh Tokens ---

function createRefreshToken(int $userId): string
{
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 604800); // 7 days

    try {
        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    } catch (\Throwable $e) {
        apiLog('ERROR', 'Failed to create refresh token: ' . $e->getMessage());
        jsonError('Error al crear refresh token', 500);
    }

    return $rawToken;
}

function verifyRefreshToken(string $rawToken): ?int
{
    $tokenHash = hash('sha256', $rawToken);

    try {
        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, user_id FROM refresh_tokens WHERE token_hash = :hash AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $row = $stmt->fetch();

        if ($row === false || $row === null) {
            return null;
        }

        // Delete used token (one-time use)
        $db->prepare('DELETE FROM refresh_tokens WHERE id = :id')->execute(['id' => $row['id']]);

        return (int) $row['user_id'];
    } catch (\Throwable $e) {
        apiLog('ERROR', 'Failed to verify refresh token: ' . $e->getMessage());
        return null;
    }
}

function setRefreshCookie(string $rawToken, int $ttl = 604800): void
{
    setcookie('refresh_token', $rawToken, [
        'expires'  => time() + $ttl,
        'path'     => '/api/admin/',
        'secure'   => isSecureRequest(),
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
}

function clearRefreshCookie(): void
{
    setcookie('refresh_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/api/admin/',
        'secure'   => isSecureRequest(),
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
}

function revokeRefreshToken(string $rawToken): void
{
    if ($rawToken === '') {
        return;
    }

    try {
        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE token_hash = :hash');
        $stmt->execute(['hash' => hash('sha256', $rawToken)]);
    } catch (\Throwable $e) {
        apiLog('ERROR', 'Failed to revoke refresh token: ' . $e->getMessage());
    }
}

function cleanupExpiredTokens(): void
{
    try {
        $db = \App\Config\Database::getConnection();
        $db->exec("DELETE FROM token_blacklist WHERE expires_at < NOW()");
        $db->exec("DELETE FROM refresh_tokens WHERE expires_at < NOW()");
    } catch (\Throwable) {
        // Silent cleanup
    }
}

if (random_int(1, 100) === 1) {
    cleanupExpiredTokens();
}

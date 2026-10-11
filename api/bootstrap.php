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

    if ($e instanceof \App\Exceptions\ApiException) {
        jsonResponse(['success' => false, 'message' => $message], $e->getStatusCode());
    }

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
        // MAIL_ENABLED=false desactiva el envío real (útil en dev/pruebas).
        'enabled'    => filter_var($_ENV['MAIL_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOL),
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

/**
 * Validacion de identificacion fiscal nicaraguense (cedula de persona natural
 * o RUC de persona juridica). Devuelve el valor canonico: 14 caracteres, sin
 * guiones y en mayusculas, que es lo que se persiste.
 *
 * La validacion es ESTRUCTURAL a proposito: el codigo de municipio, el
 * consecutivo de inscripcion y el algoritmo de la letra (modulo 23) no son
 * publicos, asi que no se pueden comprobar. Solo se verifica la forma, en que
 * extremo va la letra y que la fecha de nacimiento exista de verdad.
 *
 * Espejo en TypeScript de `classifyNicaraguanId()`
 * (backoffice/src/utils/validation.ts). Si cambia una regla, cambia en los dos
 * lados: el frontend es evadible y aca esta la autoridad.
 *
 * @param string $value Campo opcional: '' o null se traduce a null.
 * @param string $field Nombre del campo, para el detalle del error.
 */
function validateRuc(?string $value, string $field = 'ruc'): ?string
{
    // Guiones y espacios son solo presentacion: el valor canonico no los tiene.
    // NO se trunca a 14: si el valor excede la longitud hay que reportarlo,
    // porque recortar en silencio convertiria el numero de otra persona en un
    // documento valido pero distinto al que se escribio.
    $core = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    $core = substr($core, 0, 20);

    if ($core === '') {
        return null;
    }

    $fail = static function (string $message) use ($field): never {
        jsonError('Identificación inválida', 422, [$field => $message]);
    };

    $length = strlen($core);

    // El primer caracter decide la naturaleza: en la cedula la letra va al
    // final, en el RUC de persona juridica va al principio.
    if (preg_match('/^[A-Z]/', $core) === 1) {
        if (preg_match('/^[A-Z]\d{13}$/', $core) !== 1) {
            $fail('El RUC de persona jurídica debe ser una letra seguida de 13 dígitos (ej. J1234567890123)');
        }
        return $core;
    }

    if ($length !== 14) {
        $fail($length > 14
            ? "La cédula tiene {$length} caracteres y el máximo son 14"
            : "La cédula tiene {$length} de 14 caracteres: 13 dígitos y 1 letra al final");
    }
    if (preg_match('/^\d{13}[A-Z]$/', $core) !== 1) {
        $fail('La cédula debe ser 13 dígitos seguidos de una letra al final (ej. 001-291289-0042T)');
    }

    $letter = $core[13];
    if (in_array($letter, ['I', 'O', 'Z'], true)) {
        $fail("La letra final \"{$letter}\" no es válida: el algoritmo módulo 23 excluye I, O y Z");
    }

    $dd = (int) substr($core, 3, 2);
    $mm = (int) substr($core, 5, 2);
    $yy = (int) substr($core, 7, 2);

    if ($mm < 1 || $mm > 12) {
        $fail('El mes ' . substr($core, 5, 2) . ' no existe: la fecha va en formato DDMMAA');
    }
    if ($dd < 1 || $dd > 31) {
        $fail('El día ' . substr($core, 3, 2) . ' no existe: la fecha va en formato DDMMAA');
    }

    // Año de 2 digitos: se asume 20xx mientras sea <= al año en curso y 19xx en
    // el resto (pivote habitual). Un año de 2 digitos siempre cae en los
    // ultimos 100 años.
    $now = new DateTimeImmutable('now');
    $year = $yy <= ((int) $now->format('y')) ? 2000 + $yy : 1900 + $yy;

    // checkdate() roda al mes siguiente en vez de fallar, asi que se compara el
    // resultado para detectar fechas como 31/02.
    if (!checkdate($mm, $dd, $year)) {
        $fail(sprintf(
            'La fecha %s/%s/%s no existe',
            substr($core, 3, 2),
            substr($core, 5, 2),
            substr($core, 7, 2)
        ));
    }

    $age = (int) $now->format('Y') - $year;
    if ($now->format('m-d') < sprintf('%02d-%02d', $mm, $dd)) {
        $age--;
    }
    if ($age < 0) {
        $fail('La fecha de nacimiento es futura');
    }
    // No hay un tope explicito de edad: el pivote de 2 digitos ya la acota.
    // YY=27..99 -> 1927..1999 (0..99 anos) y YY=00..26 -> 2000..2026 (0..26
    // anos), asi que ninguna fecha valida puede implicar 100 anos o mas. Una
    // persona nacida en 1926 o antes simplemente no es representable con 2
    // digitos de ano.

    return $core;
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

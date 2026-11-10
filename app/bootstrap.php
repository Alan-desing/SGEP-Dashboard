<?php
declare(strict_types=1);

date_default_timezone_set((require __DIR__ . '/../config/app.php')['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/'
    ]);
    session_start();
}

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $settings = require __DIR__ . '/../config/database.php';
    $dsn = 'mysql:host=' . $settings['host'] . ';port=' . $settings['port'] . ';dbname=' . $settings['database'] . ';charset=' . $settings['charset'];
    $connection = new PDO($dsn, $settings['username'], $settings['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $connection->exec('SET time_zone = ' . $connection->quote((new DateTimeImmutable('now'))->format('P')));
    return $connection;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['error' => 'El formato de la solicitud no es válido.'], 400);
    }
    return $data;
}

function current_user(): ?array
{
    static $resolved = false;
    static $user = null;
    if ($resolved) {
        return $user;
    }
    $resolved = true;
    if (!isset($_SESSION['user']['id'])) {
        return null;
    }
    $query = db()->prepare('SELECT id,nombre,email,rol,activo FROM usuario WHERE id=? AND activo=1');
    $query->execute([(int) $_SESSION['user']['id']]);
    $current = $query->fetch();
    if (!$current) {
        unset($_SESSION['user']);
        return null;
    }
    $_SESSION['user'] = $current;
    $user = $current;
    return $user;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['error' => 'Iniciá sesión para continuar.'], 401);
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_user();
    if (!in_array($user['rol'], $roles, true)) {
        json_response(['error' => 'No tenés permiso para realizar esta acción.'], 403);
    }
    return $user;
}

function require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if ($expected === '' || !hash_equals($expected, $provided)) {
        json_response(['error' => 'La sesión venció. Actualizá la página e intentá de nuevo.'], 419);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function project_visible(int $projectId, array $user): bool
{
    if (in_array($user['rol'], ['admin', 'auditor'], true)) {
        return true;
    }
    $query = db()->prepare('SELECT 1 FROM proyecto_responsable WHERE proyecto_id = ? AND user_id = ?');
    $query->execute([$projectId, $user['id']]);
    return (bool) $query->fetchColumn();
}

function require_project_access(int $projectId, array $user): void
{
    if (!project_visible($projectId, $user)) {
        json_response(['error' => 'No tenés acceso a este proyecto.'], 403);
    }
}

function clean_string(mixed $value, int $max = 2000): string
{
    if (!is_scalar($value)) {
        return '';
    }
    return mb_substr(trim((string) $value), 0, $max);
}

function public_url(string $path): string
{
    $config = require __DIR__ . '/../config/app.php';
    if ($config['base_url'] !== '') {
        return $config['base_url'] . '/' . ltrim($path, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $directory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($directory === '' ? '' : $directory) . '/' . ltrim($path, '/');
}

function json_value(mixed $value): mixed
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
    return $value;
}

function record_visit(int $projectId, int $userId): void
{
    $statement = db()->prepare('INSERT INTO visitas (user_id, proyecto_id) VALUES (?, ?)');
    $statement->execute([$userId, $projectId]);
}

set_exception_handler(static function (Throwable $error): void {
    error_log($error->__toString());
    json_response(['error' => 'Ocurrió un error interno. Revisá la configuración y volvé a intentarlo.'], 500);
});

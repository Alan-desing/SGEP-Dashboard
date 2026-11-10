<?php
declare(strict_types=1);

function auth_login(array $data): array
{
    $email = mb_strtolower(clean_string($data['email'] ?? '', 190));
    $password = (string) ($data['password'] ?? '');
    $statement = db()->prepare('SELECT id, nombre, email, password, rol, activo FROM usuario WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user || !(bool) $user['activo'] || !password_verify($password, $user['password'])) {
        throw new DomainException('El correo o la contraseña no coinciden.');
    }
    session_regenerate_id(true);
    unset($user['password']);
    $_SESSION['user'] = $user;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return ['user' => $user, 'csrf' => $_SESSION['csrf']];
}

function auth_logout(): array
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    return ['ok' => true];
}

function auth_signup(array $data): array
{
    $name = clean_string($data['nombre'] ?? '', 140);
    $email = mb_strtolower(clean_string($data['email'] ?? '', 190));
    $password = (string) ($data['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        throw new DomainException('Completá tu nombre, correo y una contraseña de al menos 8 caracteres.');
    }
    $query = db()->prepare('SELECT 1 FROM usuario WHERE email=?');
    $query->execute([$email]);
    if ($query->fetchColumn()) {
        throw new DomainException('Ya existe una cuenta con ese correo.');
    }
    $insert = db()->prepare("INSERT INTO usuario (nombre,email,password,rol,activo) VALUES (?,?,?,'auditor',0)");
    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    return ['created' => true, 'message' => 'La cuenta quedó pendiente de habilitación por un administrador.'];
}

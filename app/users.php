<?php
declare(strict_types=1);

function users_list(): array
{
    $query = db()->query('SELECT id, nombre, email, rol, activo, created_at FROM usuario ORDER BY nombre');
    return $query->fetchAll();
}

function user_save(array $data, array $current): array
{
    require_role(['admin']);
    $id = (int) ($data['id'] ?? 0);
    $name = clean_string($data['nombre'] ?? '', 140);
    $email = mb_strtolower(clean_string($data['email'] ?? '', 190));
    $role = clean_string($data['rol'] ?? '', 20);
    $password = (string) ($data['password'] ?? '');
    $active = !empty($data['activo']) ? 1 : 0;
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin', 'pm', 'auditor'], true)) {
        throw new DomainException('Revisá el nombre, correo y rol seleccionados.');
    }
    if ($id === 0 && strlen($password) < 8) {
        throw new DomainException('La contraseña inicial debe tener al menos 8 caracteres.');
    }
    $connection = db();
    if ($id > 0) {
        $existing = $connection->prepare('SELECT rol, activo FROM usuario WHERE id=?');
        $existing->execute([$id]);
        $old = $existing->fetch();
        if (!$old) {
            throw new DomainException('No se encontró el usuario.');
        }
        if ($id === (int) $current['id'] && ($role !== 'admin' || $active === 0)) {
            throw new DomainException('No podés desactivar ni cambiar el rol de tu propia cuenta administrativa.');
        }
        $duplicate = $connection->prepare('SELECT 1 FROM usuario WHERE email=? AND id<>?');
        $duplicate->execute([$email, $id]);
        if ($duplicate->fetchColumn()) {
            throw new DomainException('Ya existe una cuenta con ese correo.');
        }
        if ($old['rol'] === 'admin' && ((int) $active === 0 || $role !== 'admin')) {
            $admins = (int) $connection->query("SELECT COUNT(*) FROM usuario WHERE rol='admin' AND activo=1")->fetchColumn();
            if ($admins <= 1 && (int) $old['activo'] === 1) {
                throw new DomainException('Debe quedar al menos un administrador activo.');
            }
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
            }
            $statement = $connection->prepare('UPDATE usuario SET nombre=?, email=?, password=?, rol=?, activo=? WHERE id=?');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $active, $id]);
        } else {
            $statement = $connection->prepare('UPDATE usuario SET nombre=?, email=?, rol=?, activo=? WHERE id=?');
            $statement->execute([$name, $email, $role, $active, $id]);
        }
        if ($role !== 'pm') {
            $connection->prepare('DELETE FROM proyecto_responsable WHERE user_id=?')->execute([$id]);
        }
        if ($id === (int) $current['id']) {
            $_SESSION['user']['nombre'] = $name;
            $_SESSION['user']['email'] = $email;
        }
    } else {
        $duplicate = $connection->prepare('SELECT 1 FROM usuario WHERE email=?');
        $duplicate->execute([$email]);
        if ($duplicate->fetchColumn()) {
            throw new DomainException('Ya existe una cuenta con ese correo.');
        }
        $statement = $connection->prepare('INSERT INTO usuario (nombre,email,password,rol,activo) VALUES (?,?,?,?,?)');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
        $id = (int) $connection->lastInsertId();
    }
    return ['id' => $id];
}

function user_delete(int $id, array $current): array
{
    require_role(['admin']);
    if ($id === (int) $current['id']) {
        throw new DomainException('No podés eliminar tu propia cuenta.');
    }
    $connection = db();
    $query = $connection->prepare('SELECT rol, activo FROM usuario WHERE id=?');
    $query->execute([$id]);
    $user = $query->fetch();
    if (!$user) {
        return ['deleted' => false];
    }
    if ($user['rol'] === 'admin' && (int) $user['activo'] === 1 && (int) $connection->query("SELECT COUNT(*) FROM usuario WHERE rol='admin' AND activo=1")->fetchColumn() <= 1) {
        throw new DomainException('Debe quedar al menos un administrador activo.');
    }
    $delete = $connection->prepare('DELETE FROM usuario WHERE id=?');
    $delete->execute([$id]);
    return ['deleted' => true];
}

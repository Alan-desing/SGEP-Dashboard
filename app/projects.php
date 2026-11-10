<?php
declare(strict_types=1);

function projects_list(array $user): array
{
    $sql = 'SELECT p.id, p.nombre, p.descripcion, p.cliente, p.estado, p.fecha_inicio, p.fecha_fin, p.avance, p.cumplimiento, p.created_at, GROUP_CONCAT(DISTINCT u.nombre ORDER BY u.nombre SEPARATOR ", ") AS responsables, COUNT(DISTINCT e.id) AS encuestas, COUNT(DISTINCT pa.id) AS respuestas FROM proyecto p LEFT JOIN proyecto_responsable pr ON pr.proyecto_id = p.id LEFT JOIN usuario u ON u.id = pr.user_id LEFT JOIN encuesta e ON e.proyecto_id = p.id LEFT JOIN participacion pa ON pa.encuesta_id = e.id';
    $params = [];
    if ($user['rol'] === 'pm') {
        $sql .= ' INNER JOIN proyecto_responsable scope ON scope.proyecto_id = p.id AND scope.user_id = ?';
        $params[] = $user['id'];
    }
    $sql .= ' GROUP BY p.id ORDER BY p.updated_at DESC, p.id DESC';
    $query = db()->prepare($sql);
    $query->execute($params);
    return $query->fetchAll();
}

function project_save(array $data, array $user): array
{
    if (!in_array($user['rol'], ['admin', 'pm'], true)) {
        throw new DomainException('Tu rol tiene acceso de solo lectura.');
    }
    $id = (int) ($data['id'] ?? 0);
    $name = clean_string($data['nombre'] ?? '', 180);
    $client = clean_string($data['cliente'] ?? '', 180);
    $status = clean_string($data['estado'] ?? 'planificacion', 20);
    $allowed = ['planificacion', 'en_curso', 'riesgo', 'completado', 'pausado'];
    if ($name === '' || $client === '' || !in_array($status, $allowed, true)) {
        throw new DomainException('Completá el nombre, cliente y estado del proyecto.');
    }
    $start = validated_project_date($data['fecha_inicio'] ?? '');
    $end = validated_project_date($data['fecha_fin'] ?? '');
    if ($start !== null && $end !== null && $end < $start) {
        throw new DomainException('La fecha de cierre debe ser igual o posterior al inicio.');
    }
    $progress = max(0, min(100, (float) ($data['avance'] ?? 0)));
    $compliance = max(0, min(100, (float) ($data['cumplimiento'] ?? 0)));
    $description = clean_string($data['descripcion'] ?? '', 4000);
    $connection = db();
    $connection->beginTransaction();
    try {
        if ($id > 0) {
            require_project_access($id, $user);
            $statement = $connection->prepare('UPDATE proyecto SET nombre=?, descripcion=?, cliente=?, estado=?, fecha_inicio=?, fecha_fin=?, avance=?, cumplimiento=? WHERE id=?');
            $statement->execute([$name, $description, $client, $status, $start, $end, $progress, $compliance, $id]);
        } else {
            $statement = $connection->prepare('INSERT INTO proyecto (nombre, descripcion, cliente, estado, fecha_inicio, fecha_fin, avance, cumplimiento) VALUES (?,?,?,?,?,?,?,?)');
            $statement->execute([$name, $description, $client, $status, $start, $end, $progress, $compliance]);
            $id = (int) $connection->lastInsertId();
        }
        if ($user['rol'] === 'admin') {
            $responsibleInput = is_array($data['responsables'] ?? null) ? $data['responsables'] : [];
            $responsibles = array_values(array_unique(array_filter(array_map('intval', $responsibleInput), static fn (int $value): bool => $value > 0)));
            $connection->prepare('DELETE FROM proyecto_responsable WHERE proyecto_id=?')->execute([$id]);
            $assign = $connection->prepare("INSERT INTO proyecto_responsable (proyecto_id, user_id) SELECT ?, id FROM usuario WHERE id=? AND rol='pm' AND activo=1");
            foreach ($responsibles as $responsibleId) {
                $assign->execute([$id, $responsibleId]);
            }
        } elseif ($id > 0) {
            $connection->prepare('INSERT IGNORE INTO proyecto_responsable (proyecto_id, user_id) VALUES (?,?)')->execute([$id, $user['id']]);
        }
        $connection->commit();
    } catch (Throwable $error) {
        $connection->rollBack();
        throw $error;
    }
    return ['id' => $id];
}

function validated_project_date(mixed $value): ?string
{
    $text = clean_string($value, 10);
    if ($text === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $text) {
        throw new DomainException('Ingresá una fecha válida.');
    }
    return $text;
}

function project_delete(int $id, array $user): array
{
    require_role(['admin']);
    $statement = db()->prepare('DELETE FROM proyecto WHERE id=?');
    $statement->execute([$id]);
    return ['deleted' => $statement->rowCount() > 0];
}

function project_detail(int $id, array $user): array
{
    require_project_access($id, $user);
    $query = db()->prepare('SELECT p.*, GROUP_CONCAT(DISTINCT pr.user_id) AS responsable_ids, GROUP_CONCAT(DISTINCT u.nombre ORDER BY u.nombre SEPARATOR ", ") AS responsables FROM proyecto p LEFT JOIN proyecto_responsable pr ON pr.proyecto_id=p.id LEFT JOIN usuario u ON u.id=pr.user_id WHERE p.id=? GROUP BY p.id');
    $query->execute([$id]);
    $project = $query->fetch();
    if (!$project) {
        json_response(['error' => 'No se encontró el proyecto.'], 404);
    }
    record_visit($id, (int) $user['id']);
    $surveyQuery = db()->prepare('SELECT e.id, e.titulo, e.tipo, e.estado, e.anonima, e.token, e.created_at, COUNT(DISTINCT pa.id) AS respuestas FROM encuesta e LEFT JOIN participacion pa ON pa.encuesta_id=e.id WHERE e.proyecto_id=? GROUP BY e.id ORDER BY e.created_at DESC');
    $surveyQuery->execute([$id]);
    return ['project' => $project, 'surveys' => $surveyQuery->fetchAll()];
}

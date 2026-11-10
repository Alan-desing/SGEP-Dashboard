<?php
declare(strict_types=1);

function surveys_list(array $user): array
{
    $sql = 'SELECT e.id, e.proyecto_id, e.titulo, e.descripcion, e.tipo, e.anonima, e.publica, e.estado, e.token, e.created_at, p.nombre AS proyecto, COUNT(DISTINCT pa.id) AS respuestas FROM encuesta e JOIN proyecto p ON p.id=e.proyecto_id LEFT JOIN participacion pa ON pa.encuesta_id=e.id';
    $params = [];
    if ($user['rol'] === 'pm') {
        $sql .= ' JOIN proyecto_responsable scope ON scope.proyecto_id=p.id AND scope.user_id=?';
        $params[] = $user['id'];
    }
    $sql .= ' GROUP BY e.id ORDER BY e.created_at DESC';
    $query = db()->prepare($sql);
    $query->execute($params);
    return $query->fetchAll();
}

function survey_get(int $id, array $user): array
{
    $query = db()->prepare('SELECT e.*,p.nombre AS proyecto FROM encuesta e JOIN proyecto p ON p.id=e.proyecto_id WHERE e.id=?');
    $query->execute([$id]);
    $survey = $query->fetch();
    if (!$survey) {
        throw new DomainException('No se encontró la encuesta.');
    }
    require_project_access((int) $survey['proyecto_id'], $user);
    $questions = db()->prepare('SELECT id,texto,tipo,obligatoria,opciones,orden,metrica,condicion_pregunta_id,condicion_valor FROM pregunta WHERE encuesta_id=? AND activa=1 ORDER BY orden,id');
    $questions->execute([$id]);
    $survey['preguntas'] = $questions->fetchAll();
    foreach ($survey['preguntas'] as &$question) {
        $question['opciones'] = json_value($question['opciones']);
        $question['key'] = (string) $question['id'];
    }
    unset($question);
    return ['survey' => $survey];
}

function survey_templates(): array
{
    return db()->query('SELECT id, nombre, descripcion, tipo, preguntas FROM plantilla WHERE activa=1 ORDER BY nombre')->fetchAll();
}

function survey_save(array $data, array $user): array
{
    require_role(['admin', 'pm']);
    $id = (int) ($data['id'] ?? 0);
    $projectId = (int) ($data['proyecto_id'] ?? 0);
    $templateId = (int) ($data['plantilla_id'] ?? 0);
    $title = clean_string($data['titulo'] ?? '', 180);
    $type = clean_string($data['tipo'] ?? 'general', 30);
    $validTypes = ['satisfaccion', 'avance', 'cumplimiento', 'lecciones', 'general'];
    if ($title === '' || $projectId < 1 || !in_array($type, $validTypes, true)) {
        throw new DomainException('Completá el proyecto, título y tipo de encuesta.');
    }
    require_project_access($projectId, $user);
    $questions = $data['preguntas'] ?? [];
    if (!is_array($questions) || count($questions) === 0) {
        throw new DomainException('Agregá al menos una pregunta.');
    }
    if (count($questions) > 80) {
        throw new DomainException('La encuesta admite hasta 80 preguntas.');
    }
    $validQuestionTypes = ['texto', 'parrafo', 'unica', 'multiple', 'escala', 'nps', 'fecha', 'si_no'];
    $validMetrics = ['ninguna', 'nps', 'avance', 'cumplimiento', 'participacion'];
    $previousQuestionKeys = [];
    foreach ($questions as $question) {
        if (!is_array($question) || clean_string($question['texto'] ?? '', 600) === '' || !in_array($question['tipo'] ?? '', $validQuestionTypes, true)) {
            throw new DomainException('Cada pregunta necesita un texto y un tipo válido.');
        }
        if (!in_array($question['metrica'] ?? 'ninguna', $validMetrics, true)) {
            throw new DomainException('La métrica seleccionada no es válida.');
        }
        if (in_array($question['tipo'], ['unica', 'multiple'], true)) {
            if (!is_array($question['opciones'] ?? null) || count(array_filter($question['opciones'], static fn ($option): bool => is_string($option) && trim($option) !== '')) < 2) {
                throw new DomainException('Las preguntas de opciones necesitan al menos dos opciones.');
            }
        }
        if ($question['tipo'] === 'escala') {
            $scaleOptions = is_array($question['opciones'] ?? null) ? array_values($question['opciones']) : [];
            if (count($scaleOptions) < 2) {
                $scaleOptions = ['0', '100'];
            }
            if (!is_numeric($scaleOptions[0]) || !is_numeric($scaleOptions[1]) || (float) $scaleOptions[1] <= (float) $scaleOptions[0]) {
                throw new DomainException('La escala debe tener un mínimo y un máximo numéricos, en ese orden.');
            }
        }
        $conditionKey = clean_string($question['condicion_pregunta_id'] ?? '', 80);
        if ($conditionKey !== '' && !in_array($conditionKey, $previousQuestionKeys, true)) {
            throw new DomainException('La condición debe depender de una pregunta anterior.');
        }
        if ($conditionKey !== '' && clean_string($question['condicion_valor'] ?? '', 255) === '') {
            throw new DomainException('Elegí el valor que habilita la pregunta condicional.');
        }
        $questionKey = clean_string($question['key'] ?? ($question['id'] ?? ''), 80);
        if ($questionKey !== '') {
            $previousQuestionKeys[] = $questionKey;
        }
        if (!empty($question['id'])) {
            $previousQuestionKeys[] = (string) (int) $question['id'];
        }
    }
    $connection = db();
    $connection->beginTransaction();
    try {
        if ($id > 0) {
            $existing = $connection->prepare('SELECT proyecto_id FROM encuesta WHERE id=?');
            $existing->execute([$id]);
            $oldSurvey = $existing->fetch();
            if (!$oldSurvey) {
                throw new DomainException('No se encontró la encuesta.');
            }
            require_project_access((int) $oldSurvey['proyecto_id'], $user);
            $statement = $connection->prepare('UPDATE encuesta SET proyecto_id=?, plantilla_id=?, titulo=?, descripcion=?, tipo=?, anonima=?, publica=?, updated_at=NOW() WHERE id=?');
            $statement->execute([$projectId, $templateId ?: null, $title, clean_string($data['descripcion'] ?? '', 3000), $type, !empty($data['anonima']) ? 1 : 0, !empty($data['publica']) ? 1 : 0, $id]);
        } else {
            $token = bin2hex(random_bytes(16));
            $statement = $connection->prepare('INSERT INTO encuesta (proyecto_id, plantilla_id, creado_por, token, titulo, descripcion, tipo, anonima, publica, estado) VALUES (?,?,?,?,?,?,?,?,?,\'borrador\')');
            $statement->execute([$projectId, $templateId ?: null, $user['id'], $token, $title, clean_string($data['descripcion'] ?? '', 3000), $type, !empty($data['anonima']) ? 1 : 0, !empty($data['publica']) ? 1 : 0]);
            $id = (int) $connection->lastInsertId();
        }
        $questionQuery = $connection->prepare('SELECT id FROM pregunta WHERE encuesta_id=?');
        $questionQuery->execute([$id]);
        $oldQuestionIds = array_map('intval', $questionQuery->fetchAll(PDO::FETCH_COLUMN));
        $keptIds = [];
        $questionMap = [];
        foreach ($questions as $index => $question) {
            $questionId = (int) ($question['id'] ?? 0);
            $text = clean_string($question['texto'], 600);
            $kind = $question['tipo'];
            $required = !empty($question['obligatoria']) ? 1 : 0;
            $optionValues = isset($question['opciones']) && is_array($question['opciones']) ? array_values(array_map(static fn ($item): string => clean_string($item, 200), $question['opciones'])) : [];
            if ($kind === 'si_no') {
                $optionValues = ['Sí', 'No'];
            } elseif ($kind === 'escala' && count($optionValues) < 2) {
                $optionValues = ['0', '100'];
            }
            $options = $optionValues ? json_encode($optionValues, JSON_UNESCAPED_UNICODE) : null;
            $metric = $question['metrica'] ?? 'ninguna';
            $clientKey = clean_string($question['key'] ?? (string) $questionId, 80);
            if ($questionId > 0 && in_array($questionId, $oldQuestionIds, true)) {
                $save = $connection->prepare('UPDATE pregunta SET texto=?, tipo=?, obligatoria=?, opciones=?, orden=?, metrica=?, activa=1, condicion_pregunta_id=NULL, condicion_valor=NULL WHERE id=? AND encuesta_id=?');
                $save->execute([$text, $kind, $required, $options, $index + 1, $metric, $questionId, $id]);
                $savedId = $questionId;
            } else {
                $save = $connection->prepare('INSERT INTO pregunta (encuesta_id,texto,tipo,obligatoria,opciones,orden,metrica,activa) VALUES (?,?,?,?,?,?,?,1)');
                $save->execute([$id, $text, $kind, $required, $options, $index + 1, $metric]);
                $savedId = (int) $connection->lastInsertId();
            }
            $keptIds[] = $savedId;
            $questionMap[$clientKey] = $savedId;
            if ($questionId > 0) {
                $questionMap[(string) $questionId] = $savedId;
            }
        }
        $removeIds = array_values(array_diff($oldQuestionIds, $keptIds));
        foreach ($removeIds as $removeId) {
            $hasAnswers = $connection->prepare('SELECT COUNT(*) FROM respuesta WHERE pregunta_id=?');
            $hasAnswers->execute([$removeId]);
            if ((int) $hasAnswers->fetchColumn() > 0) {
                $connection->prepare('UPDATE pregunta SET activa=0 WHERE id=?')->execute([$removeId]);
                $connection->prepare('UPDATE pregunta SET condicion_pregunta_id=NULL, condicion_valor=NULL WHERE condicion_pregunta_id=?')->execute([$removeId]);
            } else {
                $connection->prepare('DELETE FROM pregunta WHERE id=?')->execute([$removeId]);
            }
        }
        foreach ($questions as $index => $question) {
            $savedId = $keptIds[$index];
            $conditionKey = clean_string($question['condicion_pregunta_id'] ?? '', 80);
            if ($conditionKey !== '' && isset($questionMap[$conditionKey])) {
                $parentId = $questionMap[$conditionKey];
                if ($parentId !== $savedId) {
                    $condition = $connection->prepare('UPDATE pregunta SET condicion_pregunta_id=?, condicion_valor=? WHERE id=?');
                    $condition->execute([$parentId, clean_string($question['condicion_valor'] ?? '', 255), $savedId]);
                }
            }
        }
        $connection->commit();
    } catch (Throwable $error) {
        $connection->rollBack();
        throw $error;
    }
    return ['id' => $id, 'token' => db()->query('SELECT token FROM encuesta WHERE id=' . (int) $id)->fetchColumn()];
}

function survey_publish(int $id, string $state, array $user): array
{
    require_role(['admin', 'pm']);
    if (!in_array($state, ['publicada', 'cerrada', 'borrador'], true)) {
        throw new DomainException('El estado solicitado no es válido.');
    }
    $query = db()->prepare('SELECT proyecto_id FROM encuesta WHERE id=?');
    $query->execute([$id]);
    $projectId = $query->fetchColumn();
    if (!$projectId) {
        throw new DomainException('No se encontró la encuesta.');
    }
    require_project_access((int) $projectId, $user);
    $statement = db()->prepare('UPDATE encuesta SET estado=?, fecha_inicio=IF(?=\'publicada\' AND fecha_inicio IS NULL, NOW(), fecha_inicio), updated_at=NOW() WHERE id=?');
    $statement->execute([$state, $state, $id]);
    return ['state' => $state];
}

function survey_delete(int $id, array $user): array
{
    require_role(['admin']);
    $statement = db()->prepare('DELETE FROM encuesta WHERE id=?');
    $statement->execute([$id]);
    return ['deleted' => $statement->rowCount() > 0];
}

function survey_public(string $token): array
{
    $query = db()->prepare('SELECT e.id, e.proyecto_id, e.titulo, e.descripcion, e.tipo, e.anonima, e.publica, e.estado, p.nombre AS proyecto FROM encuesta e JOIN proyecto p ON p.id=e.proyecto_id WHERE e.token=? LIMIT 1');
    $query->execute([$token]);
    $survey = $query->fetch();
    if (!$survey || $survey['estado'] !== 'publicada') {
        json_response(['error' => 'La encuesta no está disponible.'], 404);
    }
    if (!(bool) $survey['publica'] && !current_user()) {
        json_response(['error' => 'Iniciá sesión para responder esta encuesta.'], 401);
    }
    $query = db()->prepare('SELECT id, texto, tipo, obligatoria, opciones, orden, metrica, condicion_pregunta_id, condicion_valor FROM pregunta WHERE encuesta_id=? AND activa=1 ORDER BY orden,id');
    $query->execute([$survey['id']]);
    $survey['preguntas'] = $query->fetchAll();
    foreach ($survey['preguntas'] as &$question) {
        $question['opciones'] = json_value($question['opciones']);
    }
    unset($question);
    return ['survey' => $survey, 'csrf' => csrf_token()];
}

function survey_submit(string $token, array $data, ?array $user): array
{
    $surveyQuery = db()->prepare('SELECT id, anonima, estado, publica, fecha_fin FROM encuesta WHERE token=? LIMIT 1');
    $surveyQuery->execute([$token]);
    $survey = $surveyQuery->fetch();
    if (!$survey || $survey['estado'] !== 'publicada' || ($survey['fecha_fin'] && strtotime($survey['fecha_fin']) < time())) {
        throw new DomainException('La encuesta está cerrada o no existe.');
    }
    if (!(bool) $survey['publica'] && !$user) {
        json_response(['error' => 'Iniciá sesión para responder esta encuesta.'], 401);
    }
    if (!(bool) $survey['anonima'] && !$user) {
        json_response(['error' => 'Esta encuesta requiere una sesión para registrar la respuesta.'], 401);
    }
    $query = db()->prepare('SELECT id, texto, tipo, obligatoria, opciones, condicion_pregunta_id, condicion_valor FROM pregunta WHERE encuesta_id=? AND activa=1 ORDER BY orden,id');
    $query->execute([$survey['id']]);
    $questions = $query->fetchAll();
    $answers = $data['respuestas'] ?? [];
    if (!is_array($answers)) {
        throw new DomainException('Las respuestas no tienen un formato válido.');
    }
    $priorValues = [];
    foreach ($questions as $question) {
        $questionId = (int) $question['id'];
        $parentId = (int) ($question['condicion_pregunta_id'] ?? 0);
        if ($parentId > 0) {
            $parentAnswer = $priorValues[$parentId] ?? '';
            $conditionValue = (string) ($question['condicion_valor'] ?? '');
            $conditionMatches = is_array($parentAnswer) ? in_array($conditionValue, array_map('strval', $parentAnswer), true) : (string) $parentAnswer === $conditionValue;
            if (!$conditionMatches) {
                continue;
            }
        }
        $raw = $answers[(string) $questionId] ?? $answers[$questionId] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            if ((bool) $question['obligatoria']) {
                throw new DomainException('Respondé la pregunta obligatoria: ' . $question['texto']);
            }
            continue;
        }
        $options = json_value($question['opciones']);
        if ($question['tipo'] === 'si_no' && !is_array($options)) {
            $options = ['Sí', 'No'];
        }
        if ($question['tipo'] === 'nps' || $question['tipo'] === 'escala') {
            if (!is_numeric($raw)) {
                throw new DomainException('Ingresá un valor numérico para la pregunta ' . $question['texto']);
            }
            $number = (float) $raw;
            $maximum = 10;
            if ($question['tipo'] === 'nps') {
                $maximum = 10;
            } elseif (is_array($options) && count($options) >= 2 && is_numeric($options[1])) {
                $maximum = (float) $options[1];
            }
            if ($number < 0 || $number > $maximum) {
                throw new DomainException('El valor de la pregunta ' . $question['texto'] . ' está fuera de rango.');
            }
            $raw = $number;
        } elseif (in_array($question['tipo'], ['unica', 'si_no'], true)) {
            if (!is_string($raw)) {
                throw new DomainException('Elegí una opción válida.');
            }
            if (is_array($options) && !in_array($raw, $options, true)) {
                throw new DomainException('Elegí una de las opciones disponibles.');
            }
        } elseif ($question['tipo'] === 'multiple') {
            $invalidChoice = is_array($raw) && is_array($options) && array_filter($raw, static fn ($choice): bool => !is_string($choice) || !in_array($choice, $options, true));
            if (!is_array($raw) || !is_array($options) || $invalidChoice) {
                throw new DomainException('Elegí opciones válidas.');
            }
        } elseif ($question['tipo'] === 'fecha') {
            $date = is_string($raw) ? DateTimeImmutable::createFromFormat('!Y-m-d', $raw) : false;
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$date || ($dateErrors && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $raw) {
                throw new DomainException('Ingresá una fecha válida para la pregunta ' . $question['texto']);
            }
            $raw = $date->format('Y-m-d');
        } else {
            if (!is_scalar($raw)) {
                throw new DomainException('Ingresá una respuesta de texto válida.');
            }
            $raw = clean_string($raw, 5000);
        }
        $priorValues[$questionId] = $raw;
        $answers[(string) $questionId] = $raw;
    }
    $inviteToken = clean_string($data['invitacion'] ?? '', 32);
    $inviteId = null;
    if ($inviteToken !== '') {
        $inviteQuery = db()->prepare('SELECT id, estado FROM invitacion WHERE encuesta_id=? AND token=?');
        $inviteQuery->execute([$survey['id'], $inviteToken]);
        $invite = $inviteQuery->fetch();
        if (!$invite || $invite['estado'] === 'completada') {
            throw new DomainException('Este enlace de invitación ya fue utilizado o no es válido.');
        }
        $inviteId = (int) $invite['id'];
    }
    $isRegistered = $user !== null;
    $isAnonymous = (bool) $survey['anonima'];
    $userId = $isRegistered && !$isAnonymous ? (int) $user['id'] : null;
    $connection = db();
    $connection->beginTransaction();
    try {
        $submissionToken = sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', random_int(0, 65535), random_int(0, 65535), random_int(0, 65535), random_int(0, 4095), random_int(0, 16383), random_int(0, 65535), random_int(0, 65535), random_int(0, 65535));
        $participation = $connection->prepare('INSERT INTO participacion (encuesta_id,user_id,anonimo,registrado,token_envio) VALUES (?,?,?,?,?)');
        $participation->execute([$survey['id'], $userId, $isAnonymous ? 1 : 0, $isRegistered ? 1 : 0, $submissionToken]);
        $participationId = (int) $connection->lastInsertId();
        $saveAnswer = $connection->prepare('INSERT INTO respuesta (pregunta_id,participacion_id,user_id,title,datos,anonimo,registrado) VALUES (?,?,?,?,?,?,?)');
        foreach ($questions as $question) {
            $questionId = (int) $question['id'];
            if (!array_key_exists((string) $questionId, $answers)) {
                continue;
            }
            $value = $answers[(string) $questionId];
            $saveAnswer->execute([$questionId, $participationId, $userId, $question['texto'], json_encode($value, JSON_UNESCAPED_UNICODE), $isAnonymous ? 1 : 0, $isRegistered ? 1 : 0]);
        }
        if ($inviteId) {
            $connection->prepare("UPDATE invitacion SET estado='completada', participacion_id=? WHERE id=?")->execute([$participationId, $inviteId]);
        }
        $connection->commit();
    } catch (Throwable $error) {
        $connection->rollBack();
        throw $error;
    }
    return ['ok' => true, 'anonima' => $isAnonymous, 'registrado' => $isRegistered];
}

function survey_invite(int $surveyId, array $data, array $user): array
{
    require_role(['admin', 'pm']);
    $query = db()->prepare('SELECT e.token,e.titulo,e.proyecto_id,e.estado,p.nombre AS proyecto FROM encuesta e JOIN proyecto p ON p.id=e.proyecto_id WHERE e.id=?');
    $query->execute([$surveyId]);
    $survey = $query->fetch();
    if (!$survey) {
        throw new DomainException('No se encontró la encuesta.');
    }
    if ($survey['estado'] !== 'publicada') {
        throw new DomainException('Publicá la encuesta antes de enviar invitaciones.');
    }
    require_project_access((int) $survey['proyecto_id'], $user);
    $emailInput = is_array($data['emails'] ?? null) ? $data['emails'] : [];
    $emails = array_values(array_unique(array_map(static fn ($email): string => mb_strtolower(clean_string($email, 190)), $emailInput)));
    $emails = array_values(array_filter($emails, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL)));
    if (count($emails) === 0 || count($emails) > 300) {
        throw new DomainException('Ingresá entre 1 y 300 correos electrónicos válidos.');
    }
    $subject = preg_replace('/[\r\n]+/', ' ', clean_string($data['asunto'] ?? ('Invitación: ' . $survey['titulo']), 255));
    $message = clean_string($data['mensaje'] ?? 'Te invitamos a responder una encuesta breve sobre el proyecto.', 1500);
    $from = (require __DIR__ . '/../config/app.php')['mail_from'];
    $find = db()->prepare('SELECT id,token,estado FROM invitacion WHERE encuesta_id=? AND email=?');
    $insert = db()->prepare('INSERT INTO invitacion (encuesta_id,email,token) VALUES (?,?,?)');
    $retry = db()->prepare("UPDATE invitacion SET estado='pendiente' WHERE id=?");
    $send = db()->prepare('UPDATE invitacion SET estado=?, enviada_at=IF(?=\'pendiente\',NULL,NOW()) WHERE encuesta_id=? AND email=?');
    $added = 0;
    $sent = 0;
    foreach ($emails as $email) {
        $find->execute([$surveyId, $email]);
        $existing = $find->fetch();
        if ($existing && $existing['estado'] === 'completada') {
            continue;
        }
        if ($existing) {
            $token = $existing['token'];
            $retry->execute([$existing['id']]);
        } else {
            $token = bin2hex(random_bytes(16));
            $insert->execute([$surveyId, $email, $token]);
        }
        $added++;
        $url = public_url('survey.php?t=' . urlencode($survey['token']) . '&i=' . urlencode($token));
        $body = $message . "\n\n" . $url;
        $headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
        $ok = function_exists('mail') && @mail($email, $subject, $body, $headers);
        $status = $ok ? 'pendiente' : 'error';
        $send->execute([$status, $status, $surveyId, $email]);
        if ($ok) {
            db()->prepare('UPDATE invitacion SET estado=\'pendiente\', enviada_at=NOW() WHERE encuesta_id=? AND email=?')->execute([$surveyId, $email]);
            $sent++;
        }
    }
    return ['invitaciones_creadas' => $added, 'correos_enviados' => $sent, 'url' => public_url('survey.php?t=' . urlencode($survey['token']))];
}

function survey_remind(int $surveyId, array $data, array $user): array
{
    require_role(['admin', 'pm']);
    $surveyQuery = db()->prepare('SELECT e.titulo,e.proyecto_id,e.estado FROM encuesta e WHERE e.id=?');
    $surveyQuery->execute([$surveyId]);
    $survey = $surveyQuery->fetch();
    if (!$survey) {
        throw new DomainException('No se encontró la encuesta.');
    }
    if ($survey['estado'] !== 'publicada') {
        throw new DomainException('Publicá la encuesta antes de programar recordatorios.');
    }
    require_project_access((int) $survey['proyecto_id'], $user);
    $query = db()->prepare("SELECT id,email FROM invitacion WHERE encuesta_id=? AND estado IN ('pendiente','error')");
    $query->execute([$surveyId]);
    $invitations = $query->fetchAll();
    if (!$invitations) {
        throw new DomainException('No hay invitaciones pendientes para recordar.');
    }
    $subject = preg_replace('/[\r\n]+/', ' ', clean_string($data['asunto'] ?? ('Recordatorio: ' . $survey['titulo']), 255));
    $message = clean_string($data['mensaje'] ?? 'Te recordamos completar la encuesta del proyecto.', 1500);
    $schedule = clean_string($data['programado_para'] ?? '', 19);
    $scheduledAt = date('Y-m-d H:i:s');
    if ($schedule !== '') {
        $scheduledDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $schedule);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$scheduledDate || ($dateErrors && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            throw new DomainException('Elegí una fecha y hora válidas para el recordatorio.');
        }
        $scheduledAt = $scheduledDate->format('Y-m-d H:i:s');
    }
    $insert = db()->prepare('INSERT INTO recordatorio (encuesta_id,invitacion_id,destinatario_email,asunto,mensaje,programado_para) VALUES (?,?,?,?,?,?)');
    foreach ($invitations as $invitation) {
        $insert->execute([$surveyId, $invitation['id'], $invitation['email'], $subject, $message, $scheduledAt]);
    }
    return ['encolados' => count($invitations), 'programado_para' => $scheduledAt];
}

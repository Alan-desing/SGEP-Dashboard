<?php
declare(strict_types=1);

function valid_date_filter(mixed $value): string
{
    $text = clean_string($value ?? '', 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : '';
}

function analytics_filters(array $input, array $user): array
{
    $where = [];
    $params = [];
    if ($user['rol'] === 'pm') {
        $where[] = 'EXISTS (SELECT 1 FROM proyecto_responsable scope WHERE scope.proyecto_id=p.id AND scope.user_id=?)';
        $params[] = $user['id'];
    }
    $projectId = (int) ($input['proyecto_id'] ?? 0);
    if ($projectId > 0) {
        require_project_access($projectId, $user);
        $where[] = 'p.id=?';
        $params[] = $projectId;
    }
    $from = valid_date_filter($input['desde'] ?? '');
    if ($from !== '') {
        $where[] = 'pa.created_at>=?';
        $params[] = $from . ' 00:00:00';
    }
    $to = valid_date_filter($input['hasta'] ?? '');
    if ($to !== '') {
        $where[] = 'pa.created_at<=?';
        $params[] = $to . ' 23:59:59';
    }
    $client = clean_string($input['cliente'] ?? '', 180);
    if ($client !== '') {
        $where[] = 'p.cliente LIKE ?';
        $params[] = '%' . $client . '%';
    }
    $responsible = (int) ($input['responsable_id'] ?? 0);
    if ($responsible > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM proyecto_responsable rscope WHERE rscope.proyecto_id=p.id AND rscope.user_id=?)';
        $params[] = $responsible;
    }
    $type = clean_string($input['tipo'] ?? '', 30);
    if (in_array($type, ['satisfaccion', 'avance', 'cumplimiento', 'lecciones', 'general'], true)) {
        $where[] = 'e.tipo=?';
        $params[] = $type;
    }
    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
}

function dashboard_data(array $filters, array $user): array
{
    [$where, $params] = analytics_filters($filters, $user);
    $db = db();
    $rowsQuery = $db->prepare('SELECT p.id AS proyecto_id,p.nombre AS proyecto,p.cliente,p.estado,p.avance AS avance_proyecto,p.cumplimiento AS cumplimiento_proyecto,e.id AS encuesta_id,e.tipo,pa.id AS participacion_id,pa.created_at AS respondida_at,q.id AS pregunta_id,q.texto AS pregunta,q.tipo AS tipo_pregunta,q.metrica,q.opciones,r.datos FROM participacion pa JOIN encuesta e ON e.id=pa.encuesta_id JOIN proyecto p ON p.id=e.proyecto_id LEFT JOIN respuesta r ON r.participacion_id=pa.id LEFT JOIN pregunta q ON q.id=r.pregunta_id' . $where . ' ORDER BY pa.created_at');
    $rowsQuery->execute($params);
    $groups = [];
    foreach ($rowsQuery->fetchAll() as $row) {
        $key = (string) $row['participacion_id'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'project_id' => (int) $row['proyecto_id'],
                'project' => $row['proyecto'],
                'client' => $row['cliente'],
                'status' => $row['estado'],
                'survey_id' => (int) $row['encuesta_id'],
                'type' => $row['tipo'],
                'date' => $row['respondida_at'],
                'nps' => [],
                'progress' => [],
                'compliance' => []
            ];
        }
        if ($row['pregunta_id'] === null) {
            continue;
        }
        $value = json_value($row['datos']);
        $metric = $row['metrica'];
        if ($metric === 'nps' && is_numeric($value)) {
            $groups[$key]['nps'][] = (float) $value;
        } elseif ($metric === 'avance' && is_numeric($value)) {
            $maximum = 100;
            $options = json_value($row['opciones']);
            if (is_array($options) && count($options) >= 2 && is_numeric($options[0]) && is_numeric($options[1])) {
                $minimum = (float) $options[0];
                $maximum = max($minimum + 1, (float) $options[1]);
                $value = (((float) $value - $minimum) / ($maximum - $minimum)) * 100;
            }
            $groups[$key]['progress'][] = max(0, min(100, (float) $value));
        } elseif ($metric === 'cumplimiento') {
            if (is_numeric($value)) {
                $groups[$key]['compliance'][] = max(0, min(100, (float) $value));
            } elseif (is_bool($value) || is_string($value)) {
                $normalized = mb_strtolower(trim((string) $value));
                if (in_array($normalized, ['sí', 'si', 'yes', 'true', '1', 'cumplido'], true)) {
                    $groups[$key]['compliance'][] = 100;
                } elseif (in_array($normalized, ['no', 'false', '0', 'incumplido'], true)) {
                    $groups[$key]['compliance'][] = 0;
                }
            }
        }
    }
    $projectSql = 'SELECT p.id,p.nombre,p.cliente,p.estado,p.avance,p.cumplimiento,GROUP_CONCAT(DISTINCT u.nombre ORDER BY u.nombre SEPARATOR ", ") AS responsables FROM proyecto p LEFT JOIN proyecto_responsable pr ON pr.proyecto_id=p.id LEFT JOIN usuario u ON u.id=pr.user_id';
    $projectWhere = [];
    $projectParams = [];
    if ($user['rol'] === 'pm') {
        $projectWhere[] = 'EXISTS (SELECT 1 FROM proyecto_responsable own WHERE own.proyecto_id=p.id AND own.user_id=?)';
        $projectParams[] = $user['id'];
    }
    if (!empty($filters['proyecto_id'])) {
        $projectWhere[] = 'p.id=?';
        $projectParams[] = (int) $filters['proyecto_id'];
    }
    if (!empty($filters['cliente'])) {
        $projectWhere[] = 'p.cliente LIKE ?';
        $projectParams[] = '%' . clean_string($filters['cliente'], 180) . '%';
    }
    if (!empty($filters['responsable_id'])) {
        $projectWhere[] = 'EXISTS (SELECT 1 FROM proyecto_responsable selected_owner WHERE selected_owner.proyecto_id=p.id AND selected_owner.user_id=?)';
        $projectParams[] = (int) $filters['responsable_id'];
    }
    if (!empty($filters['tipo'])) {
        $projectWhere[] = 'EXISTS (SELECT 1 FROM encuesta filtered_survey WHERE filtered_survey.proyecto_id=p.id AND filtered_survey.tipo=?)';
        $projectParams[] = clean_string($filters['tipo'], 30);
    }
    if ($projectWhere) {
        $projectSql .= ' WHERE ' . implode(' AND ', $projectWhere);
    }
    $projectSql .= ' GROUP BY p.id ORDER BY p.nombre';
    $projectsQuery = $db->prepare($projectSql);
    $projectsQuery->execute($projectParams);
    $projects = $projectsQuery->fetchAll();
    $projectIds = array_map(static fn (array $project): int => (int) $project['id'], $projects);
    $invitationWhere = [];
    $invitationParams = [];
    if ($projectIds) {
        $invitationWhere[] = 'p.id IN (' . implode(',', array_fill(0, count($projectIds), '?')) . ')';
        $invitationParams = $projectIds;
    } else {
        $invitationWhere[] = '1=0';
    }
    if (!empty($filters['proyecto_id'])) {
        $invitationWhere[] = 'p.id=?';
        $invitationParams[] = (int) $filters['proyecto_id'];
    }
    if (!empty($filters['tipo'])) {
        $invitationWhere[] = 'e.tipo=?';
        $invitationParams[] = clean_string($filters['tipo'], 30);
    }
    if (!empty($filters['cliente'])) {
        $invitationWhere[] = 'p.cliente LIKE ?';
        $invitationParams[] = '%' . clean_string($filters['cliente'], 180) . '%';
    }
    if (!empty($filters['responsable_id'])) {
        $invitationWhere[] = 'EXISTS (SELECT 1 FROM proyecto_responsable ir WHERE ir.proyecto_id=p.id AND ir.user_id=?)';
        $invitationParams[] = (int) $filters['responsable_id'];
    }
    $invitationWhere[] = $user['rol'] === 'pm' ? 'EXISTS (SELECT 1 FROM proyecto_responsable ip WHERE ip.proyecto_id=p.id AND ip.user_id=?)' : '1=1';
    if ($user['rol'] === 'pm') {
        $invitationParams[] = $user['id'];
    }
    $invFrom = valid_date_filter($filters['desde'] ?? '');
    $invTo = valid_date_filter($filters['hasta'] ?? '');
    if ($invFrom !== '') {
        $invitationWhere[] = 'i.created_at>=?';
        $invitationParams[] = $invFrom . ' 00:00:00';
    }
    if ($invTo !== '') {
        $invitationWhere[] = 'i.created_at<=?';
        $invitationParams[] = $invTo . ' 23:59:59';
    }
    $inviteQuery = $db->prepare("SELECT e.id AS encuesta_id,COUNT(i.id) AS invitadas,SUM(i.estado='completada') AS completadas FROM encuesta e JOIN proyecto p ON p.id=e.proyecto_id LEFT JOIN invitacion i ON i.encuesta_id=e.id WHERE " . implode(' AND ', $invitationWhere) . ' GROUP BY e.id');
    $inviteQuery->execute($invitationParams);
    $invites = [];
    foreach ($inviteQuery->fetchAll() as $row) {
        $invites[(int) $row['encuesta_id']] = ['total' => (int) $row['invitadas'], 'completed' => (int) ($row['completadas'] ?? 0)];
    }
    $npsValues = [];
    $progressValues = [];
    $complianceValues = [];
    $monthCounts = [];
    $projectMetrics = [];
    $totalResponses = count($groups);
    foreach ($groups as $submission) {
        if ($submission['nps']) {
            $npsValues = array_merge($npsValues, $submission['nps']);
        }
        if ($submission['progress']) {
            $progressValues = array_merge($progressValues, $submission['progress']);
        }
        if ($submission['compliance']) {
            $complianceValues = array_merge($complianceValues, $submission['compliance']);
        }
        $month = substr($submission['date'], 0, 7);
        $monthCounts[$month] = ($monthCounts[$month] ?? 0) + 1;
        if (!isset($projectMetrics[$submission['project_id']])) {
            $projectMetrics[$submission['project_id']] = ['name' => $submission['project'], 'nps' => [], 'progress' => [], 'compliance' => [], 'responses' => 0];
        }
        $projectMetrics[$submission['project_id']]['nps'] = array_merge($projectMetrics[$submission['project_id']]['nps'], $submission['nps']);
        $projectMetrics[$submission['project_id']]['progress'] = array_merge($projectMetrics[$submission['project_id']]['progress'], $submission['progress']);
        $projectMetrics[$submission['project_id']]['compliance'] = array_merge($projectMetrics[$submission['project_id']]['compliance'], $submission['compliance']);
        $projectMetrics[$submission['project_id']]['responses']++;
    }
    $nps = null;
    if ($npsValues) {
        $promoters = count(array_filter($npsValues, static fn (float $score): bool => $score >= 9));
        $detractors = count(array_filter($npsValues, static fn (float $score): bool => $score <= 6));
        $nps = (int) round((($promoters - $detractors) / count($npsValues)) * 100);
    }
    $average = static fn (array $values): ?float => $values ? round(array_sum($values) / count($values), 1) : null;
    $invitationCount = array_sum(array_column($invites, 'total'));
    $completedInvites = array_sum(array_column($invites, 'completed'));
    $participation = $invitationCount > 0 ? round(($completedInvites / $invitationCount) * 100, 1) : null;
    $riskProjects = [];
    $hasDateFilter = valid_date_filter($filters['desde'] ?? '') !== '' || valid_date_filter($filters['hasta'] ?? '') !== '';
    if ($hasDateFilter) {
        $projects = array_values(array_filter($projects, static fn (array $project): bool => isset($projectMetrics[(int) $project['id']])));
    }
    foreach ($projects as &$project) {
        $id = (int) $project['id'];
        $metrics = $projectMetrics[$id] ?? ['nps' => [], 'progress' => [], 'compliance' => [], 'responses' => 0];
        $projectNps = null;
        if ($metrics['nps']) {
            $promoters = count(array_filter($metrics['nps'], static fn (float $score): bool => $score >= 9));
            $detractors = count(array_filter($metrics['nps'], static fn (float $score): bool => $score <= 6));
            $projectNps = (int) round((($promoters - $detractors) / count($metrics['nps'])) * 100);
        }
        $project['nps'] = $projectNps;
        $project['avance_percibido'] = $average($metrics['progress']);
        $project['cumplimiento_medicion'] = $average($metrics['compliance']);
        $project['respuestas'] = $metrics['responses'];
        $project['riesgo'] = $project['estado'] === 'riesgo' || ($projectNps !== null && $projectNps < -10) || ($project['cumplimiento_medicion'] !== null && $project['cumplimiento_medicion'] < 60);
        if ($project['riesgo']) {
            $riskProjects[] = $project;
        }
    }
    unset($project);
    ksort($monthCounts);
    $npsDistribution = [
        'Detractores' => count(array_filter($npsValues, static fn (float $score): bool => $score <= 6)),
        'Pasivos' => count(array_filter($npsValues, static fn (float $score): bool => $score >= 7 && $score <= 8)),
        'Promotores' => count(array_filter($npsValues, static fn (float $score): bool => $score >= 9))
    ];
    $bar = [];
    foreach ($projects as $project) {
        $bar[] = ['proyecto' => $project['nombre'], 'nps' => $project['nps'], 'avance' => $project['avance_percibido'], 'cumplimiento' => $project['cumplimiento_medicion']];
    }
    $responsiblesQuery = $db->query("SELECT id,nombre FROM usuario WHERE rol='pm' AND activo=1 ORDER BY nombre");
    return [
        'kpis' => ['nps' => $nps, 'avance' => $average($progressValues), 'cumplimiento' => $average($complianceValues), 'participacion' => $participation, 'respuestas' => $totalResponses, 'invitaciones' => $invitationCount],
        'nps_distribution' => $npsDistribution,
        'trend' => ['labels' => array_keys($monthCounts), 'values' => array_values($monthCounts)],
        'projects' => $projects,
        'risk_projects' => $riskProjects,
        'project_chart' => $bar,
        'filters' => ['projects' => $projects, 'responsibles' => $responsiblesQuery->fetchAll()]
    ];
}

function results_data(int $surveyId, array $filters, array $user): array
{
    $surveyQuery = db()->prepare('SELECT e.*,p.nombre AS proyecto,p.cliente,p.estado AS estado_proyecto FROM encuesta e JOIN proyecto p ON p.id=e.proyecto_id WHERE e.id=?');
    $surveyQuery->execute([$surveyId]);
    $survey = $surveyQuery->fetch();
    if (!$survey) {
        throw new DomainException('No se encontró la encuesta.');
    }
    require_project_access((int) $survey['proyecto_id'], $user);
    $from = valid_date_filter($filters['desde'] ?? '');
    $to = valid_date_filter($filters['hasta'] ?? '');
    $sql = 'SELECT pa.id AS participacion_id,pa.created_at,pa.anonimo,pa.registrado,q.id AS pregunta_id,q.texto,q.tipo,q.metrica,r.datos FROM participacion pa LEFT JOIN respuesta r ON r.participacion_id=pa.id LEFT JOIN pregunta q ON q.id=r.pregunta_id WHERE pa.encuesta_id=?';
    $params = [$surveyId];
    if ($from !== '') {
        $sql .= ' AND pa.created_at>=?';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $sql .= ' AND pa.created_at<=?';
        $params[] = $to . ' 23:59:59';
    }
    $sql .= ' ORDER BY pa.created_at DESC,q.id';
    $query = db()->prepare($sql);
    $query->execute($params);
    $submissions = [];
    $questions = [];
    foreach ($query->fetchAll() as $row) {
        $participationId = (int) $row['participacion_id'];
        if (!isset($submissions[$participationId])) {
            $submissions[$participationId] = ['id' => $participationId, 'created_at' => $row['created_at'], 'anonimo' => (bool) $row['anonimo'], 'registrado' => (bool) $row['registrado'], 'answers' => []];
        }
        if ($row['pregunta_id'] === null) {
            continue;
        }
        $questionId = (int) $row['pregunta_id'];
        if (!isset($questions[$questionId])) {
            $questions[$questionId] = ['id' => $questionId, 'texto' => $row['texto'], 'tipo' => $row['tipo'], 'metrica' => $row['metrica'], 'respuestas' => []];
        }
        $value = json_value($row['datos']);
        $questions[$questionId]['respuestas'][] = $value;
        $submissions[$participationId]['answers'][$questionId] = $value;
    }
    foreach ($questions as &$question) {
        $distribution = [];
        foreach ($question['respuestas'] as $answer) {
            $values = is_array($answer) ? $answer : [$answer];
            foreach ($values as $value) {
                $label = (string) $value;
                $distribution[$label] = ($distribution[$label] ?? 0) + 1;
            }
        }
        arsort($distribution);
        $question['distribucion'] = $distribution;
    }
    unset($question);
    $inviteQuery = db()->prepare("SELECT COUNT(*) AS invitadas,SUM(estado='completada') AS completadas FROM invitacion WHERE encuesta_id=?");
    $inviteQuery->execute([$surveyId]);
    $invites = $inviteQuery->fetch();
    return ['survey' => $survey, 'total' => count($submissions), 'preguntas' => array_values($questions), 'participaciones' => array_values($submissions), 'invitaciones' => ['total' => (int) $invites['invitadas'], 'completadas' => (int) ($invites['completadas'] ?? 0)]];
}

function export_results_csv(int $surveyId, array $filters, array $user): never
{
    $results = results_data($surveyId, $filters, $user);
    $filename = 'resultados-encuesta-' . $surveyId . '-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Fecha', 'Participación', 'Anónima', 'Registrada', 'Pregunta', 'Respuesta'], ';');
    $questionNames = [];
    foreach ($results['preguntas'] as $question) {
        $questionNames[$question['id']] = $question['texto'];
    }
    foreach ($results['participaciones'] as $submission) {
        foreach ($submission['answers'] as $questionId => $answer) {
            fputcsv($output, [$submission['created_at'], $submission['id'], $submission['anonimo'] ? 'Sí' : 'No', $submission['registrado'] ? 'Sí' : 'No', $questionNames[$questionId] ?? '', is_array($answer) ? implode(', ', $answer) : (string) $answer], ';');
        }
    }
    fclose($output);
    exit;
}

function export_dashboard_csv(array $filters, array $user): never
{
    $data = dashboard_data($filters, $user);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte-dashboard-' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Indicador', 'Valor'], ';');
    fputcsv($output, ['NPS', $data['kpis']['nps'] ?? 'Sin dato'], ';');
    fputcsv($output, ['Avance percibido (%)', $data['kpis']['avance'] ?? 'Sin dato'], ';');
    fputcsv($output, ['Cumplimiento (%)', $data['kpis']['cumplimiento'] ?? 'Sin dato'], ';');
    fputcsv($output, ['Participación de invitaciones (%)', $data['kpis']['participacion'] ?? 'Sin dato'], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Proyecto', 'Cliente', 'Responsables', 'Estado', 'NPS', 'Avance percibido', 'Cumplimiento medido', 'Respuestas'], ';');
    foreach ($data['projects'] as $project) {
        fputcsv($output, [$project['nombre'], $project['cliente'], $project['responsables'], $project['estado'], $project['nps'] ?? '', $project['avance_percibido'] ?? '', $project['cumplimiento_medicion'] ?? '', $project['respuestas']], ';');
    }
    fclose($output);
    exit;
}

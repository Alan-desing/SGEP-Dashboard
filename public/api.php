<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/projects.php';
require __DIR__ . '/../app/users.php';
require __DIR__ . '/../app/surveys.php';
require __DIR__ . '/../app/analytics.php';

$action = clean_string($_GET['action'] ?? '', 60);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$data = $method === 'GET' ? $_GET : request_data();
$writeActions = ['auth.login', 'auth.signup', 'auth.logout', 'projects.save', 'projects.delete', 'users.save', 'users.delete', 'surveys.save', 'surveys.publish', 'surveys.delete', 'survey.submit', 'surveys.invite', 'surveys.remind'];
$readActions = ['auth.me', 'projects.list', 'projects.get', 'users.list', 'surveys.list', 'surveys.get', 'surveys.templates', 'survey.public', 'dashboard.data', 'dashboard.csv', 'results.data', 'results.csv'];

if (in_array($action, $writeActions, true) && $method !== 'POST') {
    json_response(['error' => 'Esta operación requiere una solicitud POST.'], 405);
}
if (in_array($action, $readActions, true) && $method !== 'GET') {
    json_response(['error' => 'Esta operación requiere una solicitud GET.'], 405);
}
if (!in_array($action, $writeActions, true) && !in_array($action, $readActions, true)) {
    json_response(['error' => 'La operación solicitada no existe.'], 404);
}
if ($method === 'POST' && $action !== 'auth.login') {
    require_csrf();
}

try {
    $result = match ($action) {
        'auth.me' => ['user' => current_user(), 'csrf' => csrf_token()],
        'auth.login' => auth_login($data),
        'auth.signup' => auth_signup($data),
        'auth.logout' => auth_logout(),
        'projects.list' => projects_list(require_user()),
        'projects.get' => project_detail((int) ($data['id'] ?? 0), require_user()),
        'projects.save' => project_save($data, require_user()),
        'projects.delete' => project_delete((int) ($data['id'] ?? 0), require_user()),
        'users.list' => users_list_after_admin(),
        'users.save' => user_save($data, require_user()),
        'users.delete' => user_delete((int) ($data['id'] ?? 0), require_user()),
        'surveys.list' => surveys_list(require_user()),
        'surveys.get' => survey_get((int) ($data['id'] ?? 0), require_user()),
        'surveys.templates' => survey_templates(),
        'surveys.save' => survey_save($data, require_user()),
        'surveys.publish' => survey_publish((int) ($data['id'] ?? 0), clean_string($data['estado'] ?? '', 20), require_user()),
        'surveys.delete' => survey_delete((int) ($data['id'] ?? 0), require_user()),
        'survey.public' => survey_public(clean_string($data['token'] ?? '', 32)),
        'survey.submit' => survey_submit(clean_string($data['token'] ?? '', 32), $data, current_user()),
        'surveys.invite' => survey_invite((int) ($data['id'] ?? 0), $data, require_user()),
        'surveys.remind' => survey_remind((int) ($data['id'] ?? 0), $data, require_user()),
        'dashboard.data' => dashboard_data($data, require_user()),
        'dashboard.csv' => export_dashboard_csv($data, require_user()),
        'results.data' => results_data((int) ($data['id'] ?? 0), $data, require_user()),
        'results.csv' => export_results_csv((int) ($data['id'] ?? 0), $data, require_user()),
        default => null
    };
    if ($action === 'users.list') {
        json_response($result);
    }
    if ($result === null) {
        json_response(['error' => 'La operación solicitada no existe.'], 404);
    }
    json_response($result);
} catch (DomainException $error) {
    json_response(['error' => $error->getMessage()], 422);
}

function users_list_after_admin(): array
{
    require_role(['admin']);
    return users_list();
}

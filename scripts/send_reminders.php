<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/../config/app.php';
$query = db()->query("SELECT r.id,r.destinatario_email,r.asunto,r.mensaje,e.token AS encuesta_token,i.token AS invitacion_token FROM recordatorio r JOIN encuesta e ON e.id=r.encuesta_id LEFT JOIN invitacion i ON i.id=r.invitacion_id WHERE r.estado='pendiente' AND r.programado_para<=NOW() ORDER BY r.programado_para,r.id LIMIT 200");
$reminders = $query->fetchAll();
$sent = db()->prepare("UPDATE recordatorio SET estado='enviado',intentos=intentos+1,enviado_at=NOW(),ultimo_error=NULL WHERE id=?");
$failed = db()->prepare("UPDATE recordatorio SET estado='error',intentos=intentos+1,ultimo_error=? WHERE id=?");
$from = $config['mail_from'];
$base = $config['base_url'];
$sentCount = 0;
foreach ($reminders as $reminder) {
    $url = $base !== '' ? rtrim($base, '/') . '/survey.php?t=' . urlencode($reminder['encuesta_token']) . ($reminder['invitacion_token'] ? '&i=' . urlencode($reminder['invitacion_token']) : '') : '';
    $body = $reminder['mensaje'] . ($url !== '' ? "\n\n" . $url : '');
    $headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
    $ok = $url !== '' && @mail($reminder['destinatario_email'], $reminder['asunto'], $body, $headers);
    if ($ok) {
        $sent->execute([$reminder['id']]);
        $sentCount++;
    } else {
        $failed->execute(['No se pudo enviar. Revisá la configuración de correo PHP y SGEP_BASE_URL.', $reminder['id']]);
    }
}
fwrite(STDOUT, count($reminders) . " recordatorios procesados; " . $sentCount . " enviados.\n");

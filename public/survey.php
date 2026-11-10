<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
$token = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['t'] ?? ''));
$invite = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['i'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#176b72">
  <title>Responder encuesta · SGEP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
  <main class="survey-public-shell">
    <div class="survey-public-card">
      <div id="surveyHeader" class="survey-public-head"><p class="eyebrow">SGEP · Encuesta</p><h1>Cargando encuesta</h1><p class="text-secondary mb-0">Un momento, por favor.</p></div>
      <div id="surveyBody" class="survey-public-body"><div class="text-center py-5 text-secondary">Cargando preguntas…</div></div>
      <div class="survey-public-footer">Sistema de Gestión de Encuestas para la Evaluación de Proyectos</div>
    </div>
  </main>
  <script>window.SGEP_PUBLIC={token:<?= json_encode($token) ?>,invite:<?= json_encode($invite) ?>};</script>
  <script src="assets/survey.js" defer></script>
</body>
</html>

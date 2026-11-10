<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/projects.php';
require __DIR__ . '/../app/analytics.php';

$user = require_user();
$id = (int) ($_GET['id'] ?? 0);
$results = results_data($id, $_GET, $user);
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reporte · <?= $escape($results['survey']['titulo']) ?></title>
  <style>
    body{font:14px Arial,sans-serif;color:#203846;margin:35px auto;max-width:980px;padding:0 20px}header{border-bottom:2px solid #176b72;padding-bottom:15px;margin-bottom:22px}h1{font-size:24px;margin:5px 0}h2{font-size:16px;margin:0 0 10px}p,small{color:#61747e}.actions{position:fixed;right:25px;top:20px}.actions button{padding:10px 14px;border:0;border-radius:6px;background:#176b72;color:#fff;cursor:pointer}.summary{display:flex;gap:25px;margin:15px 0 25px}.summary div{padding:12px;background:#f2f7f6;border-radius:8px}.question{border:1px solid #dfe7e9;border-radius:9px;padding:14px;margin:13px 0;page-break-inside:avoid}.answer{padding:6px 9px;background:#f6f8f9;border-radius:6px;margin:5px 0}table{width:100%;border-collapse:collapse;font-size:12px}td,th{text-align:left;border-bottom:1px solid #e5ebed;padding:8px;vertical-align:top}th{color:#61747e;text-transform:uppercase;font-size:10px}@media print{body{margin:0 auto}.actions{display:none}a{color:inherit;text-decoration:none}}
  </style>
</head>
<body>
  <div class="actions"><button onclick="window.print()">Guardar como PDF / Imprimir</button></div>
  <header><small>SGEP · Reporte de encuesta</small><h1><?= $escape($results['survey']['titulo']) ?></h1><p><?= $escape($results['survey']['proyecto']) ?> · <?= $escape($results['survey']['cliente']) ?> · <?= $escape($results['survey']['tipo']) ?></p><small>Generado el <?= date('d/m/Y H:i') ?></small></header>
  <div class="summary"><div><small>Respuestas</small><h2><?= (int) $results['total'] ?></h2></div><div><small>Invitaciones completadas</small><h2><?= (int) $results['invitaciones']['completadas'] ?> / <?= (int) $results['invitaciones']['total'] ?></h2></div></div>
  <?php foreach ($results['preguntas'] as $index => $question): ?>
    <section class="question"><h2><?= $index + 1 ?>. <?= $escape($question['texto']) ?></h2><small><?= count($question['respuestas']) ?> respuestas</small>
      <?php foreach ($question['respuestas'] as $answer): ?>
        <div class="answer"><?= $escape(is_array($answer) ? implode(', ', $answer) : $answer) ?></div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
  <h2>Participaciones</h2>
  <table><thead><tr><th>Fecha</th><th>Anonimato</th><th>Registrada</th><th>Respuestas</th></tr></thead><tbody>
    <?php foreach ($results['participaciones'] as $submission): ?>
      <tr><td><?= $escape($submission['created_at']) ?></td><td><?= $submission['anonimo'] ? 'Sí' : 'No' ?></td><td><?= $submission['registrado'] ? 'Sí' : 'No' ?></td><td><?php foreach ($submission['answers'] as $questionId => $answer): ?><?= $escape($questionId) ?>: <?= $escape(is_array($answer) ? implode(', ', $answer) : $answer) ?><br><?php endforeach; ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <script>window.addEventListener('load',()=>window.print());</script>
</body>
</html>

<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/analytics.php';

$user = require_user();
$data = dashboard_data($_GET, $user);
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte de dashboard SGEP</title>
  <style>
    body{font:13px Arial,sans-serif;color:#203846;margin:35px auto;max-width:1100px;padding:0 20px}header{border-bottom:2px solid #176b72;padding-bottom:13px;margin-bottom:20px}h1{font-size:24px;margin:5px 0}h2{font-size:16px;margin:22px 0 10px}.actions{position:fixed;right:25px;top:20px}.actions button{padding:10px 14px;border:0;border-radius:6px;background:#176b72;color:#fff;cursor:pointer}.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.kpi{background:#f1f7f5;border-radius:8px;padding:12px}.kpi small{display:block;color:#637780}.kpi strong{display:block;font-size:23px;margin-top:6px}table{width:100%;border-collapse:collapse;font-size:11px}td,th{text-align:left;border-bottom:1px solid #e5ebed;padding:8px;vertical-align:top}th{color:#61747e;text-transform:uppercase;font-size:9px}.risk{color:#ae554f;font-weight:bold}.stable{color:#397651;font-weight:bold}@media print{body{margin:0 auto}.actions{display:none}}
  </style>
</head>
<body>
  <div class="actions"><button onclick="window.print()">Guardar como PDF / Imprimir</button></div>
  <header><small>SGEP · Dashboard de proyectos</small><h1>Resumen de evaluación</h1><p>Generado el <?= date('d/m/Y H:i') ?></p></header>
  <div class="kpis">
    <div class="kpi"><small>NPS</small><strong><?= $data['kpis']['nps'] === null ? 'Sin dato' : (int) $data['kpis']['nps'] ?></strong></div>
    <div class="kpi"><small>Avance percibido</small><strong><?= $data['kpis']['avance'] === null ? 'Sin dato' : $escape($data['kpis']['avance']) . '%' ?></strong></div>
    <div class="kpi"><small>Cumplimiento</small><strong><?= $data['kpis']['cumplimiento'] === null ? 'Sin dato' : $escape($data['kpis']['cumplimiento']) . '%' ?></strong></div>
    <div class="kpi"><small>Participación</small><strong><?= $data['kpis']['participacion'] === null ? 'Sin dato' : $escape($data['kpis']['participacion']) . '%' ?></strong></div>
  </div>
  <h2>Proyectos</h2>
  <table><thead><tr><th>Proyecto</th><th>Cliente</th><th>Responsable</th><th>Estado</th><th>NPS</th><th>Avance percibido</th><th>Cumplimiento</th><th>Respuestas</th></tr></thead><tbody>
    <?php foreach ($data['projects'] as $project): ?>
      <tr><td><?= $escape($project['nombre']) ?></td><td><?= $escape($project['cliente']) ?></td><td><?= $escape($project['responsables']) ?></td><td><?= $escape($project['estado']) ?></td><td><?= $project['nps'] === null ? '—' : (int) $project['nps'] ?></td><td><?= $project['avance_percibido'] === null ? '—' : $escape($project['avance_percibido']) . '%' ?></td><td><?= $project['cumplimiento_medicion'] === null ? '—' : $escape($project['cumplimiento_medicion']) . '%' ?></td><td><?= (int) $project['respuestas'] ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <h2>Señales de riesgo</h2>
  <table><thead><tr><th>Proyecto</th><th>Cliente</th><th>Seguimiento</th></tr></thead><tbody>
    <?php foreach ($data['projects'] as $project): ?>
      <tr><td><?= $escape($project['nombre']) ?></td><td><?= $escape($project['cliente']) ?></td><td class="<?= $project['riesgo'] ? 'risk' : 'stable' ?>"><?= $project['riesgo'] ? 'Revisar' : 'Estable' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <script>window.addEventListener('load',()=>window.print());</script>
</body>
</html>

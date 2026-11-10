<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
$user = current_user();
$csrf = csrf_token();
$userJson = json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#132c3b">
  <title>SGEP Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<div id="loginScreen" class="login-shell <?= $user ? 'd-none' : '' ?>">
  <div class="login-card">
    <div class="brand-mark">SG</div>
    <p class="eyebrow">Gestión y evaluación</p>
    <h1>Bienvenido a SGEP</h1>
    <p class="text-secondary">Ingresá para gestionar proyectos, encuestas y resultados.</p>
    <form id="loginForm" class="mt-4">
      <label class="form-label" for="loginEmail">Correo electrónico</label>
      <input id="loginEmail" class="form-control form-control-lg mb-3" type="email" autocomplete="username" required>
      <label class="form-label" for="loginPassword">Contraseña</label>
      <input id="loginPassword" class="form-control form-control-lg" type="password" autocomplete="current-password" required>
      <div id="loginError" class="alert alert-danger mt-3 d-none"></div>
      <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">Iniciar sesión</button>
    </form>
    <form id="signupForm" class="mt-4 d-none">
      <label class="form-label" for="signupName">Nombre completo</label>
      <input id="signupName" class="form-control form-control-lg mb-3" maxlength="140" required>
      <label class="form-label" for="signupEmail">Correo electrónico</label>
      <input id="signupEmail" class="form-control form-control-lg mb-3" type="email" required>
      <label class="form-label" for="signupPassword">Contraseña</label>
      <input id="signupPassword" class="form-control form-control-lg" type="password" minlength="8" required>
      <div id="signupError" class="alert alert-danger mt-3 d-none"></div>
      <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">Solicitar cuenta</button>
    </form>
    <div class="text-center mt-3"><button type="button" id="showSignup" class="btn btn-link">Solicitar una cuenta de auditor</button><button type="button" id="showLogin" class="btn btn-link d-none">Volver al inicio de sesión</button></div>
    <p class="login-note">Las encuestas publicadas pueden responderse desde su enlace, sin crear una cuenta.</p>
  </div>
</div>

<div id="appShell" class="<?= $user ? '' : 'd-none' ?>">
  <header class="topbar">
    <button class="mobile-menu btn btn-sm btn-outline-light" id="menuToggle" aria-label="Abrir menú">☰</button>
    <a class="brand" href="#dashboard"><span class="brand-mark small">SG</span><span>SGEP <small>Dashboard</small></span></a>
    <div class="topbar-right"><span class="user-chip"><span class="user-avatar" id="userInitials"></span><span><strong id="userName"></strong><small id="userRole"></small></span></span><button id="logoutButton" class="btn btn-sm btn-outline-light">Salir</button></div>
  </header>
  <div class="app-layout">
    <aside id="sidebar" class="sidebar">
      <div class="sidebar-label">Espacio de trabajo</div>
      <button class="nav-item active" data-view="dashboard"><span>▦</span> Dashboard</button>
      <button class="nav-item" data-view="projects"><span>▧</span> Proyectos</button>
      <button class="nav-item" data-view="surveys"><span>☷</span> Encuestas</button>
      <button class="nav-item" data-view="results"><span>◉</span> Resultados</button>
      <button class="nav-item admin-only d-none" data-view="users"><span>♙</span> Usuarios</button>
      <div class="sidebar-bottom"><div class="help-card"><strong>SGEP</strong><p>Seguimiento de proyectos con datos centralizados.</p></div><small>Evaluación de proyectos</small></div>
    </aside>
    <main class="main-content">
      <div id="notice" class="alert d-none" role="alert"></div>
      <section class="view-section" id="view-dashboard">
        <div class="page-heading"><div><p class="eyebrow">Resumen general</p><h1>Dashboard</h1><p class="text-secondary">Indicadores y señales para seguir el pulso de tus proyectos.</p></div><div class="d-flex gap-2"><button class="btn btn-outline-primary" id="exportDashboardCsv">Excel / CSV</button><button class="btn btn-outline-primary" id="exportDashboardPdf">PDF</button><button class="btn btn-outline-primary" id="refreshDashboard">Actualizar</button></div></div>
        <div class="filter-panel">
          <div class="filter-title"><div><strong>Filtrar resultados</strong><span>Los indicadores se actualizan con cada filtro.</span></div><button id="clearFilters" class="btn btn-sm btn-link">Limpiar filtros</button></div>
          <div class="row g-2 align-items-end" id="dashboardFilters">
            <div class="col-12 col-md-4 col-xl"><label class="form-label">Proyecto</label><select class="form-select" name="proyecto_id"><option value="">Todos los proyectos</option></select></div>
            <div class="col-6 col-md-4 col-xl"><label class="form-label">Desde</label><input class="form-control" type="date" name="desde"></div>
            <div class="col-6 col-md-4 col-xl"><label class="form-label">Hasta</label><input class="form-control" type="date" name="hasta"></div>
            <div class="col-12 col-md-4 col-xl"><label class="form-label">Cliente</label><input class="form-control" type="search" name="cliente" placeholder="Buscar cliente"></div>
            <div class="col-12 col-md-4 col-xl"><label class="form-label">Responsable</label><select class="form-select" name="responsable_id"><option value="">Todos</option></select></div>
            <div class="col-12 col-md-4 col-xl"><label class="form-label">Tipo</label><select class="form-select" name="tipo"><option value="">Todos</option><option value="satisfaccion">Satisfacción</option><option value="avance">Avance</option><option value="cumplimiento">Cumplimiento</option><option value="lecciones">Lecciones</option><option value="general">General</option></select></div>
          </div>
        </div>
        <div class="row g-3 kpi-row">
          <div class="col-6 col-xl-3"><div class="kpi-card"><span class="kpi-icon purple">↗</span><span class="kpi-label">NPS</span><strong id="kpiNps">—</strong><small>de −100 a 100</small></div></div>
          <div class="col-6 col-xl-3"><div class="kpi-card"><span class="kpi-icon blue">◌</span><span class="kpi-label">Avance percibido</span><strong id="kpiProgress">—</strong><small>promedio de respuestas</small></div></div>
          <div class="col-6 col-xl-3"><div class="kpi-card"><span class="kpi-icon green">✓</span><span class="kpi-label">Cumplimiento</span><strong id="kpiCompliance">—</strong><small>hitos reportados</small></div></div>
          <div class="col-6 col-xl-3"><div class="kpi-card"><span class="kpi-icon orange">◎</span><span class="kpi-label">Participación</span><strong id="kpiParticipation">—</strong><small id="participationNote">respuestas de invitaciones</small></div></div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-12 col-xl-8"><div class="panel-card chart-card"><div class="panel-heading"><div><h2>Respuestas en el tiempo</h2><p>Participaciones registradas por mes</p></div><span class="live-dot">Datos MySQL</span></div><div class="chart-wrap"><canvas id="trendChart"></canvas></div></div></div>
          <div class="col-12 col-xl-4"><div class="panel-card chart-card"><div class="panel-heading"><div><h2>Recomendación</h2><p>Distribución NPS</p></div></div><div class="chart-wrap donut-wrap"><canvas id="npsChart"></canvas></div><div id="npsLegend" class="chart-legend"></div></div></div>
          <div class="col-12 col-xl-8"><div class="panel-card chart-card"><div class="panel-heading"><div><h2>Comparativo por proyecto</h2><p>Avance percibido y cumplimiento</p></div></div><div class="chart-wrap"><canvas id="projectChart"></canvas></div></div></div>
          <div class="col-12 col-xl-4"><div class="panel-card"><div class="panel-heading"><div><h2>Semáforo de proyectos</h2><p>Señales de seguimiento</p></div></div><div id="riskList" class="risk-list"></div></div></div>
          <div class="col-12"><div class="panel-card"><div class="panel-heading"><div><h2>Mapa de calor</h2><p>Indicadores por proyecto</p></div></div><div class="table-responsive"><table class="table heatmap-table"><thead><tr><th>Proyecto</th><th>Estado</th><th>NPS</th><th>Avance percibido</th><th>Cumplimiento</th><th>Respuestas</th></tr></thead><tbody id="heatmapRows"></tbody></table></div></div></div>
        </div>
      </section>

      <section class="view-section d-none" id="view-projects">
        <div class="page-heading"><div><p class="eyebrow">Cartera</p><h1>Proyectos</h1><p class="text-secondary">Responsables, estado y encuestas vinculadas.</p></div><button id="newProject" class="btn btn-primary write-only">+ Crear proyecto</button></div>
        <div class="panel-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Proyecto</th><th>Cliente</th><th>Responsable</th><th>Estado</th><th>Avance</th><th>Encuestas</th><th></th></tr></thead><tbody id="projectRows"></tbody></table></div><div id="projectEmpty" class="empty-state d-none">Todavía no hay proyectos para mostrar.</div></div>
      </section>

      <section class="view-section d-none" id="view-surveys">
        <div class="page-heading"><div><p class="eyebrow">Recolección</p><h1>Encuestas</h1><p class="text-secondary">Diseñá cuestionarios, compartilos y controlá sus respuestas.</p></div><button id="newSurvey" class="btn btn-primary write-only">+ Crear encuesta</button></div>
        <div class="panel-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Encuesta</th><th>Proyecto</th><th>Tipo</th><th>Estado</th><th>Respuestas</th><th>Enlace</th><th></th></tr></thead><tbody id="surveyRows"></tbody></table></div><div id="surveyEmpty" class="empty-state d-none">Todavía no hay encuestas. Creá una desde una plantilla o desde cero.</div></div>
      </section>

      <section class="view-section d-none" id="view-results">
        <div class="page-heading"><div><p class="eyebrow">Análisis</p><h1>Resultados</h1><p class="text-secondary">Respuestas agrupadas por pregunta y exportación.</p></div></div>
        <div class="filter-panel"><div class="row g-2 align-items-end"><div class="col-12 col-md-6"><label class="form-label">Encuesta</label><select id="resultSurvey" class="form-select"><option value="">Seleccioná una encuesta</option></select></div><div class="col-6 col-md-3"><label class="form-label">Desde</label><input id="resultFrom" type="date" class="form-control"></div><div class="col-6 col-md-3"><label class="form-label">Hasta</label><input id="resultTo" type="date" class="form-control"></div></div></div>
        <div id="resultsContent"><div class="empty-state">Elegí una encuesta para consultar las respuestas.</div></div>
      </section>

      <section class="view-section d-none" id="view-users">
        <div class="page-heading"><div><p class="eyebrow">Administración</p><h1>Usuarios</h1><p class="text-secondary">Cuentas y permisos del sistema.</p></div><button id="newUser" class="btn btn-primary">+ Crear usuario</button></div>
        <div class="panel-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Alta</th><th></th></tr></thead><tbody id="userRows"></tbody></table></div></div>
      </section>
    </main>
  </div>
</div>
<div class="modal fade" id="appModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content" id="modalContent"></div></div></div>
<script>
window.SGEP = {csrf: <?= json_encode($csrf) ?>, user: <?= $userJson ?: 'null' ?>};
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="assets/app.js" defer></script>
</body>
</html>

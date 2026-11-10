const state = {user: window.SGEP.user || null, csrf: window.SGEP.csrf || '', projects: [], surveys: [], users: [], templates: [], charts: {}, modal: null, questions: [], selectedTemplate: 0, resultData: null};
const roles = {admin: 'Administrador', pm: 'Responsable de proyecto', auditor: 'Auditor'};
const qtypes = {texto: 'Texto corto', parrafo: 'Texto largo', unica: 'Opción única', multiple: 'Varias opciones', escala: 'Escala numérica', nps: 'NPS 0–10', fecha: 'Fecha', si_no: 'Sí / No'};
const metrics = {ninguna: 'Sin métrica', nps: 'NPS', avance: 'Avance percibido', cumplimiento: 'Cumplimiento', participacion: 'Participación'};
const writable = () => state.user && ['admin', 'pm'].includes(state.user.rol);
const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pct = v => v == null ? '—' : Math.round(Number(v)) + '%';
const statusName = v => ({planificacion:'Planificación',en_curso:'En curso',riesgo:'En riesgo',completado:'Completado',pausado:'Pausado',borrador:'Borrador',publicada:'Publicada',cerrada:'Cerrada',satisfaccion:'Satisfacción',avance:'Avance',cumplimiento:'Cumplimiento',lecciones:'Lecciones',general:'General'}[v] || v);
const dateLabel = v => v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('es-AR') : '—';

async function api(action, options = {}) {
  const method = options.method || 'GET';
  const url = new URL('api.php', location.href);
  url.searchParams.set('action', action);
  Object.entries(options.query || {}).forEach(entry => {if (entry[1] !== '' && entry[1] != null) url.searchParams.set(entry[0], entry[1]);});
  const headers = {Accept:'application/json'};
  const init = {method:method, credentials:'same-origin', headers:headers};
  if (method !== 'GET') {headers['Content-Type'] = 'application/json'; headers['X-CSRF-Token'] = state.csrf; init.body = JSON.stringify(options.data || {});}
  const response = await fetch(url, init);
  if (action === 'auth.login' && response.ok) {
    const result = await response.json(); state.csrf = result.csrf; state.user = result.user; window.SGEP.csrf = state.csrf; window.SGEP.user = state.user; return result;
  }
  if (!response.ok) {const result = await response.json().catch(() => ({})); throw new Error(result.error || 'No se pudo completar la operación.');}
  return response.json();
}

function notice(message, kind) {
  const box = document.getElementById('notice'); box.textContent = message;
  box.className = 'alert alert-' + (kind === 'error' ? 'danger' : kind === 'warning' ? 'warning' : 'success');
  setTimeout(() => box.classList.add('d-none'), 5000);
}

function modal(title, body) {
  const node = document.getElementById('modalContent');
  node.innerHTML = '<div class="modal-header"><h2 class="modal-title">' + esc(title) + '</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">' + body + '</div>';
  if (!state.modal) {
    if (window.bootstrap) {
      state.modal = new bootstrap.Modal(document.getElementById('appModal'));
    } else {
      state.modal = {
        show: () => {
          const modalNode = document.getElementById('appModal');
          modalNode.classList.add('show');
          modalNode.style.display = 'block';
          modalNode.setAttribute('aria-modal', 'true');
          modalNode.removeAttribute('aria-hidden');
          document.body.classList.add('modal-open');
        },
        hide: () => {
          const modalNode = document.getElementById('appModal');
          modalNode.classList.remove('show');
          modalNode.style.display = 'none';
          modalNode.removeAttribute('aria-modal');
          modalNode.setAttribute('aria-hidden', 'true');
          document.body.classList.remove('modal-open');
        }
      };
    }
  }
  state.modal.show(); return node;
}

function setView(name) {
  document.querySelectorAll('.view-section').forEach(node => node.classList.toggle('d-none', node.id !== 'view-' + name));
  document.querySelectorAll('.nav-item').forEach(node => node.classList.toggle('active', node.dataset.view === name));
  document.getElementById('sidebar').classList.remove('open'); history.replaceState(null, '', '#' + name);
  if (name === 'dashboard') loadDashboard();
  if (name === 'projects') loadProjects();
  if (name === 'surveys') loadSurveys();
  if (name === 'results') loadResultChoices();
  if (name === 'users' && state.user.rol === 'admin') loadUsers();
}

function initUser() {
  if (!state.user) return;
  document.getElementById('loginScreen').classList.add('d-none');
  document.getElementById('appShell').classList.remove('d-none');
  document.getElementById('userName').textContent = state.user.nombre;
  document.getElementById('userRole').textContent = roles[state.user.rol];
  document.getElementById('userInitials').textContent = state.user.nombre.split(/\s+/).slice(0,2).map(part => part[0]).join('').toUpperCase();
  document.querySelectorAll('.admin-only').forEach(node => node.classList.toggle('d-none', state.user.rol !== 'admin'));
  document.querySelectorAll('.write-only').forEach(node => node.classList.toggle('d-none', !writable()));
  const requested = location.hash.slice(1);
  setView(['dashboard','projects','surveys','results','users'].includes(requested) && (requested !== 'users' || state.user.rol === 'admin') ? requested : 'dashboard');
}

function badge(status) {return '<span class="status-badge status-' + esc(status) + '">' + esc(statusName(status)) + '</span>';}

async function loadProjects() {
  try {
    state.projects = await api('projects.list');
    document.getElementById('projectEmpty').classList.toggle('d-none', state.projects.length > 0);
    document.getElementById('projectRows').innerHTML = state.projects.map(project =>
      '<tr><td><strong>' + esc(project.nombre) + '</strong><div class="text-secondary small">' + esc(project.descripcion || '') + '</div></td><td>' + esc(project.cliente) + '</td><td>' + esc(project.responsables || 'Sin asignar') + '</td><td>' + badge(project.estado) + '</td><td>' + pct(project.avance) + '</td><td>' + project.encuestas + '</td><td class="text-end text-nowrap"><button class="icon-button" data-project-view="' + project.id + '">Ver</button>' + (writable() ? '<button class="icon-button" data-project-edit="' + project.id + '">Editar</button>' : '') + (state.user.rol === 'admin' ? '<button class="icon-button danger" data-project-delete="' + project.id + '">Eliminar</button>' : '') + '</td></tr>'
    ).join('');
  } catch (error) {notice(error.message, 'error');}
}

async function loadSurveys() {
  try {
    state.surveys = await api('surveys.list');
    document.getElementById('surveyEmpty').classList.toggle('d-none', state.surveys.length > 0);
    document.getElementById('surveyRows').innerHTML = state.surveys.map(survey => {
      const url = new URL('survey.php?t=' + encodeURIComponent(survey.token), location.href).href;
      return '<tr><td><strong>' + esc(survey.titulo) + '</strong><div class="text-secondary small">' + esc(survey.descripcion || '') + '</div></td><td>' + esc(survey.proyecto) + '</td><td>' + esc(statusName(survey.tipo)) + '</td><td>' + badge(survey.estado) + '</td><td>' + survey.respuestas + '</td><td><a class="copy-link" href="' + esc(url) + '" target="_blank">' + esc(url) + '</a></td><td class="text-end text-nowrap">' + (writable() ? (survey.estado === 'publicada' ? '<button class="icon-button" data-survey-distribution="' + survey.id + '">Distribuir</button>' : '<span class="text-secondary small">Publicá para distribuir</span>') + '<button class="icon-button" data-survey-edit="' + survey.id + '">Editar</button>' : '') + '<button class="icon-button" data-survey-results="' + survey.id + '">Resultados</button>' + (writable() ? '<button class="icon-button" data-survey-state="' + survey.id + '" data-next="' + (survey.estado === 'publicada' ? 'cerrada' : 'publicada') + '">' + (survey.estado === 'publicada' ? 'Cerrar' : survey.estado === 'cerrada' ? 'Reabrir' : 'Publicar') + '</button>' : '') + (state.user.rol === 'admin' ? '<button class="icon-button danger" data-survey-delete="' + survey.id + '">Eliminar</button>' : '') + '</td></tr>';
    }).join('');
  } catch (error) {notice(error.message, 'error');}
}

function metricClass(value, nps) {
  if (value == null) return 'heat-empty';
  return nps ? Number(value) >= 30 ? 'heat-good' : Number(value) >= 0 ? 'heat-mid' : 'heat-low' : Number(value) >= 75 ? 'heat-good' : Number(value) >= 55 ? 'heat-mid' : 'heat-low';
}
function heat(value, nps) {return value == null ? '<span class="heat-cell heat-empty">Sin dato</span>' : '<span class="heat-cell ' + metricClass(value, nps) + '">' + (nps ? esc(value) : pct(value)) + '</span>';}

async function loadDashboard() {
  if (!state.user) return;
  const filters = Object.fromEntries(new FormData(document.getElementById('dashboardFilters')).entries());
  try {
    const data = await api('dashboard.data', {query:filters});
    fillDashboardFilters(data.filters);
    document.getElementById('kpiNps').textContent = data.kpis.nps == null ? '—' : data.kpis.nps;
    document.getElementById('kpiProgress').textContent = pct(data.kpis.avance);
    document.getElementById('kpiCompliance').textContent = pct(data.kpis.cumplimiento);
    document.getElementById('kpiParticipation').textContent = pct(data.kpis.participacion);
    document.getElementById('participationNote').textContent = data.kpis.invitaciones ? data.kpis.respuestas + ' respuestas · ' + data.kpis.invitaciones + ' invitaciones' : data.kpis.respuestas + ' respuestas registradas';
    renderDashboardCharts(data); renderRisk(data.projects); renderHeatmap(data.projects);
  } catch (error) {notice(error.message, 'error');}
}

function fillDashboardFilters(filters) {
  const project = document.querySelector('#dashboardFilters [name="proyecto_id"]');
  const prevProject = project.value;
  project.innerHTML = '<option value="">Todos los proyectos</option>' + filters.projects.map(item => '<option value="' + item.id + '">' + esc(item.nombre) + '</option>').join('');
  if (filters.projects.some(item => String(item.id) === prevProject)) project.value = prevProject;
  const responsible = document.querySelector('#dashboardFilters [name="responsable_id"]');
  const prevResponsible = responsible.value;
  responsible.innerHTML = '<option value="">Todos</option>' + filters.responsibles.map(item => '<option value="' + item.id + '">' + esc(item.nombre) + '</option>').join('');
  if (filters.responsibles.some(item => String(item.id) === prevResponsible)) responsible.value = prevResponsible;
}

function renderDashboardCharts(data) {
  if (typeof Chart === 'undefined') return;
  Object.values(state.charts).forEach(chart => chart.destroy());
  state.charts = {};
  const common = {responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{grid:{display:false},ticks:{color:'#819198',font:{size:10}}},y:{beginAtZero:true,grid:{color:'#edf1f2'},ticks:{color:'#819198',font:{size:10}}}}};
  state.charts.trend = new Chart(document.getElementById('trendChart'), {type:'line',data:{labels:data.trend.labels,datasets:[{data:data.trend.values,fill:true,borderColor:'#277c7d',backgroundColor:'rgba(61,155,145,.12)',pointBackgroundColor:'#277c7d',pointRadius:3,tension:.35}]},options:common});
  const dist = data.nps_distribution;
  state.charts.nps = new Chart(document.getElementById('npsChart'), {type:'doughnut',data:{labels:Object.keys(dist),datasets:[{data:Object.values(dist),backgroundColor:['#e37b70','#ecc674','#65ad87'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{display:false}}}});
  document.getElementById('npsLegend').innerHTML = Object.entries(dist).map((item,index) => '<span class="legend-item"><span class="legend-dot" style="background:' + ['#e37b70','#ecc674','#65ad87'][index] + '"></span>' + esc(item[0]) + ' ' + item[1] + '</span>').join('');
  const projects = data.project_chart;
  state.charts.projects = new Chart(document.getElementById('projectChart'), {type:'bar',data:{labels:projects.map(item => item.proyecto),datasets:[{label:'Avance',data:projects.map(item => item.avance),backgroundColor:'#70b5a5',borderRadius:5},{label:'Cumplimiento',data:projects.map(item => item.cumplimiento),backgroundColor:'#6d8db7',borderRadius:5}]},options:{...common,plugins:{legend:{display:true,position:'bottom',labels:{boxWidth:8,usePointStyle:true,font:{size:10}}}},scales:{...common.scales,y:{...common.scales.y,max:100}}}});
}

function renderRisk(projects) {
  const node = document.getElementById('riskList');
  node.innerHTML = projects.length ? projects.map(project => {
    const level = project.estado === 'riesgo' || (project.nps != null && project.nps < -10) ? 'red' : project.riesgo ? 'amber' : 'green';
    const note = level === 'red' ? 'Requiere atención prioritaria' : level === 'amber' ? 'Revisar próximos hitos' : 'Seguimiento estable';
    return '<div class="risk-item"><span class="risk-light ' + level + '"></span><div><strong>' + esc(project.nombre) + '</strong><small>' + note + ' · ' + project.respuestas + ' respuestas</small></div></div>';
  }).join('') : '<div class="risk-empty">No hay proyectos para mostrar.</div>';
}

function renderHeatmap(projects) {
  document.getElementById('heatmapRows').innerHTML = projects.length ? projects.map(project => '<tr><td><strong>' + esc(project.nombre) + '</strong><div class="text-secondary small">' + esc(project.cliente) + '</div></td><td>' + badge(project.estado) + '</td><td>' + heat(project.nps, true) + '</td><td>' + heat(project.avance_percibido, false) + '</td><td>' + heat(project.cumplimiento_medicion, false) + '</td><td>' + project.respuestas + '</td></tr>').join('') : '<tr><td colspan="6" class="empty-state">No hay resultados para los filtros seleccionados.</td></tr>';
}

function projectForm(project) {
  project = project || {};
  const selected = String(project.responsable_ids || '').split(',');
  const people = state.users.filter(user => user.rol === 'pm' && Number(user.activo)).map(person => '<option value="' + person.id + '"' + (selected.includes(String(person.id)) ? ' selected' : '') + '>' + esc(person.nombre) + '</option>').join('');
  return '<form id="projectForm"><input type="hidden" name="id" value="' + esc(project.id || '') + '"><div class="row g-3"><div class="col-md-8"><label class="form-label">Nombre</label><input class="form-control" name="nombre" maxlength="180" value="' + esc(project.nombre || '') + '" required></div><div class="col-md-4"><label class="form-label">Cliente</label><input class="form-control" name="cliente" maxlength="180" value="' + esc(project.cliente || '') + '" required></div><div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3">' + esc(project.descripcion || '') + '</textarea></div><div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado">' + ['planificacion','en_curso','riesgo','completado','pausado'].map(status => '<option value="' + status + '"' + (project.estado === status ? ' selected' : '') + '>' + statusName(status) + '</option>').join('') + '</select></div><div class="col-md-4"><label class="form-label">Inicio</label><input class="form-control" type="date" name="fecha_inicio" value="' + esc(project.fecha_inicio || '') + '"></div><div class="col-md-4"><label class="form-label">Cierre</label><input class="form-control" type="date" name="fecha_fin" value="' + esc(project.fecha_fin || '') + '"></div><div class="col-md-6"><label class="form-label">Avance (%)</label><input class="form-control" type="number" min="0" max="100" name="avance" value="' + esc(project.avance || 0) + '"></div><div class="col-md-6"><label class="form-label">Cumplimiento (%)</label><input class="form-control" type="number" min="0" max="100" name="cumplimiento" value="' + esc(project.cumplimiento || 0) + '"></div>' + (state.user.rol === 'admin' ? '<div class="col-12"><label class="form-label">Responsables</label><select class="form-select" name="responsables" multiple size="4">' + people + '</select><div class="form-text">Se pueden asignar varios responsables PM.</div></div>' : '') + '</div><div id="formError" class="alert alert-danger d-none mt-3"></div><div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar proyecto</button></div></form>';
}

async function openProject(project) {
  if (state.user.rol === 'admin') await loadUsers(true);
  const box = modal(project ? 'Editar proyecto' : 'Crear proyecto', projectForm(project));
  box.querySelector('#projectForm').addEventListener('submit', async event => {
    event.preventDefault(); const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form).entries());
    data.responsables = form.elements.responsables ? [...form.elements.responsables.selectedOptions].map(option => Number(option.value)) : [];
    try {await api('projects.save',{method:'POST',data:data});state.modal.hide();notice('Proyecto guardado.');await Promise.all([loadProjects(),loadDashboard()]);}
    catch(error){const holder=form.querySelector('#formError');holder.textContent=error.message;holder.classList.remove('d-none');}
  });
}

async function viewProject(id) {
  const result = await api('projects.get',{query:{id:id}});
  const project = result.project;
  const rows = result.surveys.map(survey => '<tr><td>' + esc(survey.titulo) + '</td><td>' + badge(survey.estado) + '</td><td>' + survey.respuestas + '</td><td><button class="btn btn-sm btn-outline-primary" data-project-result="' + survey.id + '">Resultados</button></td></tr>').join('');
  const box = modal(project.nombre,'<div class="row g-3 mb-3"><div class="col-sm-6"><span class="form-label">Cliente</span><div>' + esc(project.cliente) + '</div></div><div class="col-sm-6"><span class="form-label">Responsables</span><div>' + esc(project.responsables || 'Sin asignar') + '</div></div><div class="col-sm-6"><span class="form-label">Estado</span><div>' + badge(project.estado) + '</div></div><div class="col-sm-6"><span class="form-label">Fechas</span><div>' + dateLabel(project.fecha_inicio) + ' — ' + dateLabel(project.fecha_fin) + '</div></div><div class="col-12"><span class="form-label">Descripción</span><div>' + esc(project.descripcion || 'Sin descripción') + '</div></div></div><h3 class="modal-section-title mt-4">Encuestas del proyecto</h3><div class="table-responsive"><table class="table"><thead><tr><th>Encuesta</th><th>Estado</th><th>Respuestas</th><th></th></tr></thead><tbody>' + (rows || '<tr><td colspan="4" class="text-secondary">No hay encuestas asociadas todavía.</td></tr>') + '</tbody></table></div>');
  box.querySelectorAll('[data-project-result]').forEach(button => button.addEventListener('click',() => {state.modal.hide();setView('results');setTimeout(() => {document.getElementById('resultSurvey').value=button.dataset.projectResult;loadResults();},100);}));
}

function newQuestion(question) {
  return {key:question.key || String(question.id || 'new-' + Date.now() + '-' + Math.random().toString(16).slice(2,6)),id:question.id || null,texto:question.texto || '',tipo:question.tipo || 'texto',obligatoria:Number(question.obligatoria) === 1 || question.obligatoria === true,opciones:Array.isArray(question.opciones) ? question.opciones : [],metrica:question.metrica || 'ninguna',condicion_pregunta_id:question.condicion_pregunta_id ? String(question.condicion_pregunta_id) : '',condicion_valor:question.condicion_valor || ''};
}

async function openSurvey(id) {
  if (!state.projects.length) await loadProjects();
  state.templates = await api('surveys.templates');
  let survey = {titulo:'',descripcion:'',tipo:'general',proyecto_id:'',anonima:1,publica:1,preguntas:[]};
  if (id) survey = (await api('surveys.get',{query:{id:id}})).survey;
  state.selectedTemplate = Number(survey.plantilla_id || 0);
  state.questions = survey.preguntas.map(newQuestion); if (!state.questions.length) state.questions = [newQuestion({})];
  const projectOptions = state.projects.map(project => '<option value="' + project.id + '">' + esc(project.nombre) + ' · ' + esc(project.cliente) + '</option>').join('');
  const templateOptions = state.templates.map(template => '<option value="' + template.id + '"' + (state.selectedTemplate === Number(template.id) ? ' selected' : '') + '>' + esc(template.nombre) + '</option>').join('');
  const surveyTypes = ['general','satisfaccion','avance','cumplimiento','lecciones'].map(type => '<option value="' + type + '"' + (survey.tipo === type ? ' selected' : '') + '>' + statusName(type) + '</option>').join('');
  const box = modal(id ? 'Editar encuesta' : 'Nueva encuesta','<form id="surveyForm"><div class="row g-3"><div class="col-md-7"><label class="form-label">Título</label><input name="titulo" class="form-control" maxlength="180" value="' + esc(survey.titulo) + '" required></div><div class="col-md-5"><label class="form-label">Proyecto</label><select name="proyecto_id" class="form-select" required><option value="">Elegir proyecto</option>' + projectOptions + '</select></div><div class="col-md-7"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="2">' + esc(survey.descripcion || '') + '</textarea></div><div class="col-md-5"><label class="form-label">Tipo</label><select name="tipo" class="form-select">' + surveyTypes + '</select></div><div class="col-12"><label class="form-label">Plantilla reutilizable</label><div class="input-group"><select id="templateChoice" class="form-select"><option value="">Sin plantilla / conservar preguntas actuales</option>' + templateOptions + '</select><button type="button" id="applyTemplate" class="btn btn-outline-primary">Cargar plantilla</button></div></div><div class="col-12 d-flex gap-4 flex-wrap"><label class="form-check"><input class="form-check-input" type="checkbox" name="anonima" ' + (Number(survey.anonima) ? 'checked' : '') + '><span class="form-check-label">Respuestas anónimas</span></label><label class="form-check"><input class="form-check-input" type="checkbox" name="publica" ' + (Number(survey.publica) ? 'checked' : '') + '><span class="form-check-label">Acceso público</span></label></div></div><div class="builder-toolbar"><div><strong>Preguntas</strong><span class="d-block">Arrastrá las preguntas para ordenar.</span></div><button type="button" id="addQuestion" class="btn btn-sm btn-outline-primary">+ Agregar pregunta</button></div><div id="questionEditor" class="question-editor"></div><div id="formError" class="alert alert-danger d-none mt-3"></div><div class="d-flex justify-content-between gap-2 mt-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">' + (id ? 'Guardar cambios' : 'Crear borrador') + '</button></div></form>');
  box.querySelector('[name="proyecto_id"]').value = survey.proyecto_id || '';
  box.querySelector('#applyTemplate').addEventListener('click', () => {
    const selected = state.templates.find(template => Number(template.id) === Number(box.querySelector('#templateChoice').value));
    if (!selected) return;
    let templateQuestions = selected.preguntas;
    if (typeof templateQuestions === 'string') {
      try {templateQuestions = JSON.parse(templateQuestions);} catch (error) {templateQuestions = [];}
    }
    state.selectedTemplate = Number(selected.id);
    state.questions = (templateQuestions || []).map(question => newQuestion(question));
    if (!state.questions.length) state.questions = [newQuestion({})];
    if (['general','satisfaccion','avance','cumplimiento','lecciones'].includes(selected.tipo)) box.querySelector('[name="tipo"]').value = selected.tipo;
    renderQuestions(box);
    notice('Plantilla cargada. Guardá la encuesta para almacenar las preguntas.');
  });
  box.querySelector('#templateChoice').addEventListener('change', event => {if (!event.target.value) state.selectedTemplate = 0;});
  box.querySelector('#addQuestion').addEventListener('click',() => {state.questions.push(newQuestion({}));renderQuestions(box);});
  renderQuestions(box);
  box.querySelector('#surveyForm').addEventListener('submit',async event => {
    event.preventDefault();const form=event.currentTarget;
    const questions=readQuestions(box);
    const data={id:id || 0,plantilla_id:state.selectedTemplate,titulo:form.elements.titulo.value,proyecto_id:Number(form.elements.proyecto_id.value),descripcion:form.elements.descripcion.value,tipo:form.elements.tipo.value,anonima:form.elements.anonima.checked,publica:form.elements.publica.checked,preguntas:questions};
    try {await api('surveys.save',{method:'POST',data:data});state.modal.hide();notice('Encuesta guardada como borrador. Publicala para distribuirla.');await Promise.all([loadSurveys(),loadDashboard()]);}
    catch(error){const holder=form.querySelector('#formError');holder.textContent=error.message;holder.classList.remove('d-none');}
  });
}

function renderQuestions(box) {
  const container=box.querySelector('#questionEditor');
  container.innerHTML=state.questions.map((question,index) => {
    const typeOptions=Object.entries(qtypes).map(item=>'<option value="'+item[0]+'"'+(item[0]===question.tipo?' selected':'')+'>'+item[1]+'</option>').join('');
    const showOptions=['unica','multiple','escala','si_no'].includes(question.tipo);
    const options=question.tipo==='si_no'?'Sí\nNo':question.tipo==='escala'?(question.opciones.join('\n') || '0\n100'):question.opciones.join('\n');
    const parents=state.questions.slice(0,index).map(parent=>'<option value="'+esc(parent.key)+'"'+(question.condicion_pregunta_id===parent.key || question.condicion_pregunta_id===String(parent.id)?' selected':'')+'>'+esc(parent.texto || 'Pregunta sin texto').slice(0,55)+'</option>').join('');
    const selectedParent=state.questions.find(parent=>parent.key===question.condicion_pregunta_id || String(parent.id)===question.condicion_pregunta_id);
    const conditionValues=selectedParent?(selectedParent.tipo==='nps'?Array.from({length:11},(_,value)=>String(value)):selectedParent.tipo==='si_no'?['Sí','No']:selectedParent.opciones):[];
    const parentOptions=conditionValues.map(option=>'<option value="'+esc(option)+'"'+(question.condicion_valor===option?' selected':'')+'>'+esc(option)+'</option>').join('');
    const conditionInput=conditionValues.length?'<select class="form-select" data-field="condicion_valor"><option value="">Respuesta que habilita</option>'+parentOptions+'</select>':'<input class="form-control" data-field="condicion_valor" placeholder="Valor exacto que habilita" value="'+esc(question.condicion_valor)+'">';
    const condition=parents?'<div class="condition-fields"><select class="form-select" data-field="condicion_pregunta_id"><option value="">Mostrar siempre</option>'+parents+'</select>'+conditionInput+'</div>':'';
    const metricOptions=Object.entries(metrics).map(item=>'<option value="'+item[0]+'"'+(item[0]===question.metrica?' selected':'')+'>'+esc(item[1])+'</option>').join('');
    return '<article class="question-card" draggable="true" data-index="'+index+'"><div class="question-top"><span class="drag-handle">⠿</span><div class="question-fields"><input class="form-control" data-field="texto" placeholder="Escribí la pregunta" value="'+esc(question.texto)+'" required><select class="form-select" data-field="tipo">'+typeOptions+'</select>'+(showOptions?'<textarea class="form-control question-options" data-field="opciones" rows="2" placeholder="Una opción por línea">'+esc(options)+'</textarea>':'')+'</div><button type="button" class="icon-button danger" data-remove="'+index+'">×</button></div><div class="question-settings"><label class="form-check"><input class="form-check-input" type="checkbox" data-field="obligatoria" '+(question.obligatoria?'checked':'')+'><span class="form-check-label">Obligatoria</span></label><select class="form-select" style="width:auto" data-field="metrica">'+metricOptions+'</select>'+condition+'</div></article>';
  }).join('');
  container.querySelectorAll('[data-field]').forEach(control=>control.addEventListener(control.dataset.field==='texto'?'input':'change',()=>{
    const item=state.questions[Number(control.closest('.question-card').dataset.index)],field=control.dataset.field;
    if(field==='tipo'){item.tipo=control.value;if(control.value==='si_no')item.opciones=['Sí','No'];if(control.value==='escala'&&!item.opciones.length)item.opciones=['0','100'];renderQuestions(box);}
    else if(field==='texto')item.texto=control.value;
    else if(field==='obligatoria')item.obligatoria=control.checked;
    else if(field==='opciones')item.opciones=control.value.split(/\r?\n/).map(value=>value.trim()).filter(Boolean);
    else if(field==='condicion_pregunta_id'){item[field]=control.value;item.condicion_valor='';renderQuestions(box);}
    else item[field]=control.value;
  }));
  container.querySelectorAll('[data-remove]').forEach(button=>button.addEventListener('click',()=>{if(state.questions.length>1){state.questions.splice(Number(button.dataset.remove),1);renderQuestions(box);}}));
  let moving=null;
  container.querySelectorAll('.question-card').forEach(card=>{
    card.addEventListener('dragstart',()=>{moving=Number(card.dataset.index);card.classList.add('dragging');});
    card.addEventListener('dragend',()=>card.classList.remove('dragging'));
    card.addEventListener('dragover',event=>event.preventDefault());
    card.addEventListener('drop',event=>{event.preventDefault();const target=Number(card.dataset.index);if(moving!==null&&moving!==target){const item=state.questions.splice(moving,1)[0];state.questions.splice(target,0,item);moving=null;renderQuestions(box);}});
  });
}

function readQuestions(box) {
  return [...box.querySelectorAll('.question-card')].map((card,index)=>{
    const item=state.questions[Number(card.dataset.index)],type=card.querySelector('[data-field="tipo"]').value;
    const optionControl=card.querySelector('[data-field="opciones"]');
    let options=optionControl?optionControl.value.split(/\r?\n/).map(value=>value.trim()).filter(Boolean):item.opciones;
    if(type==='si_no')options=['Sí','No'];if(type==='escala'&&!options.length)options=['0','100'];
    return {id:item.id,key:item.key,texto:card.querySelector('[data-field="texto"]').value.trim(),tipo:type,obligatoria:card.querySelector('[data-field="obligatoria"]').checked,opciones:options,metrica:card.querySelector('[data-field="metrica"]').value,condicion_pregunta_id:card.querySelector('[data-field="condicion_pregunta_id"]')?.value || '',condicion_valor:card.querySelector('[data-field="condicion_valor"]')?.value || '',orden:index+1};
  });
}

function surveyLink(token) {return new URL('survey.php?t='+encodeURIComponent(token),location.href).href;}

async function openDistribution(survey) {
  if(!survey.token)survey=state.surveys.find(item=>Number(item.id)===Number(survey.id))||survey;
  const link=surveyLink(survey.token);
  const box=modal('Distribuir · '+survey.titulo,'<div class="distribution-info mb-3">Compartí el enlace o generá invitaciones por correo. Las encuestas anónimas no guardan la identidad de quien responde.</div><label class="form-label">Enlace</label><div class="input-group mb-3"><input id="surveyLink" class="form-control" readonly value="'+esc(link)+'"><button id="copyLink" class="btn btn-outline-primary">Copiar</button></div><div class="row g-3"><div class="col-md-6"><h3 class="modal-section-title">Código QR</h3><div class="qr-frame"><div id="qrCode"></div></div><div id="qrStatus" class="form-text mt-2 text-center"></div></div><div class="col-md-6"><h3 class="modal-section-title">Invitar por correo</h3><form id="inviteForm"><label class="form-label">Correos (separados por coma o línea)</label><textarea name="emails" class="form-control mb-2" rows="3"></textarea><label class="form-label">Asunto</label><input name="asunto" class="form-control mb-2" value="Invitación: '+esc(survey.titulo)+'"><label class="form-label">Mensaje</label><textarea name="mensaje" class="form-control mb-2" rows="2">Te invitamos a responder una encuesta breve sobre el proyecto.</textarea><button class="btn btn-primary w-100">Enviar invitaciones</button><label class="form-label mt-3">Enviar recordatorio el</label><input id="reminderWhen" type="datetime-local" class="form-control"><button type="button" id="remindButton" class="btn btn-outline-primary w-100 mt-2">Programar recordatorio</button></form></div></div><div id="distributionError" class="alert alert-danger d-none mt-3"></div>');
  box.querySelector('#copyLink').addEventListener('click',async()=>{try{await navigator.clipboard.writeText(link);box.querySelector('#copyLink').textContent='Copiado';}catch(error){box.querySelector('#surveyLink').select();}});
  if(typeof QRCode!=='undefined'){new QRCode(box.querySelector('#qrCode'),{text:link,width:170,height:170,colorDark:'#132c3b',colorLight:'#fff'});box.querySelector('#qrStatus').textContent='Este código abre la encuesta.';}else box.querySelector('#qrStatus').textContent='No se pudo cargar el generador QR; usá el enlace.';
  box.querySelector('#inviteForm').addEventListener('submit',async event=>{
    event.preventDefault();const form=event.currentTarget;const emails=form.emails.value.split(/[,\n;]+/).map(value=>value.trim()).filter(Boolean);
    try{const result=await api('surveys.invite',{method:'POST',data:{id:survey.id,emails:emails,asunto:form.asunto.value,mensaje:form.mensaje.value}});notice(result.invitaciones_creadas+' invitaciones; '+result.correos_enviados+' correos enviados.');}
    catch(error){const holder=box.querySelector('#distributionError');holder.textContent=error.message;holder.classList.remove('d-none');}
  });
  box.querySelector('#remindButton').addEventListener('click',async()=>{
    try{const result=await api('surveys.remind',{method:'POST',data:{id:survey.id,programado_para:box.querySelector('#reminderWhen').value}});notice(result.encolados+' recordatorios agregados a la cola local.');}
    catch(error){const holder=box.querySelector('#distributionError');holder.textContent=error.message;holder.classList.remove('d-none');}
  });
}

async function loadResultChoices() {
  if(!state.surveys.length)state.surveys=await api('surveys.list');
  const select=document.getElementById('resultSurvey'),previous=select.value;
  select.innerHTML='<option value="">Seleccioná una encuesta</option>'+state.surveys.map(item=>'<option value="'+item.id+'">'+esc(item.titulo)+' · '+esc(item.proyecto)+'</option>').join('');
  if(state.surveys.some(item=>String(item.id)===previous))select.value=previous;
  if(select.value)loadResults();
}

async function openResults(id) {
  await loadResultChoices();
  setView('results');
  document.getElementById('resultSurvey').value=String(id);
  await loadResults();
}

async function loadResults() {
  const id=Number(document.getElementById('resultSurvey').value);if(!id)return;
  try{state.resultData=await api('results.data',{query:{id:id,desde:document.getElementById('resultFrom').value,hasta:document.getElementById('resultTo').value}});renderResults(state.resultData);}
  catch(error){notice(error.message,'error');}
}

function renderResults(data) {
  const cards=data.preguntas.map((question,index)=>{
    const text=['texto','parrafo','fecha'].includes(question.tipo);
    const answers=text?question.respuestas.map(answer=>'<div class="answer-card">'+esc(Array.isArray(answer)?answer.join(', '):answer)+'</div>').join():'<div class="chart-wrap" style="height:220px"><canvas id="qChart'+index+'"></canvas></div><div class="answer-distribution">'+Object.entries(question.distribucion).map(entry=>'<div class="distribution-row"><span>'+esc(entry[0])+'</span><span class="distribution-track"><span class="distribution-bar" style="width:'+Math.round(entry[1]/Math.max(1,question.respuestas.length)*100)+'%"></span></span><strong>'+entry[1]+'</strong></div>').join('')+'</div>';
    return '<div class="panel-card"><div class="panel-heading"><div><h2>'+(index+1)+'. '+esc(question.texto)+'</h2><p>'+question.respuestas.length+' respuestas · '+esc(qtypes[question.tipo]||question.tipo)+'</p></div><span class="status-badge status-publicada">'+esc(metrics[question.metrica]||'')+'</span></div>'+answers+'</div>';
  }).join('');
  const rows=data.participaciones.map(submission=>'<tr><td>'+dateLabel(submission.created_at)+'</td><td>#'+submission.id+'</td><td>'+(submission.anonimo?'Sí':'No')+'</td><td>'+(submission.registrado?'Sí':'No')+'</td><td>'+Object.entries(submission.answers).map(entry=>'<div><strong>'+esc((data.preguntas.find(item=>String(item.id)===entry[0])||{}).texto||'Respuesta')+':</strong> '+esc(Array.isArray(entry[1])?entry[1].join(', '):entry[1])+'</div>').join('')+'</td></tr>').join('');
  const box=document.getElementById('resultsContent');
  box.innerHTML='<div class="row g-3 mb-3"><div class="col-sm-4"><div class="kpi-card"><span class="kpi-label">Respuestas</span><strong>'+data.total+'</strong><small>participaciones completas</small></div></div><div class="col-sm-4"><div class="kpi-card"><span class="kpi-label">Invitaciones completas</span><strong>'+data.invitaciones.completadas+' / '+data.invitaciones.total+'</strong><small>control de distribución</small></div></div><div class="col-sm-4"><div class="kpi-card"><span class="kpi-label">Exportar</span><div class="d-flex gap-2 mt-3"><button id="exportCsv" class="btn btn-outline-primary">Excel / CSV</button><button id="exportPdf" class="btn btn-outline-primary">PDF</button></div></div></div></div><div class="d-flex align-items-center gap-2 mb-3"><label class="form-label mb-0">Gráficos</label><select id="chartKind" class="form-select" style="width:auto"><option value="bar">Barras</option><option value="doughnut">Torta / anillo</option></select><span class="text-secondary small">Las respuestas abiertas se muestran individualmente.</span></div>'+cards+'<div class="panel-card"><div class="panel-heading"><div><h2>Detalle de participaciones</h2><p>Fecha, anonimato y respuestas.</p></div></div><div class="table-responsive"><table class="table"><thead><tr><th>Fecha</th><th>Participación</th><th>Anónima</th><th>Registrada</th><th>Respuestas</th></tr></thead><tbody>'+(rows||'<tr><td colspan="5" class="text-secondary">Sin respuestas.</td></tr>')+'</tbody></table></div></div>';
  drawQuestionCharts(data,'bar');
  box.querySelector('#chartKind').addEventListener('change',event=>drawQuestionCharts(data,event.target.value));
  box.querySelector('#exportCsv').addEventListener('click',downloadCsv);
  box.querySelector('#exportPdf').addEventListener('click',()=>window.open('report.php?id='+data.survey.id+'&desde='+encodeURIComponent(document.getElementById('resultFrom').value)+'&hasta='+encodeURIComponent(document.getElementById('resultTo').value),'_blank','noopener'));
}

function drawQuestionCharts(data,kind) {
  Object.entries(state.charts).filter(entry=>entry[0].indexOf('question')===0).forEach(entry=>entry[1].destroy());
  data.preguntas.forEach((question,index)=>{
    const canvas=document.getElementById('qChart'+index);if(!canvas)return;
    const entries=Object.entries(question.distribucion);
    state.charts['question'+index]=new Chart(canvas,{type:kind,data:{labels:entries.map(entry=>entry[0]),datasets:[{data:entries.map(entry=>entry[1]),backgroundColor:['#277c7d','#6d8db7','#e5b862','#df8378','#79b899','#9b86bd','#7d9da4','#cd9d7b'],borderWidth:0,borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:kind==='doughnut',position:'bottom',labels:{boxWidth:8,font:{size:10}}}},scales:kind==='bar'?{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}:{}}});
  });
}

async function downloadCsv() {
  const url=new URL('api.php',location.href);url.searchParams.set('action','results.csv');url.searchParams.set('id',document.getElementById('resultSurvey').value);url.searchParams.set('desde',document.getElementById('resultFrom').value);url.searchParams.set('hasta',document.getElementById('resultTo').value);
  const response=await fetch(url,{credentials:'same-origin'});if(!response.ok){const error=await response.json().catch(()=>({}));notice(error.error||'No se pudo exportar.','error');return;}
  const blob=await response.blob(),anchor=document.createElement('a');anchor.href=URL.createObjectURL(blob);anchor.download='resultados-'+document.getElementById('resultSurvey').value+'.csv';anchor.click();URL.revokeObjectURL(anchor.href);
}

async function loadUsers(silent) {
  state.users=await api('users.list');
  if(silent)return;
  document.getElementById('userRows').innerHTML=state.users.map(user=>'<tr><td><strong>'+esc(user.nombre)+'</strong></td><td>'+esc(user.email)+'</td><td><span class="role-badge role-'+user.rol+'">'+roles[user.rol]+'</span></td><td>'+(Number(user.activo)?'<span class="text-success">Activo</span>':'<span class="text-secondary">Desactivado</span>')+'</td><td>'+dateLabel(user.created_at)+'</td><td class="text-end text-nowrap"><button class="icon-button" data-user-edit="'+user.id+'">Editar</button><button class="icon-button danger" data-user-delete="'+user.id+'">Eliminar</button></td></tr>').join('');
}

function userForm(user) {
  user=user||{};
  const roleOptions=[['admin','Administrador'],['pm','Responsable de proyecto'],['auditor','Auditor']].map(item=>'<option value="'+item[0]+'"'+(user.rol===item[0]?' selected':'')+'>'+item[1]+'</option>').join('');
  return '<form id="userForm"><div class="row g-3"><div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="'+esc(user.nombre||'')+'" required></div><div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" type="email" name="email" value="'+esc(user.email||'')+'" required></div><div class="col-md-6"><label class="form-label">Rol</label><select class="form-select" name="rol">'+roleOptions+'</select></div><div class="col-md-6"><label class="form-label">Contraseña '+(user.id?'(opcional)':'')+'</label><input class="form-control" type="password" name="password" minlength="8" '+(user.id?'':'required')+'></div><div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="activo" '+(!user.id||Number(user.activo)?'checked':'')+'><span class="form-check-label">Cuenta activa</span></label></div></div><div id="formError" class="alert alert-danger d-none mt-3"></div><div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar usuario</button></div></form>';
}

function openUser(user) {
  const box=modal(user?'Editar usuario':'Crear usuario',userForm(user));
  box.querySelector('#userForm').addEventListener('submit',async event=>{
    event.preventDefault();const form=event.currentTarget;const data=Object.fromEntries(new FormData(form).entries());data.id=user?user.id:0;data.activo=form.elements.activo.checked;
    try{await api('users.save',{method:'POST',data:data});state.modal.hide();notice('Usuario guardado.');await loadUsers();}
    catch(error){const holder=form.querySelector('#formError');holder.textContent=error.message;holder.classList.remove('d-none');}
  });
}

function bindEvents() {
  document.getElementById('appModal').addEventListener('click',event=>{if(event.target.closest('[data-bs-dismiss="modal"]'))state.modal?.hide();});
  document.querySelectorAll('.nav-item').forEach(button=>button.addEventListener('click',()=>setView(button.dataset.view)));
  document.getElementById('menuToggle').addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('open'));
  document.getElementById('loginForm').addEventListener('submit',async event=>{
    event.preventDefault();const error=document.getElementById('loginError');error.classList.add('d-none');
    try{await api('auth.login',{method:'POST',data:{email:document.getElementById('loginEmail').value,password:document.getElementById('loginPassword').value}});initUser();}
    catch(problem){error.textContent=problem.message;error.classList.remove('d-none');}
  });
  document.getElementById('showSignup').addEventListener('click',()=>{
    document.getElementById('loginForm').classList.add('d-none');
    document.getElementById('signupForm').classList.remove('d-none');
    document.getElementById('showSignup').classList.add('d-none');
    document.getElementById('showLogin').classList.remove('d-none');
  });
  document.getElementById('showLogin').addEventListener('click',()=>{
    document.getElementById('signupForm').classList.add('d-none');
    document.getElementById('loginForm').classList.remove('d-none');
    document.getElementById('showLogin').classList.add('d-none');
    document.getElementById('showSignup').classList.remove('d-none');
  });
  document.getElementById('signupForm').addEventListener('submit',async event=>{
    event.preventDefault();const form=event.currentTarget;const error=document.getElementById('signupError');error.classList.add('d-none');
    try{const result=await api('auth.signup',{method:'POST',data:{nombre:document.getElementById('signupName').value,email:document.getElementById('signupEmail').value,password:document.getElementById('signupPassword').value}});error.textContent=result.message;error.className='alert alert-success mt-3';error.classList.remove('d-none');form.reset();}
    catch(problem){error.textContent=problem.message;error.className='alert alert-danger mt-3';error.classList.remove('d-none');}
  });
  document.getElementById('logoutButton').addEventListener('click',async()=>{try{await api('auth.logout',{method:'POST'});location.reload();}catch(error){notice(error.message,'error');}});
  document.getElementById('newProject').addEventListener('click',()=>openProject(null));
  document.getElementById('newSurvey').addEventListener('click',async()=>{await loadProjects();await openSurvey(0);});
  document.getElementById('newUser').addEventListener('click',()=>openUser(null));
  document.getElementById('refreshDashboard').addEventListener('click',loadDashboard);
  document.getElementById('exportDashboardCsv').addEventListener('click',downloadDashboardCsv);
  document.getElementById('exportDashboardPdf').addEventListener('click',()=>{
    const filters=Object.fromEntries(new FormData(document.getElementById('dashboardFilters')).entries());
    const url=new URL('report_dashboard.php',location.href);
    Object.entries(filters).forEach(entry=>{if(entry[1])url.searchParams.set(entry[0],entry[1]);});
    window.open(url,'_blank','noopener');
  });
  document.getElementById('dashboardFilters').addEventListener('change',loadDashboard);
  let filterTimer;
  document.querySelector('#dashboardFilters [name="cliente"]').addEventListener('input',()=>{clearTimeout(filterTimer);filterTimer=setTimeout(loadDashboard,350);});
  document.getElementById('clearFilters').addEventListener('click',()=>{document.getElementById('dashboardFilters').reset();loadDashboard();});
  document.getElementById('resultSurvey').addEventListener('change',loadResults);
  document.getElementById('resultFrom').addEventListener('change',loadResults);
  document.getElementById('resultTo').addEventListener('change',loadResults);
  document.getElementById('projectRows').addEventListener('click',async event=>{
    const view=event.target.closest('[data-project-view]'),edit=event.target.closest('[data-project-edit]'),remove=event.target.closest('[data-project-delete]');
    try{
      if(view)await viewProject(view.dataset.projectView);
      if(edit)await openProject(state.projects.find(item=>Number(item.id)===Number(edit.dataset.projectEdit)));
      if(remove&&confirm('¿Eliminar este proyecto y las encuestas asociadas?')){await api('projects.delete',{method:'POST',data:{id:remove.dataset.projectDelete}});notice('Proyecto eliminado.');await Promise.all([loadProjects(),loadDashboard(),loadSurveys()]);}
    }catch(error){notice(error.message,'error');}
  });
  document.getElementById('surveyRows').addEventListener('click',async event=>{
    const dist=event.target.closest('[data-survey-distribution]'),edit=event.target.closest('[data-survey-edit]'),results=event.target.closest('[data-survey-results]'),status=event.target.closest('[data-survey-state]'),remove=event.target.closest('[data-survey-delete]');
    try{
      if(dist)await openDistribution(state.surveys.find(item=>Number(item.id)===Number(dist.dataset.surveyDistribution)));
      if(edit)await openSurvey(Number(edit.dataset.surveyEdit));
      if(results)await openResults(results.dataset.surveyResults);
      if(status){await api('surveys.publish',{method:'POST',data:{id:status.dataset.surveyState,estado:status.dataset.next}});notice(status.dataset.next==='publicada'?'Encuesta publicada.':'Encuesta cerrada.');await Promise.all([loadSurveys(),loadDashboard()]);}
      if(remove&&confirm('¿Eliminar esta encuesta y sus respuestas?')){await api('surveys.delete',{method:'POST',data:{id:remove.dataset.surveyDelete}});notice('Encuesta eliminada.');await Promise.all([loadSurveys(),loadDashboard()]);}
    }catch(error){notice(error.message,'error');}
  });
  document.getElementById('userRows').addEventListener('click',async event=>{
    const edit=event.target.closest('[data-user-edit]'),remove=event.target.closest('[data-user-delete]');
    if(edit)openUser(state.users.find(item=>Number(item.id)===Number(edit.dataset.userEdit)));
    if(remove&&confirm('¿Eliminar este usuario?')){try{await api('users.delete',{method:'POST',data:{id:remove.dataset.userDelete}});notice('Usuario eliminado.');await loadUsers();}catch(error){notice(error.message,'error');}}
  });
}

function downloadDashboardCsv() {
  const filters=Object.fromEntries(new FormData(document.getElementById('dashboardFilters')).entries());
  const url=new URL('api.php',location.href);url.searchParams.set('action','dashboard.csv');
  Object.entries(filters).forEach(entry=>{if(entry[1])url.searchParams.set(entry[0],entry[1]);});
  fetch(url,{credentials:'same-origin'}).then(response=>{if(!response.ok)throw new Error('No se pudo exportar el dashboard.');return response.blob();}).then(blob=>{const anchor=document.createElement('a');anchor.href=URL.createObjectURL(blob);anchor.download='reporte-dashboard.csv';anchor.click();URL.revokeObjectURL(anchor.href);}).catch(error=>notice(error.message,'error'));
}

document.addEventListener('DOMContentLoaded',()=>{bindEvents();if(state.user)initUser();setInterval(()=>{if(!document.getElementById('view-dashboard').classList.contains('d-none'))loadDashboard();},60000);});

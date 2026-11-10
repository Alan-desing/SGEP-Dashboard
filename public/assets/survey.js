const token = window.SGEP_PUBLIC.token;
const invite = window.SGEP_PUBLIC.invite;
let csrf = '';
let survey = null;
const safe = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));

async function loadSurvey() {
  const url = new URL('api.php', location.href);
  url.searchParams.set('action', 'survey.public');
  url.searchParams.set('token', token);
  const response = await fetch(url, {credentials:'same-origin',headers:{Accept:'application/json'}});
  const result = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(result.error || 'No se pudo abrir la encuesta.');
  csrf = result.csrf;
  survey = result.survey;
  document.title = survey.titulo + ' · SGEP';
  document.getElementById('surveyHeader').innerHTML = '<p class="eyebrow">Encuesta · ' + safe(survey.proyecto) + '</p><h1>' + safe(survey.titulo) + '</h1><p class="text-secondary mb-1">' + safe(survey.descripcion || 'Tu opinión nos ayuda a mejorar el proyecto.') + '</p><span class="survey-question-count">' + survey.preguntas.length + ' preguntas · ' + (Number(survey.anonima) ? 'respuestas anónimas' : 'respuestas identificadas') + '</span>';
  renderQuestions();
}

function answerControl(question) {
  const id = 'answer-' + question.id;
  if (question.tipo === 'texto') return '<input class="form-control" id="' + id + '" type="text" maxlength="5000" data-answer="' + question.id + '">';
  if (question.tipo === 'parrafo') return '<textarea class="form-control" id="' + id + '" rows="4" maxlength="5000" data-answer="' + question.id + '"></textarea>';
  if (question.tipo === 'fecha') return '<input class="form-control" id="' + id + '" type="date" data-answer="' + question.id + '">';
  if (question.tipo === 'nps') return '<div class="d-flex flex-wrap gap-2 nps-options">' + Array.from({length:11},(_,value)=>'<label class="btn btn-outline-primary"><input class="visually-hidden" type="radio" name="answer-' + question.id + '" value="' + value + '" data-answer="' + question.id + '"><span>' + value + '</span></label>').join('') + '</div><div class="d-flex justify-content-between small text-secondary mt-2"><span>Nada probable</span><span>Muy probable</span></div>';
  if (question.tipo === 'escala') {
    const range = Array.isArray(question.opciones) && question.opciones.length > 1 ? question.opciones : ['0','100'];
    const min = Number(range[0]) || 0;
    const max = Number(range[1]) || 100;
    const initial = Math.round((max + min) / 2);
    return '<div class="d-flex align-items-center gap-3"><input class="form-range" id="' + id + '" type="range" min="' + min + '" max="' + max + '" value="' + initial + '" data-answer="' + question.id + '"><output class="badge text-bg-light" for="' + id + '">' + initial + '</output></div><div class="d-flex justify-content-between small text-secondary"><span>' + min + '</span><span>' + max + '</span></div>';
  }
  const options = question.tipo === 'si_no' ? ['Sí','No'] : question.opciones || [];
  const inputType = question.tipo === 'multiple' ? 'checkbox' : 'radio';
  return options.map((option,index) => '<label class="form-check mb-2"><input class="form-check-input" type="' + inputType + '" name="answer-' + question.id + '" id="' + id + '-' + index + '" value="' + safe(option) + '" data-answer="' + question.id + '"><span class="form-check-label">' + safe(option) + '</span></label>').join('');
}

function renderQuestions() {
  const body = document.getElementById('surveyBody');
  body.innerHTML = '<form id="publicSurveyForm">' + survey.preguntas.map((question,index) => '<section class="response-question" data-question="' + question.id + '" data-parent="' + (question.condicion_pregunta_id || '') + '" data-condition="' + safe(question.condicion_valor || '') + '"><h3>' + (index+1) + '. ' + safe(question.texto) + (Number(question.obligatoria) ? ' <span class="text-danger">*</span>' : '') + '</h3>' + answerControl(question) + '</section>').join('') + '<div id="surveyError" class="alert alert-danger d-none mt-3"></div><button class="btn btn-primary btn-lg w-100 mt-3">Enviar respuestas</button></form>';
  body.querySelectorAll('[data-answer]').forEach(input => {
    input.addEventListener('change', () => {
      if (input.closest('.nps-options')) input.closest('label').classList.toggle('active', input.checked);
      updateConditions();
    });
    if (input.type === 'range') input.addEventListener('input', () => {input.nextElementSibling.value = input.value;});
  });
  updateConditions();
  body.querySelector('#publicSurveyForm').addEventListener('submit', submitSurvey);
}

function getValue(questionId) {
  const fields = [...document.querySelectorAll('[data-answer="' + questionId + '"]')];
  if (!fields.length) return '';
  const first = fields[0];
  if (first.type === 'radio') return fields.find(field => field.checked)?.value || '';
  if (first.type === 'checkbox') return fields.filter(field => field.checked).map(field => field.value);
  return first.value;
}

function updateConditions() {
  survey.preguntas.forEach(question => {
    const section = document.querySelector('[data-question="' + question.id + '"]');
    if (!section) return;
    const parent = Number(question.condicion_pregunta_id || 0);
    const parentAnswer = parent ? getValue(parent) : '';
    const conditionValue = String(question.condicion_valor || '');
    const visible = !parent || (Array.isArray(parentAnswer) ? parentAnswer.map(String).includes(conditionValue) : String(parentAnswer) === conditionValue);
    section.classList.toggle('d-none', !visible);
    section.querySelectorAll('[data-answer]').forEach(input => {
      if (!visible) input.required = false;
      else input.required = Number(question.obligatoria) === 1 && !['radio','checkbox'].includes(input.type) && input.type !== 'range';
    });
  });
}

async function submitSurvey(event) {
  event.preventDefault();
  const errorBox = document.getElementById('surveyError');
  errorBox.classList.add('d-none');
  const answers = {};
  survey.preguntas.forEach(question => {
    const section = document.querySelector('[data-question="' + question.id + '"]');
    if (section && !section.classList.contains('d-none')) answers[question.id] = getValue(question.id);
  });
  const url = new URL('api.php', location.href);
  url.searchParams.set('action', 'survey.submit');
  const response = await fetch(url, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,Accept:'application/json'},body:JSON.stringify({token:token,invitacion:invite,respuestas:answers})});
  const result = await response.json().catch(() => ({}));
  if (!response.ok) {
    errorBox.textContent = result.error || 'No se pudieron guardar tus respuestas.';
    errorBox.classList.remove('d-none');
    return;
  }
  document.getElementById('surveyBody').innerHTML = '<div class="survey-success"><div class="success-icon">✓</div><h2>¡Gracias por responder!</h2><p class="text-secondary">Tu participación quedó registrada.</p><p class="small text-secondary">' + (result.anonima ? 'La encuesta es anónima.' : 'La respuesta se guardó con el registro habilitado.') + '</p></div>';
}

loadSurvey().catch(error => {
  document.getElementById('surveyHeader').innerHTML = '<p class="eyebrow">Encuesta</p><h1>No disponible</h1>';
  document.getElementById('surveyBody').innerHTML = '<div class="alert alert-warning">' + safe(error.message) + '</div>';
});

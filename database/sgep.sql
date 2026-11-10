CREATE DATABASE IF NOT EXISTS sgep_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sgep_dashboard;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS recordatorio;
DROP TABLE IF EXISTS invitacion;
DROP TABLE IF EXISTS respuesta;
DROP TABLE IF EXISTS participacion;
DROP TABLE IF EXISTS visitas;
DROP TABLE IF EXISTS pregunta;
DROP TABLE IF EXISTS encuesta;
DROP TABLE IF EXISTS plantilla;
DROP TABLE IF EXISTS proyecto_responsable;
DROP TABLE IF EXISTS proyecto;
DROP TABLE IF EXISTS usuario;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuario (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(140) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL,
  rol ENUM('admin','pm','auditor') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_email (email),
  KEY idx_usuario_rol_activo (rol, activo)
) ENGINE=InnoDB;

CREATE TABLE proyecto (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  cliente VARCHAR(180) NOT NULL DEFAULT '',
  estado ENUM('planificacion','en_curso','riesgo','completado','pausado') NOT NULL DEFAULT 'planificacion',
  fecha_inicio DATE NULL,
  fecha_fin DATE NULL,
  avance DECIMAL(5,2) NOT NULL DEFAULT 0,
  cumplimiento DECIMAL(5,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proyecto_estado (estado),
  KEY idx_proyecto_cliente (cliente),
  CONSTRAINT chk_proyecto_avance CHECK (avance BETWEEN 0 AND 100),
  CONSTRAINT chk_proyecto_cumplimiento CHECK (cumplimiento BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE proyecto_responsable (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  proyecto_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proyecto_responsable (proyecto_id, user_id),
  KEY idx_responsable_usuario (user_id),
  CONSTRAINT fk_responsable_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyecto(id) ON DELETE CASCADE,
  CONSTRAINT fk_responsable_usuario FOREIGN KEY (user_id) REFERENCES usuario(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE plantilla (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(140) NOT NULL,
  descripcion VARCHAR(255) NOT NULL DEFAULT '',
  tipo VARCHAR(60) NOT NULL,
  preguntas JSON NOT NULL,
  creada_por INT UNSIGNED NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_plantilla_activa (activa),
  CONSTRAINT fk_plantilla_usuario FOREIGN KEY (creada_por) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE encuesta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  proyecto_id INT UNSIGNED NOT NULL,
  plantilla_id INT UNSIGNED NULL,
  creado_por INT UNSIGNED NULL,
  token CHAR(32) NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  tipo ENUM('satisfaccion','avance','cumplimiento','lecciones','general') NOT NULL DEFAULT 'general',
  anonima TINYINT(1) NOT NULL DEFAULT 1,
  publica TINYINT(1) NOT NULL DEFAULT 1,
  estado ENUM('borrador','publicada','cerrada') NOT NULL DEFAULT 'borrador',
  fecha_inicio DATETIME NULL,
  fecha_fin DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_encuesta_token (token),
  KEY idx_encuesta_proyecto_estado (proyecto_id, estado),
  KEY idx_encuesta_tipo (tipo),
  CONSTRAINT fk_encuesta_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyecto(id) ON DELETE CASCADE,
  CONSTRAINT fk_encuesta_plantilla FOREIGN KEY (plantilla_id) REFERENCES plantilla(id) ON DELETE SET NULL,
  CONSTRAINT fk_encuesta_creador FOREIGN KEY (creado_por) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE pregunta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  encuesta_id INT UNSIGNED NOT NULL,
  texto VARCHAR(600) NOT NULL,
  tipo ENUM('texto','parrafo','unica','multiple','escala','nps','fecha','si_no') NOT NULL DEFAULT 'texto',
  obligatoria TINYINT(1) NOT NULL DEFAULT 0,
  opciones JSON NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  metrica ENUM('ninguna','nps','avance','cumplimiento','participacion') NOT NULL DEFAULT 'ninguna',
  condicion_pregunta_id INT UNSIGNED NULL,
  condicion_valor VARCHAR(255) NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pregunta_encuesta_orden (encuesta_id, orden),
  KEY idx_pregunta_condicion (condicion_pregunta_id),
  CONSTRAINT fk_pregunta_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuesta(id) ON DELETE CASCADE,
  CONSTRAINT fk_pregunta_condicion FOREIGN KEY (condicion_pregunta_id) REFERENCES pregunta(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE participacion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  encuesta_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  anonimo TINYINT(1) NOT NULL DEFAULT 1,
  registrado TINYINT(1) NOT NULL DEFAULT 0,
  token_envio CHAR(36) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participacion_token (token_envio),
  KEY idx_participacion_encuesta_fecha (encuesta_id, created_at),
  KEY idx_participacion_usuario (user_id),
  CONSTRAINT fk_participacion_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuesta(id) ON DELETE CASCADE,
  CONSTRAINT fk_participacion_usuario FOREIGN KEY (user_id) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE invitacion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  encuesta_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  token CHAR(32) NOT NULL,
  estado ENUM('pendiente','completada','error') NOT NULL DEFAULT 'pendiente',
  enviada_at DATETIME NULL,
  participacion_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitacion_token (token),
  UNIQUE KEY uq_invitacion_encuesta_email (encuesta_id, email),
  KEY idx_invitacion_encuesta_estado (encuesta_id, estado),
  CONSTRAINT fk_invitacion_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuesta(id) ON DELETE CASCADE,
  CONSTRAINT fk_invitacion_participacion FOREIGN KEY (participacion_id) REFERENCES participacion(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE respuesta (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pregunta_id INT UNSIGNED NOT NULL,
  participacion_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  title VARCHAR(600) NOT NULL,
  datos JSON NOT NULL,
  anonimo TINYINT(1) NOT NULL DEFAULT 1,
  registrado TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_respuesta_participacion_pregunta (participacion_id, pregunta_id),
  KEY idx_respuesta_pregunta_fecha (pregunta_id, created_at),
  KEY idx_respuesta_usuario (user_id),
  CONSTRAINT fk_respuesta_pregunta FOREIGN KEY (pregunta_id) REFERENCES pregunta(id) ON DELETE CASCADE,
  CONSTRAINT fk_respuesta_participacion FOREIGN KEY (participacion_id) REFERENCES participacion(id) ON DELETE CASCADE,
  CONSTRAINT fk_respuesta_usuario FOREIGN KEY (user_id) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE visitas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  proyecto_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_visitas_usuario_fecha (user_id, created_at),
  KEY idx_visitas_proyecto_fecha (proyecto_id, created_at),
  CONSTRAINT fk_visitas_usuario FOREIGN KEY (user_id) REFERENCES usuario(id) ON DELETE CASCADE,
  CONSTRAINT fk_visitas_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyecto(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE recordatorio (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  encuesta_id INT UNSIGNED NOT NULL,
  invitacion_id BIGINT UNSIGNED NULL,
  destinatario_email VARCHAR(190) NOT NULL,
  asunto VARCHAR(255) NOT NULL,
  mensaje TEXT NOT NULL,
  estado ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_error VARCHAR(500) NULL,
  programado_para DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_recordatorio_pendiente (estado, programado_para),
  CONSTRAINT fk_recordatorio_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuesta(id) ON DELETE CASCADE,
  CONSTRAINT fk_recordatorio_invitacion FOREIGN KEY (invitacion_id) REFERENCES invitacion(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO usuario (id, nombre, email, password, rol, activo) VALUES
(1, 'Administración SGEP', 'admin@sgep.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
(2, 'María Responsable', 'pm@sgep.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pm', 1),
(3, 'Auditor de Proyectos', 'auditor@sgep.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'auditor', 1);

INSERT INTO proyecto (id, nombre, descripcion, cliente, estado, fecha_inicio, fecha_fin, avance, cumplimiento) VALUES
(1, 'Plataforma de turnos', 'Digitalización de la atención y asignación de turnos.', 'Centro de Salud Norte', 'en_curso', '2026-03-01', '2026-12-15', 68, 82),
(2, 'Huerta escolar inteligente', 'Automatización del riego y registro de cultivos.', 'Escuela Técnica 7', 'riesgo', '2026-02-10', '2026-11-30', 42, 58),
(3, 'Inventario comunitario', 'Sistema de control de insumos para organizaciones barriales.', 'Municipio', 'completado', '2025-08-01', '2026-06-30', 100, 94);

INSERT INTO proyecto_responsable (proyecto_id, user_id) VALUES (1,2),(2,2),(3,2);

INSERT INTO plantilla (id, nombre, descripcion, tipo, preguntas, creada_por, activa) VALUES
(1, 'Satisfacción de stakeholders', 'Percepción general, recomendación y comentarios.', 'satisfaccion', JSON_ARRAY(JSON_OBJECT('texto','¿Qué tan probable es que recomiende este proyecto?','tipo','nps','obligatoria',true,'metrica','nps'), JSON_OBJECT('texto','¿Cómo evalúa el avance del proyecto?','tipo','escala','obligatoria',true,'metrica','avance'), JSON_OBJECT('texto','¿Qué deberíamos mejorar?','tipo','parrafo','obligatoria',false,'metrica','ninguna')), 1, 1),
(2, 'Seguimiento de avance', 'Control periódico de avance y cumplimiento.', 'avance', JSON_ARRAY(JSON_OBJECT('texto','¿Qué porcentaje de avance percibe?','tipo','escala','obligatoria',true,'metrica','avance'), JSON_OBJECT('texto','¿Se cumplieron los hitos acordados?','tipo','si_no','obligatoria',true,'metrica','cumplimiento')), 1, 1),
(3, 'Lecciones aprendidas', 'Identificación de aprendizajes y próximos pasos.', 'lecciones', JSON_ARRAY(JSON_OBJECT('texto','¿Qué salió bien?','tipo','parrafo','obligatoria',true,'metrica','ninguna'), JSON_OBJECT('texto','¿Qué haría diferente?','tipo','parrafo','obligatoria',false,'metrica','ninguna')), 1, 1);

INSERT INTO encuesta (id, proyecto_id, plantilla_id, creado_por, token, titulo, descripcion, tipo, anonima, publica, estado, fecha_inicio) VALUES
(1,1,1,2,'a12f39d8b4274cc3ad96e027c2d6c101','Pulso de satisfacción','Encuesta trimestral para stakeholders del proyecto.','satisfaccion',1,1,'publicada','2026-03-01 00:00:00'),
(2,1,2,2,'b23a40e9c5384dd4be07f138d3e7d212','Revisión de avance','Medición de avance percibido y cumplimiento.','avance',0,1,'publicada','2026-04-01 00:00:00'),
(3,2,1,2,'c34b51f0d6494ee5cf18a249e4f8e323','Satisfacción de la comunidad','Opinión de estudiantes y docentes.','satisfaccion',1,1,'publicada','2026-02-10 00:00:00'),
(4,3,3,2,'d45c6201e7504ff6d029b35af509f434','Lecciones de cierre','Revisión final y aprendizajes.','lecciones',1,1,'publicada','2026-05-01 00:00:00');

INSERT INTO pregunta (id, encuesta_id, texto, tipo, obligatoria, opciones, orden, metrica) VALUES
(1,1,'¿Qué tan probable es que recomiende este proyecto?','nps',1,NULL,1,'nps'),
(2,1,'¿Cómo evalúa el avance del proyecto?','escala',1,JSON_ARRAY('0','10'),2,'avance'),
(3,1,'¿Qué deberíamos mejorar?','parrafo',0,NULL,3,'ninguna'),
(4,2,'¿Qué porcentaje de avance percibe?','escala',1,JSON_ARRAY('0','100'),1,'avance'),
(5,2,'¿Se cumplieron los hitos acordados?','si_no',1,JSON_ARRAY('Sí','No'),2,'cumplimiento'),
(6,3,'¿Qué tan probable es que recomiende este proyecto?','nps',1,NULL,1,'nps'),
(7,3,'¿Cómo evalúa el avance del proyecto?','escala',1,JSON_ARRAY('0','10'),2,'avance'),
(8,3,'¿Qué deberíamos mejorar?','parrafo',0,NULL,3,'ninguna'),
(9,4,'¿Qué salió bien?','parrafo',1,NULL,1,'ninguna'),
(10,4,'¿Qué haría diferente?','parrafo',0,NULL,2,'ninguna');

INSERT INTO participacion (id, encuesta_id, user_id, anonimo, registrado, token_envio, created_at) VALUES
(1,1,NULL,1,0,'11111111-1111-4111-8111-111111111111','2026-04-02 10:00:00'),
(2,1,NULL,1,0,'22222222-2222-4222-8222-222222222222','2026-04-12 11:00:00'),
(3,1,3,1,1,'33333333-3333-4333-8333-333333333333','2026-05-04 09:00:00'),
(4,1,NULL,1,0,'44444444-4444-4444-8444-444444444444','2026-05-20 13:00:00'),
(5,1,NULL,1,0,'55555555-5555-4555-8555-555555555555','2026-06-09 15:00:00'),
(6,1,NULL,1,0,'66666666-6666-4666-8666-666666666666','2026-07-02 12:00:00'),
(7,2,3,0,1,'77777777-7777-4777-8777-777777777777','2026-05-10 09:00:00'),
(8,2,NULL,0,0,'88888888-8888-4888-8888-888888888888','2026-06-15 16:00:00'),
(9,2,NULL,0,0,'99999999-9999-4999-8999-999999999999','2026-07-18 10:00:00'),
(10,3,NULL,1,0,'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','2026-03-01 10:00:00'),
(11,3,NULL,1,0,'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','2026-04-03 14:00:00'),
(12,3,NULL,1,0,'cccccccc-cccc-4ccc-8ccc-cccccccccccc','2026-05-15 11:00:00'),
(13,3,NULL,1,0,'dddddddd-dddd-4ddd-8ddd-dddddddddddd','2026-06-28 12:00:00'),
(14,4,NULL,1,0,'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee','2026-06-30 12:00:00');

INSERT INTO respuesta (pregunta_id, participacion_id, user_id, title, datos, anonimo, registrado, created_at) VALUES
(1,1,NULL,'¿Qué tan probable es que recomiende este proyecto?','6',1,0,'2026-04-02 10:00:00'),(2,1,NULL,'¿Cómo evalúa el avance del proyecto?','6',1,0,'2026-04-02 10:00:00'),(3,1,NULL,'¿Qué deberíamos mejorar?','"Mejorar la comunicación de fechas"',1,0,'2026-04-02 10:00:00'),
(1,2,NULL,'¿Qué tan probable es que recomiende este proyecto?','8',1,0,'2026-04-12 11:00:00'),(2,2,NULL,'¿Cómo evalúa el avance del proyecto?','7',1,0,'2026-04-12 11:00:00'),(3,2,NULL,'¿Qué deberíamos mejorar?','"Compartir avances con más frecuencia"',1,0,'2026-04-12 11:00:00'),
(1,3,NULL,'¿Qué tan probable es que recomiende este proyecto?','9',1,1,'2026-05-04 09:00:00'),(2,3,NULL,'¿Cómo evalúa el avance del proyecto?','8',1,1,'2026-05-04 09:00:00'),(3,3,NULL,'¿Qué deberíamos mejorar?','"La experiencia es buena"',1,1,'2026-05-04 09:00:00'),
(1,4,NULL,'¿Qué tan probable es que recomiende este proyecto?','7',1,0,'2026-05-20 13:00:00'),(2,4,NULL,'¿Cómo evalúa el avance del proyecto?','7',1,0,'2026-05-20 13:00:00'),(3,4,NULL,'¿Qué deberíamos mejorar?','"Publicar responsables por etapa"',1,0,'2026-05-20 13:00:00'),
(1,5,NULL,'¿Qué tan probable es que recomiende este proyecto?','10',1,0,'2026-06-09 15:00:00'),(2,5,NULL,'¿Cómo evalúa el avance del proyecto?','9',1,0,'2026-06-09 15:00:00'),(3,5,NULL,'¿Qué deberíamos mejorar?','"Continuar con reuniones periódicas"',1,0,'2026-06-09 15:00:00'),
(1,6,NULL,'¿Qué tan probable es que recomiende este proyecto?','8',1,0,'2026-07-02 12:00:00'),(2,6,NULL,'¿Cómo evalúa el avance del proyecto?','8',1,0,'2026-07-02 12:00:00'),(3,6,NULL,'¿Qué deberíamos mejorar?','"Incluir a más usuarios en pruebas"',1,0,'2026-07-02 12:00:00'),
(4,7,3,'¿Qué porcentaje de avance percibe?','72',0,1,'2026-05-10 09:00:00'),(5,7,3,'¿Se cumplieron los hitos acordados?','"Sí"',0,1,'2026-05-10 09:00:00'),
(4,8,NULL,'¿Qué porcentaje de avance percibe?','64',0,0,'2026-06-15 16:00:00'),(5,8,NULL,'¿Se cumplieron los hitos acordados?','"No"',0,0,'2026-06-15 16:00:00'),
(4,9,NULL,'¿Qué porcentaje de avance percibe?','58',0,0,'2026-07-18 10:00:00'),(5,9,NULL,'¿Se cumplieron los hitos acordados?','"No"',0,0,'2026-07-18 10:00:00'),
(6,10,NULL,'¿Qué tan probable es que recomiende este proyecto?','5',1,0,'2026-03-01 10:00:00'),(7,10,NULL,'¿Cómo evalúa el avance del proyecto?','4',1,0,'2026-03-01 10:00:00'),(8,10,NULL,'¿Qué deberíamos mejorar?','"Asegurar materiales antes de iniciar"',1,0,'2026-03-01 10:00:00'),
(6,11,NULL,'¿Qué tan probable es que recomiende este proyecto?','6',1,0,'2026-04-03 14:00:00'),(7,11,NULL,'¿Cómo evalúa el avance del proyecto?','5',1,0,'2026-04-03 14:00:00'),(8,11,NULL,'¿Qué deberíamos mejorar?','"Coordinar mejor el calendario de riego"',1,0,'2026-04-03 14:00:00'),
(6,12,NULL,'¿Qué tan probable es que recomiende este proyecto?','7',1,0,'2026-05-15 11:00:00'),(7,12,NULL,'¿Cómo evalúa el avance del proyecto?','5',1,0,'2026-05-15 11:00:00'),(8,12,NULL,'¿Qué deberíamos mejorar?','"Sumar más talleres"',1,0,'2026-05-15 11:00:00'),
(6,13,NULL,'¿Qué tan probable es que recomiende este proyecto?','4',1,0,'2026-06-28 12:00:00'),(7,13,NULL,'¿Cómo evalúa el avance del proyecto?','6',1,0,'2026-06-28 12:00:00'),(8,13,NULL,'¿Qué deberíamos mejorar?','"Revisar los sensores"',1,0,'2026-06-28 12:00:00'),
(9,14,NULL,'¿Qué salió bien?','"El registro de stock redujo faltantes"',1,0,'2026-06-30 12:00:00'),(10,14,NULL,'¿Qué haría diferente?','"Capacitaría a los usuarios antes"',1,0,'2026-06-30 12:00:00');

INSERT INTO visitas (user_id, proyecto_id, created_at) VALUES
(1,1,'2026-07-01 09:00:00'),(2,1,'2026-07-02 09:00:00'),(2,2,'2026-07-03 09:00:00'),(3,1,'2026-07-05 09:00:00');

INSERT INTO invitacion (encuesta_id, email, token, estado, enviada_at) VALUES
(1,'stakeholder1@example.local','10000000000000000000000000000001','completada','2026-04-01 09:00:00'),
(1,'stakeholder2@example.local','10000000000000000000000000000002','completada','2026-04-01 09:00:00'),
(1,'stakeholder3@example.local','10000000000000000000000000000003','pendiente','2026-04-01 09:00:00'),
(1,'stakeholder4@example.local','10000000000000000000000000000004','pendiente','2026-04-01 09:00:00'),
(2,'stakeholder5@example.local','20000000000000000000000000000001','completada','2026-05-01 09:00:00'),
(2,'stakeholder6@example.local','20000000000000000000000000000002','pendiente','2026-05-01 09:00:00'),
(3,'comunidad1@example.local','30000000000000000000000000000001','completada','2026-02-20 09:00:00'),
(3,'comunidad2@example.local','30000000000000000000000000000002','pendiente','2026-02-20 09:00:00');

UPDATE invitacion SET participacion_id = CASE
  WHEN encuesta_id = 1 AND email = 'stakeholder1@example.local' THEN 1
  WHEN encuesta_id = 1 AND email = 'stakeholder2@example.local' THEN 2
  WHEN encuesta_id = 2 AND email = 'stakeholder5@example.local' THEN 7
  WHEN encuesta_id = 3 AND email = 'comunidad1@example.local' THEN 10
  ELSE NULL
END WHERE estado = 'completada';

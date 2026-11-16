-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-11-2026 a las 19:10:45
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sgep_dashboard`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `encuesta`
--

CREATE TABLE `encuesta` (
  `id` int(10) UNSIGNED NOT NULL,
  `proyecto_id` int(10) UNSIGNED NOT NULL,
  `plantilla_id` int(10) UNSIGNED DEFAULT NULL,
  `creado_por` int(10) UNSIGNED DEFAULT NULL,
  `token` char(32) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('satisfaccion','avance','cumplimiento','lecciones','general') NOT NULL DEFAULT 'general',
  `anonima` tinyint(1) NOT NULL DEFAULT 1,
  `publica` tinyint(1) NOT NULL DEFAULT 1,
  `estado` enum('borrador','publicada','cerrada') NOT NULL DEFAULT 'borrador',
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `encuesta`
--

INSERT INTO `encuesta` (`id`, `proyecto_id`, `plantilla_id`, `creado_por`, `token`, `titulo`, `descripcion`, `tipo`, `anonima`, `publica`, `estado`, `fecha_inicio`, `fecha_fin`, `created_at`, `updated_at`) VALUES
(5, 4, NULL, 1, '487f278bc90efe8446d53f8da20cb44c', 'Juego Malvinas', 'Nivel de satisfaccion del publico', 'satisfaccion', 1, 1, 'publicada', '2026-11-16 15:06:29', NULL, '2026-11-16 18:06:16', '2026-11-16 18:06:29'),
(6, 4, NULL, 1, 'ec4a84f2d371dcb2bc9fd7fb5f033308', 'Juego Malvinas', 'Encuesta sobre el cumplimiento de requisitos del proyecto', 'cumplimiento', 1, 1, 'publicada', '2026-11-16 15:08:25', NULL, '2026-11-16 18:08:23', '2026-11-16 18:08:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invitacion`
--

CREATE TABLE `invitacion` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `encuesta_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `token` char(32) NOT NULL,
  `estado` enum('pendiente','completada','error') NOT NULL DEFAULT 'pendiente',
  `enviada_at` datetime DEFAULT NULL,
  `participacion_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `participacion`
--

CREATE TABLE `participacion` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `encuesta_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `anonimo` tinyint(1) NOT NULL DEFAULT 1,
  `registrado` tinyint(1) NOT NULL DEFAULT 0,
  `token_envio` char(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `participacion`
--

INSERT INTO `participacion` (`id`, `encuesta_id`, `user_id`, `anonimo`, `registrado`, `token_envio`, `created_at`) VALUES
(15, 5, NULL, 1, 1, 'c907a131-4334-4f52-29e9-24db364f0a63', '2026-11-16 18:06:37'),
(16, 6, NULL, 1, 1, '2fd0f1d3-caf6-4444-252b-5c7d8bcad84f', '2026-11-16 18:08:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantilla`
--

CREATE TABLE `plantilla` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(140) NOT NULL,
  `descripcion` varchar(255) NOT NULL DEFAULT '',
  `tipo` varchar(60) NOT NULL,
  `preguntas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`preguntas`)),
  `creada_por` int(10) UNSIGNED DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `plantilla`
--

INSERT INTO `plantilla` (`id`, `nombre`, `descripcion`, `tipo`, `preguntas`, `creada_por`, `activa`, `created_at`) VALUES
(1, 'Satisfacción de stakeholders', 'Percepción general, recomendación y comentarios.', 'satisfaccion', '[{\"texto\": \"¿Qué tan probable es que recomiende este proyecto?\", \"tipo\": \"nps\", \"obligatoria\": true, \"metrica\": \"nps\"}, {\"texto\": \"¿Cómo evalúa el avance del proyecto?\", \"tipo\": \"escala\", \"obligatoria\": true, \"metrica\": \"avance\"}, {\"texto\": \"¿Qué deberíamos mejorar?\", \"tipo\": \"parrafo\", \"obligatoria\": false, \"metrica\": \"ninguna\"}]', 1, 1, '2026-11-10 21:03:22'),
(2, 'Seguimiento de avance', 'Control periódico de avance y cumplimiento.', 'avance', '[{\"texto\": \"¿Qué porcentaje de avance percibe?\", \"tipo\": \"escala\", \"obligatoria\": true, \"metrica\": \"avance\"}, {\"texto\": \"¿Se cumplieron los hitos acordados?\", \"tipo\": \"si_no\", \"obligatoria\": true, \"metrica\": \"cumplimiento\"}]', 1, 1, '2026-11-10 21:03:22'),
(3, 'Lecciones aprendidas', 'Identificación de aprendizajes y próximos pasos.', 'lecciones', '[{\"texto\": \"¿Qué salió bien?\", \"tipo\": \"parrafo\", \"obligatoria\": true, \"metrica\": \"ninguna\"}, {\"texto\": \"¿Qué haría diferente?\", \"tipo\": \"parrafo\", \"obligatoria\": false, \"metrica\": \"ninguna\"}]', 1, 1, '2026-11-10 21:03:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pregunta`
--

CREATE TABLE `pregunta` (
  `id` int(10) UNSIGNED NOT NULL,
  `encuesta_id` int(10) UNSIGNED NOT NULL,
  `texto` varchar(600) NOT NULL,
  `tipo` enum('texto','parrafo','unica','multiple','escala','nps','fecha','si_no') NOT NULL DEFAULT 'texto',
  `obligatoria` tinyint(1) NOT NULL DEFAULT 0,
  `opciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opciones`)),
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `metrica` enum('ninguna','nps','avance','cumplimiento','participacion') NOT NULL DEFAULT 'ninguna',
  `condicion_pregunta_id` int(10) UNSIGNED DEFAULT NULL,
  `condicion_valor` varchar(255) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pregunta`
--

INSERT INTO `pregunta` (`id`, `encuesta_id`, `texto`, `tipo`, `obligatoria`, `opciones`, `orden`, `metrica`, `condicion_pregunta_id`, `condicion_valor`, `activa`, `created_at`) VALUES
(11, 5, 'Cual es tu nivel de satisfaccion con este proyecto', 'nps', 1, NULL, 1, 'ninguna', NULL, NULL, 1, '2026-11-16 18:06:16'),
(12, 6, 'Consideras que se cumplieron los requisitos del proyecto?', 'si_no', 1, '[\"Sí\",\"No\"]', 1, 'ninguna', NULL, NULL, 1, '2026-11-16 18:08:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto`
--

CREATE TABLE `proyecto` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `cliente` varchar(180) NOT NULL DEFAULT '',
  `estado` enum('planificacion','en_curso','riesgo','completado','pausado') NOT NULL DEFAULT 'planificacion',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `avance` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cumplimiento` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `proyecto`
--

INSERT INTO `proyecto` (`id`, `nombre`, `descripcion`, `cliente`, `estado`, `fecha_inicio`, `fecha_fin`, `avance`, `cumplimiento`, `created_at`, `updated_at`) VALUES
(4, 'Malvinas Voces del Frente', 'Videojuego educativo sobre la guerra de Malvinas', 'Instituciones', 'completado', '2026-03-12', '2026-11-16', 100.00, 100.00, '2026-11-16 18:02:52', '2026-11-16 18:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto_responsable`
--

CREATE TABLE `proyecto_responsable` (
  `id` int(10) UNSIGNED NOT NULL,
  `proyecto_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proyecto_responsable`
--

INSERT INTO `proyecto_responsable` (`id`, `proyecto_id`, `user_id`, `created_at`) VALUES
(4, 4, 2, '2026-11-16 18:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recordatorio`
--

CREATE TABLE `recordatorio` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `encuesta_id` int(10) UNSIGNED NOT NULL,
  `invitacion_id` bigint(20) UNSIGNED DEFAULT NULL,
  `destinatario_email` varchar(190) NOT NULL,
  `asunto` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  `intentos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_error` varchar(500) DEFAULT NULL,
  `programado_para` datetime NOT NULL DEFAULT current_timestamp(),
  `enviado_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuesta`
--

CREATE TABLE `respuesta` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pregunta_id` int(10) UNSIGNED NOT NULL,
  `participacion_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(600) NOT NULL,
  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`datos`)),
  `anonimo` tinyint(1) NOT NULL DEFAULT 1,
  `registrado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `respuesta`
--

INSERT INTO `respuesta` (`id`, `pregunta_id`, `participacion_id`, `user_id`, `title`, `datos`, `anonimo`, `registrado`, `created_at`) VALUES
(39, 11, 15, NULL, 'Cual es tu nivel de satisfaccion con este proyecto', '9', 1, 1, '2026-11-16 18:06:37'),
(40, 12, 16, NULL, 'Consideras que se cumplieron los requisitos del proyecto?', '\"Sí\"', 1, 1, '2026-11-16 18:08:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(140) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','pm','auditor') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id`, `nombre`, `email`, `password`, `rol`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Administración SGEP', 'admin@sgep.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, '2026-11-10 21:03:22', '2026-11-10 21:03:22'),
(2, 'Alan', 'pm@sgep.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pm', 1, '2026-11-10 21:03:22', '2026-11-16 18:03:13'),
(3, 'Auditor de Proyectos', 'auditor@sgep.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'auditor', 1, '2026-11-10 21:03:22', '2026-11-10 21:03:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas`
--

CREATE TABLE `visitas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `proyecto_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `encuesta`
--
ALTER TABLE `encuesta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_encuesta_token` (`token`),
  ADD KEY `idx_encuesta_proyecto_estado` (`proyecto_id`,`estado`),
  ADD KEY `idx_encuesta_tipo` (`tipo`),
  ADD KEY `fk_encuesta_plantilla` (`plantilla_id`),
  ADD KEY `fk_encuesta_creador` (`creado_por`);

--
-- Indices de la tabla `invitacion`
--
ALTER TABLE `invitacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_invitacion_token` (`token`),
  ADD UNIQUE KEY `uq_invitacion_encuesta_email` (`encuesta_id`,`email`),
  ADD KEY `idx_invitacion_encuesta_estado` (`encuesta_id`,`estado`),
  ADD KEY `fk_invitacion_participacion` (`participacion_id`);

--
-- Indices de la tabla `participacion`
--
ALTER TABLE `participacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_participacion_token` (`token_envio`),
  ADD KEY `idx_participacion_encuesta_fecha` (`encuesta_id`,`created_at`),
  ADD KEY `idx_participacion_usuario` (`user_id`);

--
-- Indices de la tabla `plantilla`
--
ALTER TABLE `plantilla`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plantilla_activa` (`activa`),
  ADD KEY `fk_plantilla_usuario` (`creada_por`);

--
-- Indices de la tabla `pregunta`
--
ALTER TABLE `pregunta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pregunta_encuesta_orden` (`encuesta_id`,`orden`),
  ADD KEY `idx_pregunta_condicion` (`condicion_pregunta_id`);

--
-- Indices de la tabla `proyecto`
--
ALTER TABLE `proyecto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proyecto_estado` (`estado`),
  ADD KEY `idx_proyecto_cliente` (`cliente`);

--
-- Indices de la tabla `proyecto_responsable`
--
ALTER TABLE `proyecto_responsable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_proyecto_responsable` (`proyecto_id`,`user_id`),
  ADD KEY `idx_responsable_usuario` (`user_id`);

--
-- Indices de la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recordatorio_pendiente` (`estado`,`programado_para`),
  ADD KEY `fk_recordatorio_encuesta` (`encuesta_id`),
  ADD KEY `fk_recordatorio_invitacion` (`invitacion_id`);

--
-- Indices de la tabla `respuesta`
--
ALTER TABLE `respuesta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_respuesta_participacion_pregunta` (`participacion_id`,`pregunta_id`),
  ADD KEY `idx_respuesta_pregunta_fecha` (`pregunta_id`,`created_at`),
  ADD KEY `idx_respuesta_usuario` (`user_id`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuario_email` (`email`),
  ADD KEY `idx_usuario_rol_activo` (`rol`,`activo`);

--
-- Indices de la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visitas_usuario_fecha` (`user_id`,`created_at`),
  ADD KEY `idx_visitas_proyecto_fecha` (`proyecto_id`,`created_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `encuesta`
--
ALTER TABLE `encuesta`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `invitacion`
--
ALTER TABLE `invitacion`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `participacion`
--
ALTER TABLE `participacion`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `plantilla`
--
ALTER TABLE `plantilla`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `pregunta`
--
ALTER TABLE `pregunta`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `proyecto`
--
ALTER TABLE `proyecto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proyecto_responsable`
--
ALTER TABLE `proyecto_responsable`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `respuesta`
--
ALTER TABLE `respuesta`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `visitas`
--
ALTER TABLE `visitas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `encuesta`
--
ALTER TABLE `encuesta`
  ADD CONSTRAINT `fk_encuesta_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_encuesta_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `plantilla` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_encuesta_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyecto` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `invitacion`
--
ALTER TABLE `invitacion`
  ADD CONSTRAINT `fk_invitacion_encuesta` FOREIGN KEY (`encuesta_id`) REFERENCES `encuesta` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invitacion_participacion` FOREIGN KEY (`participacion_id`) REFERENCES `participacion` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `participacion`
--
ALTER TABLE `participacion`
  ADD CONSTRAINT `fk_participacion_encuesta` FOREIGN KEY (`encuesta_id`) REFERENCES `encuesta` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_participacion_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `plantilla`
--
ALTER TABLE `plantilla`
  ADD CONSTRAINT `fk_plantilla_usuario` FOREIGN KEY (`creada_por`) REFERENCES `usuario` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pregunta`
--
ALTER TABLE `pregunta`
  ADD CONSTRAINT `fk_pregunta_condicion` FOREIGN KEY (`condicion_pregunta_id`) REFERENCES `pregunta` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pregunta_encuesta` FOREIGN KEY (`encuesta_id`) REFERENCES `encuesta` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `proyecto_responsable`
--
ALTER TABLE `proyecto_responsable`
  ADD CONSTRAINT `fk_responsable_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyecto` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_responsable_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  ADD CONSTRAINT `fk_recordatorio_encuesta` FOREIGN KEY (`encuesta_id`) REFERENCES `encuesta` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recordatorio_invitacion` FOREIGN KEY (`invitacion_id`) REFERENCES `invitacion` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `respuesta`
--
ALTER TABLE `respuesta`
  ADD CONSTRAINT `fk_respuesta_participacion` FOREIGN KEY (`participacion_id`) REFERENCES `participacion` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_respuesta_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `pregunta` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_respuesta_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD CONSTRAINT `fk_visitas_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyecto` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_visitas_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

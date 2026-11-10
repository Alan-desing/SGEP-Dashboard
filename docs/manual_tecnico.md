# Manual técnico

## Arquitectura

SGEP utiliza PHP como aplicación y API REST, MySQL como persistencia, HTML/CSS/Bootstrap para las vistas y JavaScript para la interacción sin recargas. `public/` es la única raíz que debe exponer Apache.

`public/index.php` presenta el panel. `public/survey.php` sirve el formulario de respuesta pública. `public/api.php` despacha operaciones JSON a la lógica separada de `app/`. `report.php` y `report_dashboard.php` generan vistas imprimibles a partir de datos almacenados.

## Organización del código

- `app/bootstrap.php`: sesión, PDO, validaciones comunes, respuestas JSON, control de acceso a proyecto y manejo de errores.
- `app/auth.php`: inicio de sesión, cierre y solicitud de cuenta.
- `app/projects.php`: consulta, alta, edición, eliminación y detalle de proyectos.
- `app/users.php`: CRUD de usuarios y controles para preservar un Administrador activo.
- `app/surveys.php`: plantillas, preguntas, constructor, publicación, participación, invitaciones y recordatorios.
- `app/analytics.php`: filtros, KPIs, resultados agrupados y exportación CSV.
- `config/database.php`: credenciales MySQL y variables de entorno.
- `config/app.php`: URL base, remitente de correo y zona horaria.
- `database/sgep.sql`: esquema MySQL 8 y datos de demostración.
- `scripts/send_reminders.php`: proceso CLI que envía recordatorios vencidos.

## Base de datos y relaciones

Las tablas principales del diagrama se mantienen:

- `usuario` contiene nombre, correo, hash de contraseña y rol.
- `proyecto` contiene cliente, fechas, estado, avance y cumplimiento.
- `encuesta` se relaciona con un proyecto, una plantilla opcional y la persona creadora.
- `pregunta` pertenece a una encuesta, ordena preguntas, almacena opciones JSON, métrica y referencia condicional.
- `respuesta` pertenece a una pregunta y a una participación; almacena el valor JSON, texto de la pregunta, anonimato, registro y fecha.
- `visitas` conserva la consulta autenticada de un usuario a un proyecto.

Tablas adicionales:

- `proyecto_responsable` materializa la asignación muchos a muchos usuario–proyecto.
- `plantilla` guarda estructuras JSON reutilizables.
- `participacion` agrupa las respuestas que se enviaron en conjunto y conserva el estado de registro y anonimato.
- `invitacion` registra destinatarios, token individual y estado de respuesta.
- `recordatorio` conserva la programación y estado del correo de seguimiento.

Las claves primarias usan `AUTO_INCREMENT`; las relaciones tienen claves foráneas con borrado en cascada cuando la entidad dependiente no tiene sentido por sí sola. Una pregunta contestada que se retira del constructor se desactiva para conservar sus respuestas históricas.

## Autenticación y permisos

PHP mantiene la sesión con cookie `HttpOnly` y política `SameSite=Lax`. El ingreso valida `password_verify()`; el alta y los cambios de contraseña usan `password_hash()`. Las operaciones que modifican datos requieren token CSRF enviado en `X-CSRF-Token`.

La API valida sesión, rol y asignación del proyecto en cada operación. El rol `admin` administra el sistema; `pm` accede a proyectos en los que aparece en `proyecto_responsable`; `auditor` tiene acceso de lectura. La solicitud pública de cuentas asigna el rol Auditor y crea el usuario desactivado hasta su habilitación administrativa.

Para encuestas anónimas, `participacion.user_id` y `respuesta.user_id` permanecen nulos incluso si responde un usuario autenticado. El campo `registrado` registra por separado si había una sesión. Para encuestas no anónimas se requiere sesión y se almacena el usuario.

## API

Todas las operaciones están en `public/api.php`, seleccionadas mediante `action`.

| Acción | Método | Acceso |
|---|---|---|
| `auth.me`, `auth.login`, `auth.logout`, `auth.signup` | GET / POST | Público o sesión según acción |
| `projects.list`, `projects.get` | GET | Usuario autenticado; PM limitado a sus proyectos |
| `projects.save`, `projects.delete` | POST | Admin/PM para guardar; Admin para eliminar |
| `users.list`, `users.save`, `users.delete` | GET / POST | Admin |
| `surveys.list`, `surveys.get`, `surveys.templates` | GET | Usuario autenticado |
| `surveys.save`, `surveys.publish` | POST | Admin/PM con acceso al proyecto |
| `surveys.delete` | POST | Admin |
| `survey.public` | GET | Público si la encuesta es pública; sesión si es restringida |
| `survey.submit` | POST | Público con token CSRF, o usuario autenticado |
| `surveys.invite`, `surveys.remind` | POST | Admin/PM con acceso al proyecto |
| `dashboard.data`, `dashboard.csv` | GET | Usuario autenticado, scope de PM |
| `results.data`, `results.csv` | GET | Usuario autenticado, scope de PM |

Las entradas se limitan por longitud y se consultan mediante PDO con sentencias preparadas. La vista no sustituye las comprobaciones del servidor.

## Constructor y respuestas

El constructor permite ordenar tarjetas con HTML Drag and Drop. Las preguntas guardan tipo, obligatoriedad, opciones, métrica, posición y condición. Al guardar, la API mantiene las preguntas existentes, agrega las nuevas y desactiva las eliminadas que ya tengan respuestas.

El enlace de encuesta usa un token aleatorio de 32 caracteres hexadecimales. Las invitaciones reciben un token individual que se adjunta al enlace y vincula la participación con esa invitación. Las respuestas se guardan como filas por pregunta y participación.

## Dashboard y métricas

El dashboard consulta participaciones y respuestas MySQL. Los filtros de fecha se aplican sobre `participacion.created_at`; proyecto, cliente, responsable y tipo se aplican a sus relaciones correspondientes.

- **NPS:** porcentaje de promotores (9–10) menos porcentaje de detractores (0–6).
- **Avance percibido:** promedio de respuestas a preguntas marcadas como avance, normalizadas al rango 0–100 según la escala configurada.
- **Cumplimiento:** promedio de valores de cumplimiento; respuestas “Sí” valen 100 y “No” valen 0.
- **Participación:** invitaciones completadas sobre invitaciones registradas en el período.
- **Riesgo:** estado de proyecto “riesgo”, NPS menor que −10 o cumplimiento medido menor que 60.

Los gráficos de línea, barras y torta/anillo se generan con Chart.js; el mapa de calor es una tabla de indicadores con colores. Si no hay respuestas para un indicador, se muestra como dato vacío y no se inventa un valor.

## Distribución y correo

La aplicación envía invitaciones con `mail()` de PHP y mantiene estados por dirección. La entrega depende de la configuración local del servidor de correo. Los recordatorios se almacenan en `recordatorio`; una tarea de sistema debe ejecutar `php scripts/send_reminders.php` con la zona horaria y `SGEP_BASE_URL` configuradas.

## Exportaciones

Los endpoints CSV generan valores actuales de MySQL, con BOM UTF-8 y separador punto y coma para facilitar la apertura con Excel en configuraciones regionales españolas. `report.php` contiene resultados de encuesta y `report_dashboard.php` incluye KPIs y proyectos. Ambas vistas llaman al diálogo de impresión para guardar como PDF.

## Instalación y operación

El procedimiento de XAMPP, las credenciales semilla, las variables de entorno y las URLs se detallan en el README de la raíz. Para un despliegue, usá credenciales MySQL propias, cambialas mediante configuración de entorno, definí `SGEP_BASE_URL`, configurá correo y serví únicamente el directorio `public/`.

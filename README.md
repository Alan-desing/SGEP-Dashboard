# Sistema de Gestión de Encuestas para la Evaluación de Proyectos

**SGEP-Dashboard** permite administrar proyectos, construir y distribuir encuestas, centralizar respuestas y consultar métricas para el seguimiento de proyectos de 7mo.

## Objetivo

El sistema reemplaza planillas dispersas y formularios aislados por un espacio común con trazabilidad de proyectos, responsables, encuestas, invitaciones y respuestas. El dashboard reúne satisfacción, avance percibido, cumplimiento y participación para apoyar decisiones de seguimiento.

## Funcionalidades

- Alta, consulta y edición de proyectos con estado, cliente, avance, cumplimiento y responsables.
- Cuentas con roles Administrador, Responsable de proyecto y Auditor.
- Constructor de encuestas con reordenamiento drag & drop, preguntas obligatorias, opciones y reglas condicionales.
- Plantillas reutilizables de satisfacción, avance y lecciones aprendidas.
- Encuestas anónimas o identificadas, públicas o restringidas.
- Enlace único por encuesta, QR, invitaciones por correo y recordatorios programados.
- Respuestas públicas sin registro. Cada participación distingue `registrado` y conserva el anonimato configurado.
- Dashboard con NPS, avance percibido, cumplimiento, participación, filtros, gráficos, mapa de calor y semáforo de riesgo.
- Resultados por pregunta, distribuciones, filtros por fecha y detalle de participaciones.
- Exportación compatible con Excel en CSV y reportes para imprimir o guardar como PDF.
- Datos de demostración para que el dashboard tenga información al importar la base.

## Tecnologías

- **HTML, CSS y Bootstrap 5:** pantallas responsive.
- **JavaScript:** interacción, constructor drag & drop, gráficos, QR y llamadas `fetch`.
- **PHP 8.1 o superior:** sesiones, API REST, reglas de negocio y exportaciones.
- **MySQL 8.0.16 o superior:** almacenamiento relacional, JSON, claves foráneas e índices.
- **Chart.js 4 y QRCode.js:** gráficos y códigos QR. Se cargan desde CDN; para el primer uso se necesita conexión a Internet.

## Requisitos previos

- XAMPP o un entorno equivalente con Apache, PHP 8.1+ y MySQL 8.0+.
- Extensiones PHP `pdo_mysql`, `mbstring` y `json`.
- Navegador actualizado.
- Acceso a Internet para cargar Bootstrap, Chart.js y QRCode.js desde sus CDN.
- Para enviar correos desde el sistema, una configuración de correo compatible con `mail()` de PHP. La aplicación guarda el resultado del envío; en instalaciones locales se puede usar MailHog o el servicio SMTP del entorno.

## Instalación en XAMPP

1. Descomprimí la carpeta `SGEP-Dashboard` dentro de `C:\xampp\htdocs`. El proyecto debe quedar en `C:\xampp\htdocs\SGEP-Dashboard`.
2. Abrí el panel de XAMPP e iniciá Apache y MySQL.
3. Abrí `http://localhost/phpmyadmin`.
4. Elegí **Importar**, seleccioná `SGEP-Dashboard/database/sgep.sql` y confirmá. El archivo crea la base `sgep_dashboard`, sus tablas y registros de demostración.
5. Revisá `SGEP-Dashboard/config/database.php`. La configuración inicial usa `127.0.0.1`, puerto `3306`, base `sgep_dashboard`, usuario `root` y contraseña vacía, que son los valores habituales de XAMPP.
6. Si tu instalación usa otras credenciales, actualizá `host`, `port`, `database`, `username` y `password` en ese archivo. También podés establecer las variables de entorno `SGEP_DB_HOST`, `SGEP_DB_PORT`, `SGEP_DB_NAME`, `SGEP_DB_USER` y `SGEP_DB_PASSWORD`.
7. Abrí **`http://localhost/SGEP-Dashboard/public/`**.

El directorio `public/` es la raíz web. En un servidor compartido, configurá Apache para que apunte allí y evitá publicar `config/`, `app/`, `database/` o `scripts/`.

## Configuración de correo y enlaces

En `config/app.php` podés ajustar:

- `base_url`: dirección pública completa del directorio `public`, por ejemplo `http://localhost/SGEP-Dashboard/public`. Se usa al generar enlaces para correos y recordatorios.
- `mail_from`: dirección remitente usada por `mail()`.
- `timezone`: zona horaria de fechas y recordatorios.

También se aceptan las variables de entorno `SGEP_BASE_URL`, `SGEP_MAIL_FROM` y `SGEP_TIMEZONE`.

Las invitaciones se envían inmediatamente con `mail()`. Si la configuración local no puede entregar correo, la invitación queda marcada con error y se puede volver a intentar. Los recordatorios quedan guardados en MySQL; para enviarlos, configurá una tarea programada que ejecute desde la carpeta del proyecto:

```text
php scripts/send_reminders.php
```

En Windows, esa tarea se puede crear con el Programador de tareas. En Linux, se puede ejecutar mediante cron. La tarea procesa hasta 200 recordatorios vencidos por ejecución. Revisá `base_url` antes de habilitarla.

## Usuarios de prueba

Todas las cuentas iniciales usan la contraseña **`password`**. Cambiala desde la gestión de usuarios después de iniciar sesión.

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@sgep.local` | `password` |
| Responsable de proyecto | `pm@sgep.local` | `password` |
| Auditor | `auditor@sgep.local` | `password` |

Una cuenta solicitada desde la pantalla de ingreso se crea como Auditor inactivo. Un administrador debe habilitarla antes de que pueda iniciar sesión. Las personas encuestadas no necesitan crear una cuenta.

## Permisos por rol

- **Administrador:** administra usuarios, proyectos, responsables y encuestas; consulta resultados y dashboard; distribuye encuestas y exporta reportes.
- **Responsable de proyecto (PM):** trabaja únicamente con proyectos que tiene asignados; crea y edita proyectos propios, prepara y publica encuestas, distribuye enlaces, registra recordatorios, consulta resultados y exporta.
- **Auditor:** consulta proyectos, dashboard, encuestas publicadas, resultados y exportaciones. No modifica información.
- **Persona encuestada:** responde desde el enlace o QR sin iniciar sesión. Si ya tiene sesión iniciada, se registra el indicador correspondiente. En encuestas anónimas no se guarda su `user_id`.

## Recorrido de comprobación

1. Iniciá sesión como Administrador.
2. Revisá los tres proyectos y las encuestas de demostración en el dashboard.
3. Creá un proyecto y asigná uno o más responsables PM.
4. Creá una encuesta, cargá una plantilla y reordená preguntas con el control de arrastre.
5. Configurá una pregunta obligatoria y, si corresponde, una condición basada en una respuesta previa.
6. Guardá el borrador, publicalo y abrí **Distribuir** para copiar el enlace o mostrar el QR.
7. Respondé desde una ventana privada para generar una respuesta no registrada. Para comprobar invitaciones, respondé desde el enlace individual recibido por correo.
8. Consultá Resultados y aplicá fechas.
9. Exportá CSV compatible con Excel o abrí el reporte para imprimirlo o guardarlo como PDF desde el navegador.
10. Iniciá sesión como PM y Auditor para comprobar el alcance del rol y las pantallas de solo lectura.

## Estructura del proyecto

```text
SGEP-Dashboard/
├── app/                 Lógica de autenticación, proyectos, encuestas y analítica
├── config/              Conexión MySQL y parámetros generales
├── database/sgep.sql    Esquema y datos iniciales
├── docs/                Manuales y diagramas
├── public/              Raíz web, pantallas, API y recursos
│   ├── assets/          JavaScript y CSS
│   ├── api.php           Endpoints REST
│   ├── index.php         Panel de gestión
│   ├── report.php        Reporte imprimible de encuesta
│   ├── report_dashboard.php Reporte imprimible del dashboard
│   └── survey.php        Respuesta pública de encuesta
└── scripts/             Tareas de envío programado de recordatorios
```

## Datos y criterios del modelo

El esquema conserva las entidades `usuario`, `proyecto`, `encuesta`, `pregunta`, `respuesta` y `visitas` del diagrama. `proyecto_responsable` resuelve la relación de responsables; `plantilla`, `participacion`, `invitacion` y `recordatorio` completan los requisitos de distribución, plantillas y agrupación de respuestas.

En el diagrama original, `respuesta` apuntaba a una pregunta individual, pero no permitía agrupar las respuestas de una misma encuesta completada. `participacion` agrupa esas filas; cada fila de `respuesta` continúa vinculada a su pregunta e incluye `title`, `datos`, `anonimo`, `registrado` y fecha. Las claves primarias son `AUTO_INCREMENT` y las relaciones se implementan con claves foráneas.

La relación usuario–proyecto del diagrama no especificaba cardinalidad o atributos de asignación. Se implementa como una relación muchos a muchos mediante `proyecto_responsable`, que permite varios responsables por proyecto.

## Limitaciones operativas

- El correo depende de que PHP pueda usar `mail()` o de una configuración SMTP compatible. Sin ese servicio, el resto del sistema funciona y los errores de envío se registran.
- La exportación PDF abre una versión imprimible; seleccioná **Guardar como PDF** en el diálogo del navegador.
- Bootstrap, gráficos y QR se sirven desde CDN. Si el equipo está sin conexión, las funciones de formularios y API siguen disponibles, pero el estilo Bootstrap, los gráficos o QR pueden no cargarse.

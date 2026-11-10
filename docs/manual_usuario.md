# Manual de usuario

## Acceso

Abrí `http://localhost/SGEP-Dashboard/public/`. Las credenciales iniciales se encuentran en el README. Al entrar, SGEP muestra un dashboard conectado con proyectos y respuestas guardados en MySQL.

Las cuentas nuevas se solicitan desde la pantalla de acceso. Se asigna el rol Auditor y la cuenta queda inactiva hasta que un administrador la habilite. La persona que recibe una encuesta puede contestarla sin registro ni inicio de sesión.

## Administrador

### Dashboard

El dashboard muestra NPS, avance percibido, cumplimiento y participación sobre las invitaciones registradas. Filtrá por proyecto, intervalo de fechas, cliente, responsable o tipo de encuesta. Los gráficos, el mapa de calor y el semáforo se actualizan con los filtros actuales.

Usá **Excel / CSV** para descargar un informe compatible con hojas de cálculo. **PDF** abre el informe de impresión del navegador; elegí **Guardar como PDF**.

### Proyectos

1. Abrí **Proyectos** y seleccioná **Crear proyecto**.
2. Completá nombre, cliente, descripción, fechas, estado, avance y cumplimiento.
3. Asigná uno o más usuarios PM.
4. Usá **Ver** para consultar datos y encuestas asociadas. **Editar** permite ajustar el proyecto; **Eliminar** está disponible para Administradores y también elimina sus encuestas dependientes.

### Encuestas

1. Abrí **Encuestas** y seleccioná **Crear encuesta**.
2. Elegí el proyecto, tipo, privacidad y acceso público.
3. Aplicá una plantilla si corresponde o armá las preguntas manualmente.
4. Seleccioná tipo de pregunta, obligatoriedad y métrica. Las preguntas de opciones permiten cargar una opción por línea.
5. Arrastrá las tarjetas para cambiar el orden.
6. Para mostrar una pregunta condicional, seleccioná una pregunta previa y el valor de respuesta que la habilita.
7. Guardá el borrador. Publicalo cuando esté listo para recibir respuestas.

Una encuesta anónima no guarda el usuario que responde, aunque esa persona tenga una sesión. El indicador `registrado` conserva si la sesión estaba activa. Una encuesta no anónima requiere sesión y registra el usuario.

### Distribuir y recordar

En la lista de encuestas, abrí **Distribuir** para copiar el enlace, mostrar el QR o enviar invitaciones a direcciones separadas por coma o línea. El enlace base es único por encuesta; el sistema genera un enlace individual por invitación para contabilizar su respuesta.

Los recordatorios se agregan a una cola local y pueden programarse para una fecha y hora. La tarea `scripts/send_reminders.php` procesa los recordatorios vencidos. Configurá correo y URL pública antes de programar la tarea.

### Resultados

Seleccioná una encuesta en **Resultados**. Podés filtrar por fecha, consultar distribuciones por pregunta, alternar entre barras y anillo, revisar respuestas abiertas y exportar.

**Excel / CSV** descarga un archivo que se abre en Excel. **PDF** abre un informe imprimible que podés guardar en PDF.

### Usuarios

Desde **Usuarios**, el Administrador crea y edita cuentas, elige uno de los tres roles permitidos, activa o desactiva cuentas y define contraseñas. Las contraseñas se almacenan con `password_hash()`. El sistema mantiene al menos un Administrador activo y evita que la persona que administra se desactive a sí misma.

## Responsable de proyecto

El PM ve únicamente los proyectos que tiene asignados. Puede crear proyectos, editar sus proyectos, preparar y publicar encuestas, distribuirlas, programar recordatorios y consultar resultados y reportes de esos proyectos. La asignación de responsables la administra el Administrador.

El PM no puede eliminar proyectos ni gestionar usuarios. Si deja de ser responsable de un proyecto, pierde acceso a su información.

## Auditor

El Auditor tiene acceso de consulta. Puede filtrar el dashboard, revisar proyectos y encuestas, consultar resultados y exportar reportes. No puede editar proyectos, encuestas ni usuarios.

## Persona que responde

1. Abrí el enlace recibido o escaneá el QR.
2. Respondé las preguntas visibles. Las preguntas obligatorias están señaladas.
3. Seleccioná **Enviar respuestas**.
4. Al confirmar, la participación se guarda en el sistema.

Las reglas condicionales muestran u ocultan preguntas según respuestas anteriores. Si el enlace requiere acceso autenticado, iniciá sesión con una cuenta activa.

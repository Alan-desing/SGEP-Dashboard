# Diagramas SGEP

## Casos de uso y permisos

El diagrama inicial se complementa con Auditor, que aparece en la consigna. La persona encuestada puede responder sin cuenta; el alta de una cuenta administrativa se asigna como Auditor inactivo hasta habilitación.

```mermaid
flowchart LR
  Respondente[Persona encuestada]
  PM[Responsable de proyecto]
  Admin[Administrador]
  Auditor[Auditor]
  Encuesta[Responder encuesta]
  Proyectos[Gestionar proyectos asignados]
  Constructor[Crear y editar encuestas]
  Distribuir[Distribuir y recordar]
  Resultados[Consultar resultados]
  Dashboard[Consultar dashboard]
  Exportar[Exportar informes]
  Usuarios[Gestionar usuarios y roles]
  Alta[Solicitar cuenta Auditor]

  Respondente --> Encuesta
  PM --> Alta
  PM --> Proyectos
  PM --> Constructor
  PM --> Distribuir
  PM --> Resultados
  PM --> Dashboard
  PM --> Exportar
  Admin --> Alta
  Admin --> Proyectos
  Admin --> Constructor
  Admin --> Distribuir
  Admin --> Resultados
  Admin --> Dashboard
  Admin --> Exportar
  Admin --> Usuarios
  Auditor --> Alta
  Auditor --> Resultados
  Auditor --> Dashboard
  Auditor --> Exportar
```

## Flujo de publicación y respuesta

```mermaid
flowchart TD
  A[Usuario autorizado inicia sesión] --> B[Selecciona un proyecto]
  B --> C[Elige plantilla o crea encuesta]
  C --> D[Agrega y ordena preguntas]
  D --> E[Configura obligatoriedad, métricas y condiciones]
  E --> F[Guarda borrador en MySQL]
  F --> G{¿Lista para distribuir?}
  G -- No --> C
  G -- Sí --> H[Publica encuesta]
  H --> I[Genera enlace y QR]
  I --> J[Comparte enlace o envía invitaciones]
  J --> K[Persona responde]
  K --> L{¿Encuesta anónima?}
  L -- Sí --> M[Guarda respuestas sin user_id]
  L -- No --> N[Valida sesión y registra usuario]
  M --> O[Guarda participación, respuestas y fecha]
  N --> O
  O --> P[Actualiza dashboard y resultados]
  P --> Q[Consulta y exporta reporte]
```

## Cronograma de referencia

```mermaid
gantt
  title Plan de implementación SGEP
  dateFormat YYYY-MM-DD
  axisFormat %d/%m
  section Análisis
  Revisión de consigna y diagramas :done, analisis, 2026-10-28, 1d
  Definición del modelo relacional :done, modelo, after analisis, 1d
  section Desarrollo
  Base de datos y autenticación :done, base, after modelo, 2d
  Proyectos y permisos :done, proyectos, after base, 2d
  Constructor y distribución :done, encuestas, after proyectos, 3d
  Dashboard, resultados y reportes :done, analitica, after encuestas, 3d
  section Entrega
  Manuales y guía de instalación :done, docs, after analitica, 1d
  Revisión de integración :active, revision, after docs, 1d
```

## Relaciones del modelo

```mermaid
erDiagram
  USUARIO ||--o{ PROYECTO_RESPONSABLE : asigna
  PROYECTO ||--o{ PROYECTO_RESPONSABLE : tiene
  PROYECTO ||--o{ ENCUESTA : contiene
  PLANTILLA o|--o{ ENCUESTA : inicia
  USUARIO o|--o{ ENCUESTA : crea
  ENCUESTA ||--o{ PREGUNTA : incluye
  PREGUNTA o|--o{ PREGUNTA : condiciona
  ENCUESTA ||--o{ PARTICIPACION : recibe
  USUARIO o|--o{ PARTICIPACION : identifica
  PARTICIPACION ||--o{ RESPUESTA : agrupa
  PREGUNTA ||--o{ RESPUESTA : contesta
  ENCUESTA ||--o{ INVITACION : distribuye
  INVITACION o|--o{ RECORDATORIO : recuerda
  USUARIO ||--o{ VISITAS : consulta
  PROYECTO ||--o{ VISITAS : registra
```

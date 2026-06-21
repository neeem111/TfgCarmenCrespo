# TfgCarmenCrespo
# TFG — Requisitos de calidad software para la publicación de plugins en Moodle

> **Autora:** Carmen Crespo Navarro  
> **Tutor:** Jesús Damián García-Consuegra Bleda  
> **Universidad:** Universidad de Castilla-La Mancha — Escuela Superior de Ingeniería Informática  
> **Titulación:** Grado en Ingeniería Informática (Tecnologías de la Información)  
> **Curso:** 2025–2026  


## ¿De qué trata este repositorio?

Este repositorio contiene las pruebas desarrollados en el Trabajo Fin de Grado cuyo objetivo es **analizar los requisitos de calidad software exigidos por Moodle para la publicación de plugins y aplicarlos sobre el plugin mod_sqlab**.

El trabajo propone una metodología de evaluación reproducible basada íntegramente en las herramientas del ecosistema oficial de Moodle (PHPUnit, Behat, Selenium, phpcs, phplint), aplicable a cualquier plugin de tipo *módulo de actividad*.

## Estructura del repositorio
TfgCarmenCrespo/
├── README.md                          ← Este fichero
├── tests/
│   ├── mod_sqlab_version_test.php     ← UNI-02: Instalación, version.php, instanciación
│   ├── mod_sqlab_privacy_test.php     ← UNI-03: Privacy API
│   ├── mod_sqlab_backup_test.php      ← UNI-04: Backup & Restore API
│   ├── mod_sqlab_structure_test.php   ← UNI-05: Estructura de ficheros obligatorios
│   ├── behat/
│   │   ├── FUN-01_sqlab_acceso_actividad.feature
│   │   ├── FUN-02_sqlab_acceso_actividad_sqlab.feature
│   │   ├── FUN-03_sqlab_instalacion.feature
│   │   ├── FUN-04_sqlab_enunciado_resultados.feature
│   │   ├── FUN-05_admin_sqlab_crear_actividad.feature
│   │   ├── FUN-06_student_consulta_sqlab_feedback.feature
│   │   ├── FUN-07_sqlab_navegacion_preguntas.feature
│   │   └── FUN-08_sqlab_puntuacion.feature
│   └── generator/
│       └── lib.php                    ← Generador de datos para Behat
└── resultados/
    ├── fichas_behat_FUN01_FUN08.md    ← Fichas documentadas de los 21 escenarios Behat
    ├── fichas_phpunit_UNI02_UNI05.md  ← Fichas documentadas de los 16 métodos PHPUnit
    ├── phpunit_output.txt             ← Salida de ejecución PHPUnit (servidor tutor)
    ├── behat_output.txt               ← Salida de ejecución Behat (servidor tutor)
    ├── phpcs_output.txt               ← Salida de phpcs (servidor tutor)
    └── capturas/                      ← Capturas de pantalla de la ejecución

## Entorno de pruebas

Las pruebas están diseñadas para ejecutarse sobre **moodle-docker** (repositorio oficial de MoodleHQ), que proporciona todos los servicios necesarios en contenedores Docker:

## Requisitos previos

- Docker instalado en el servidor
- Repositorio [moodle-docker](https://github.com/moodlehq/moodle-docker) clonado y configurado
- Plugin mod_sqlab instalado en la instancia de Moodle
- Los ficheros de tests/ copiados en mod/sqlab/tests/ dentro de la instalación de Moodle

## Cobertura de pruebas

### PHPUnit (16 métodos)

| Suite | ID | Requisito verificado |
|---|---|---|
| UNI-02 | a–d | Instalación, `version.php`, Frankenstyle, tabla BD |
| UNI-03 | a–c | Privacy API (approval blocker si falta) |
| UNI-04 | a–c | Backup & Restore API (approval blocker si falta) |
| UNI-05 | a–f | Estructura ficheros, `lib.php`, seguridad, idioma |

### Behat (21 escenarios)

| Feature | Escenarios | Cubre |
|---|---|---|
| FUN-01 | 3 | Acceso al curso, roles, capabilities |
| FUN-02 | 2 | Acceso a la actividad SQLab |
| FUN-03 | 3 | Instalación y ausencia de errores PHP |
| FUN-04 | 3 | Enunciado, resultado esperado, editor SQL |
| FUN-05 | 1 | Creación de actividad por el admin |
| FUN-06 | 3 | Ejecución de consultas y feedback |
| FUN-07 | 3 | Navegación entre preguntas |
| FUN-08 | 3 | Visualización de puntuación |

## Notas de adaptación para el plugin definitivo

Algunos escenarios Behat contienen valores que pueden necesitar ajuste en el plugin final. Consultar los comentarios `# NOTA PARA EL TUTOR:` en cada fichero `.feature` y la Sección III.9.2 del Anexo III de la memoria.

## Licencia

Las pruebas desarrolladas en este trabajo se distribuyen bajo los términos de la [GNU General Public License v3](https://www.gnu.org/licenses/gpl-3.0.html), en consonancia con los requisitos de licenciamiento del ecosistema Moodle.

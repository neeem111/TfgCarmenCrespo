# TFG — Requisitos de calidad software en Moodle

> **Autora:** Carmen Crespo Navarro
> **Tutor:** Jesús Damián García-Consuegra Bleda
> **Universidad:** Universidad de Castilla-La Mancha — Escuela Superior de Ingeniería Informática
> **Titulación:** Grado en Ingeniería Informática (Tecnologías de la Información)
> **Curso:** 2025–2026

---

## ¿De qué trata este repositorio?

Este repositorio contiene las pruebas y los resultados cuyo objetivo es **analizar los requisitos de calidad software exigidos por Moodle para la publicación de plugins y aplicarlos sobre el plugin `mod_sqlab`**.

El trabajo propone una metodología de evaluación reproducible basada en las herramientas del ecosistema oficial de Moodle (phplint, phpcs, PHPUnit) y, para la validación funcional, en **Selenium WebDriver**, aplicable a cualquier plugin de tipo *módulo de actividad*.

## Estructura del repositorio
TfgCarmenCrespo/
├── README.md
├── tests/
│   ├── phpunit/                                  ← Suites PHPUnit (UNI-02 a UNI-06)
│   │   ├── mod_sqlab_version_test.php            ← UNI-02: instalación, version.php, instanciación
│   │   ├── mod_sqlab_privacy_test.php            ← UNI-03: Privacy API
│   │   ├── mod_sqlab_backup_test.php             ← UNI-04: Backup & Restore API
│   │   ├── mod_sqlab_structure_test.php          ← UNI-05: estructura de ficheros y seguridad
│   │   └── mod_sqlab_integration_test_v2.php     ← UNI-06: lógica de ejecución de consultas SQL
│   ├── behat/                                    ← Escenarios Behat (DISEÑO, no ejecutados)
│   │   ├── FUN-01_sqlab_acceso_actividad.feature
│   │   ├── FUN-02_sqlab_acceso_actividad_sqlab.feature
│   │   ├── FUN-03_sqlab_instalacion.feature
│   │   ├── FUN-04_sqlab_enunciado_resultados.feature
│   │   ├── FUN-05_admin_sqlab_crear_actividad.feature
│   │   ├── FUN-06_student_consulta_sqlab_feedback.feature
│   │   ├── FUN-07_sqlab_navegacion_preguntas.feature
│   │   ├── FUN-08_sqlab_puntuacion.feature
│   │   ├── FUN-09_sqlab_diccionario_datos.feature
│   │   └── FUN-10_sqlab_entorno_colaborativo.feature
│   └── selenium/                                 ← Validación funcional REAL (FUN-01..10, FUN-12)
│       ├── selenium_FUN01.py
│       ├── selenium_FUN02.py
│       ├── selenium_FUN03.py
│       ├── selenium_FUN04.py
│       ├── selenium_FUN05.py
│       ├── selenium_FUN06.py
│       ├── selenium_FUN07.py
│       ├── selenium_FUN08.py
│       ├── selenium_FUN09.py
│       ├── selenium_FUN10.py
│       ├── selenium_FUN12.py
│       └── chromedriver.exe
└── resultados/
    ├── estaticas/
    │   ├── syntax_check.log                      ← Salida de phplint (php -l) sobre el plugin
    │   └── resultado_phpcs.txt                   ← Salida de phpcs (estándar moodle-cs)
    └── phpunit/
        └── phpunit_definitivo_20260615.txt       ← Salida PHPUnit (servidor del tutor, UNI-02..06)

### Validación estática

| Prueba | Herramienta | Alcance | Resultado registrado |
|---|---|---|---|
| SIN-01 | phplint (`php -l`) | Todos los ficheros PHP del plugin | 2 errores fatales (`moodle_interface.php`, `sqldb_manager.php`); el resto sin errores de sintaxis |
| EST-01 | phpcs (estándar `moodle-cs`) | Ficheros de `classes/` | 507 errores y 77 *warnings* en 5 ficheros — no superada |

### Validación unitaria (PHPUnit)

| Suite | Fichero | Requisito verificado | Resultado registrado |
|---|---|---|---|
| UNI-02 | `mod_sqlab_version_test.php` | Instalación, `version.php`, tabla BD, instanciación | 5 PASS, 1 *skipped* (instanciación sin generador) |
| UNI-03 | `mod_sqlab_privacy_test.php` | Privacy API | 5 PASS |
| UNI-04 | `mod_sqlab_backup_test.php` | Backup & Restore API (*approval blocker*) | 3 FAIL, 2 *skipped* |
| UNI-05 | `mod_sqlab_structure_test.php` | Estructura de ficheros y seguridad | 4 PASS, 2 FAIL (`upgrade.php`, `required_param()`) |
| UNI-06 | `mod_sqlab_integration_test_v2.php` | Lógica de ejecución de consultas SQL | 2 PASS, 2 *skipped* (requieren PostgreSQL externo) |

### Validación funcional (Selenium — FUN-01 a FUN-10 y FUN-12)

| Script | FUN | Casos de uso | Escenarios | Usuarios |
|---|---|---|---|---|
| `selenium_FUN01.py` | FUN-01 | CU-00, CU-07 | Acceso al curso · profesor activa edición · estudiante no edita | student1, carmenprof |
| `selenium_FUN02.py` | FUN-02 | CU-01 | Actividad visible · abrir sin errores PHP | student1 |
| `selenium_FUN03.py` | FUN-03 | Instalación | Plugin en módulos · sin errores PHP · habilitado | admin |
| `selenium_FUN04.py` | FUN-04 | CU-02 | Enunciado · resultados esperados · editor SQL | student1 |
| `selenium_FUN05.py` | FUN-05 | CU-08 | SQLab en el selector · campos del formulario | carmenprof |
| `selenium_FUN06.py` | FUN-06 | CU-03, CU-04 | Ejecutar · evaluar · SQL inválido sin error PHP | student1 |
| `selenium_FUN07.py` | FUN-07 | CU-05 | Sidebar P1/P2 · avanzar · volver | student1 |
| `selenium_FUN08.py` | FUN-08 | CU-06 | «Puntúa como» · evaluar · SQL inválido | student1 |
| `selenium_FUN09.py` | FUN-09 | CU-09 | Botón diccionario · menú sin error · snippet en editor | student1 |
| `selenium_FUN10.py` | FUN-10 | CU-10, CU-11, CU-12 | Elementos colaborativos (1 usuario) · dos usuarios simultáneos | student1, student2 |
| `selenium_FUN12.py` | FUN-12 | Integral | Flujo colaborativo completo entre dos usuarios | student1, student2 |

## Resultados

Las salidas de la ejecución sobre el plugin se encuentran en la carpeta `resultados/`:

- `resultados/estaticas/syntax_check.log` — salida de phplint.
- `resultados/estaticas/resultado_phpcs.txt` — salida de phpcs.
- `resultados/phpunit/phpunit_definitivo_20260615.txt` — salida de las suites UNI-02 a UNI-06 ejecutadas.

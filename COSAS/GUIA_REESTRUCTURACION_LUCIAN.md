# GUÍA COMPLETA DE REESTRUCTURACIÓN — ESTILO LUCIAN
## Qué cambiar AHORA mientras esperas resultados Selenium / PHPUnit v2

---

## PREGUNTA 1: ¿Dónde va "todo el rollo de Behat"?

**Respuesta corta:** El detalle de Behat ya está en el Anexo II (perfecto, quédalo ahí). En el Capítulo 5 solo va:
- 1 párrafo de motivación (por qué se diseñó con Behat)
- 1 figura del error del generador
- Tabla comprimida a 3 filas (no 10)
- 1 párrafo de pivot: "esto llevó a Selenium"

**Justificación:** Lucian describe su test de 2 usuarios (equivalente a tus Behat) en 1 página con 4 figuras. No tiene una sección separada para "tests que no corrieron". Sus tests fallidos los menciona en 2 líneas ("caused CPU spikes") y pasa directamente a la solución. Tú debes hacer lo mismo.

| Contenido Behat | Dónde va |
|-----------------|----------|
| Cómo diseñar escenarios .feature | Anexo II (ya está ✔) |
| Comandos de ejecución | Anexo II (ya está ✔) |
| Estructura de archivos .feature | Anexo II (ya está ✔) |
| Error del generador (figura) | Cap 5 sección 5.7.1 |
| Tabla de resultados (3 filas) | Cap 5 sección 5.7.1 |
| Los 10 .feature individuales | Repositorio GitHub + referencia en Anexo III |
| Casos de uso (Tabla 5, 12 filas) | Mover al Anexo II o III |

---

## ESTRUCTURA OBJETIVO DEL CAPÍTULO 5 (siguiendo a Lucian)

### Comparación visual:

```
LUCIAN Cap 5                         CARMEN Cap 5 OBJETIVO
─────────────────────────────        ────────────────────────────────
5.1 Introduction (1 pág)            5.1 Intro + Figura 1 (1.5 pág)
5.2 Overview iterations (0.5 pág)   5.2 Descripción del plugin (0.5 pág)
5.3 Prototype 1 (1.5 pág)          5.3 Diseño estrategia (0.5 pág)
5.4 Prototype 2 (1.5 pág)          5.4 Configuración del entorno (1.5 pág)
5.5 Prototype 3 (1 pág)            5.5 Diseño metodología verificación (0.5 pág)
5.6 Prototype 4 (1.5 pág)          5.6 Validación estática (1.5 pág)
5.7 Final Prototype (0.5 pág)       5.7 Validación dinámica (4 págs)
5.8 Testing (5 págs + 9 figuras)    5.8 Cumplimiento (1.5 pág)
                                    5.9 Propuesta mejoras (1 pág)
                                    5.10 Conclusiones (1 pág)
```

**El equivalente de 5.8 Testing de Lucian (su sección más larga) es tu 5.7 (validación dinámica).** Ahí es donde van las figuras de evidencias.

---

## CAMBIOS SECCIÓN POR SECCIÓN (texto exacto para hacer ahora)

---

### 5.1 — Introducción y estrategia de evaluación

**LO QUE TIENES:** Texto con 2 fases (ya actualizado a 3 en el doc anterior). Sin figura.

**LO QUE AÑADES AHORA:**

1. Pega la Figura 1 (el diagrama SVG de 3 fases) después del párrafo de las 3 fases.
   Pie de figura: `Figura 1. Estrategia iterativa de evaluación de mod_sqlab. Elaboración propia.`

2. Al final de 5.1, añade este párrafo de cierre (conecta con lo que viene):

> Las secciones 5.6 y 5.7 presentan los resultados concretos de cada fase. La sección 5.6 recoge los resultados de la validación estática, aplicable a las tres fases. La sección 5.7 recoge los resultados de la validación dinámica, donde cada subsección documenta tanto los hallazgos obtenidos como las decisiones arquitectónicas que motivaron el paso a la siguiente fase.

---

### 5.6 — Resultados de la validación estática

**LO QUE TIENES:** Tablas 3 y 4 con resultados de phplint y phpcs. Sin figuras.

**LO QUE AÑADES AHORA:**

**FIGURA 2** — Output de phplint como bloque de código en Word:

Inserta ANTES de la Tabla 3 este bloque (fuente Courier New, tamaño 9, fondo gris claro):

```
$ find /var/www/html/mod/sqlab/ -name '*.php' -exec php -l {} \;

No syntax errors detected in classes/grader.php
No syntax errors detected in classes/attempt_manager.php
...
PHP Parse error: syntax error, unexpected token "const",
  expecting variable (T_VARIABLE) in
  /var/www/html/mod/sqlab/classes/moodle_interface.php on line 29

PHP Parse error: syntax error, unexpected token "const" in
  /var/www/html/mod/sqlab/classes/sqldb_manager.php on line 28

Errors parsing: 2 files with fatal errors. 42 files OK.
```

Pie: `Figura 2. Output de validación sintáctica con phplint sobre mod_sqlab.`

**FIGURA 3** — Output parcial de phpcs como bloque de código:

Inserta ANTES de la Tabla 4:

```
FILE: /var/www/html/mod/sqlab/classes/moodle_interface.php
-----------------------------------------------------------------------
FOUND 174 ERRORS AND 32 WARNINGS
-----------------------------------------------------------------------
  8 | ERROR | Missing file doc comment
  8 | ERROR | GPL boilerplate not found
 29 | ERROR | Constant expression contains invalid operations
 45 | ERROR | error_log() usage detected; use debugging() instead
...

FILE: /var/www/html/mod/sqlab/classes/chat_manager.php
-----------------------------------------------------------------------
FOUND 100 ERRORS AND 22 WARNINGS
-----------------------------------------------------------------------
  1 | ERROR | @package tag 'mod_sqlab\chat' incorrect format
...

SUMMARY: 5 files checked. 507 errors, 77 warnings. NOT PASSED.
```

Pie: `Figura 3. Output parcial del análisis de estilo con phpcs (estándar moodle-cs) sobre mod_sqlab.`

**Añade esta frase introductoria antes de la Tabla 4** (donde actualmente dice solo los resultados):

> El análisis reveló que ninguno de los ficheros incluye el boilerplate GPL obligatorio y que varias funciones de gestión de errores usan `error_log()` en lugar de la función nativa de Moodle `debugging()`. Este último constituye un fallo de seguridad que actúa como approval blocker según la política de publicación. El detalle completo de resultados por fichero se muestra en la Tabla 4.

---

### 5.7.1 — Pruebas funcionales con Behat

**LO QUE TIENES:** Tabla 5 (12 casos de uso) + Tabla 6 (10 filas de resultados) + nota al pie.

**CAMBIOS A HACER AHORA:**

**PASO 1:** Mueve la Tabla 5 (casos de uso) al Anexo II, sección II.5 o al Anexo III. En su lugar pon una línea:
> Los casos de uso que guiaron el diseño de los escenarios se recogen en el Anexo III, tabla X.

**PASO 2:** Añade FIGURA 4 (error del generador) antes de la Tabla 6:

```
coding_exception: Coding error detected, it must be fixed by a programmer:
Component mod_sqlab does not support generators yet.
Missing tests/generator/lib.php.

Stack trace:
  /var/www/html/lib/testing/generator/data_generator.php:1528
  /var/www/html/lib/testing/generator/data_generator.php:132
  /var/www/html/lib/testing/generator/data_generator.php:502
  /var/www/html/mod/sqlab/tests/mod_sqlab_version_test.php:72
  /var/www/html/lib/phpunit/classes/advanced_testcase.php:81
```

Pie: `Figura 4. Error producido al intentar ejecutar escenarios Behat sin generador de datos implementado.`

**PASO 3:** Sustituye la Tabla 6 actual (10 filas) por esta versión comprimida:

| ID | Descripción | Resultado | Motivo |
|----|-------------|-----------|--------|
| FUN-01 a FUN-09 | Escenarios funcionales (usabilidad, SQL, interfaz, navegación, diccionario) | No ejecutable | Generador de datos ausente. Error: ver Figura 4. |
| FUN-10 | Entorno colaborativo (dos usuarios simultáneos) | No ejecutable (Behat) + Validado con Selenium (§5.7.3) | Generador ausente + Behat: 1 solo hilo, incapaz de sesiones paralelas |

**PASO 4:** Elimina la nota al pie ("Fun 2 a 8 dependen de generador...") y sustitúyela por este párrafo de cierre:

> El análisis de la imposibilidad de ejecutar FUN-10 con Behat reveló una limitación arquitectónica fundamental: Behat opera con un único hilo de ejecución y una sola instancia de WebDriver, lo que hace técnicamente imposible simular la presencia simultánea de dos usuarios en la misma sala colaborativa. Este hallazgo —confirmado por el tutor con la indicación de usar Selenium directamente— constituyó el punto de inflexión metodológico: en lugar de documentar FUN-10 como "no testeable", se adoptó Selenium standalone con hilos independientes como solución que sí permite la simultaneidad real (sección 5.7.3).

---

### 5.7.2 — Pruebas unitarias con PHPUnit

**LO QUE TIENES:** Tabla 7 con UNI-02 a UNI-05. Sin figuras. Sin párrafo de incidencias.

**CAMBIOS A HACER AHORA:**

**PASO 1:** Añade FIGURA 5 (output del tutor) ANTES de la Tabla 7:

```
Moodle 4.3.3 (Build: 20240212)
PHP: 8.2.30, MariaDB: 11.3.2, OS: Linux 6.8.0

mod_sqlab_version_test
 ✔ Plugin is installed
 ✔ Version file has required fields
 ✘ Can create sqlab instance
   │ coding_exception: Component mod_sqlab does not support
   │ generators yet. Missing tests/generator/lib.php.
   │ /var/www/html/mod/sqlab/tests/mod_sqlab_version_test.php:72
 ✔ Database table exists

Tests: 4, Assertions: 7, Errors: 1.
```

Pie: `Figura 5. Output de la suite UNI-02 ejecutada por el tutor en el servidor UCLM (Moodle 4.3.3).`

**PASO 2:** Añade FIGURA 6 (error fatal de --group) después de la Figura 5:

```
$ vendor/bin/phpunit --group mod_sqlab --testdox

PHP Fatal error: Class qtype_sqlquestion\privacy\provider
contains 2 abstract methods and must therefore be declared
abstract or implement the remaining methods
(core_privacy\local\metadata\provider::get_metadata,
core_privacy\local\request\plugin\provider::export_user_preferences)
in /var/www/html/question/type/sqlquestion/classes/privacy/provider.php
on line 42
```

Pie: `Figura 6. Error fatal producido al ejecutar todas las pruebas con --group mod_sqlab. Causa: bug en plugin dependencia qtype_sqlquestion, ajeno al código de mod_sqlab.`

**PASO 3:** Añade el párrafo de incidencias ANTES de la Tabla 7 (después de las figuras):

> La ejecución de las suites en el servidor del tutor reveló dos incidencias de infraestructura documentadas en las Figuras 5 y 6. La primera (Figura 6) es un error fatal en `qtype_sqlquestion\privacy\provider` que impide ejecutar el conjunto de pruebas con `--group mod_sqlab`; su solución es ejecutar cada suite por fichero individual. La segunda (Figura 5) es el `coding_exception` de UNI-02c por ausencia del generador, corregido en la versión v2 del test mediante `markTestSkipped()`. Ambas incidencias son externas al código del plugin evaluado. Los resultados confirman que las suites UNI-03, UNI-04 y UNI-05 se ejecutaron en el entorno local de desarrollo, mientras que UNI-02 fue confirmada en el servidor del tutor.

**PASO 4:** Actualiza la Tabla 7 (cambios del doc CAMBIOS_EXACTOS_CAPITULO5.md):
- UNI-02c: FAILED → SKIPPED (v2)
- Añadir columna "Confirmado en"
- Añadir filas UNI-06

---

### 5.7.3 — Validación colaborativa con Selenium standalone ← NUEVA

**LO QUE TIENES:** Nada (o una línea suelta de "5.8 Selenium").

**LO QUE PONES AHORA** (texto completo del doc CAMBIOS_EXACTOS_CAPITULO5.md, sección CAMBIO 4).

**PLACEHOLDER para las figuras de Selenium** (mientras esperas el servidor):

> [Figura 7 — pendiente: captura Chrome 1 con student2 en sala colaborativa]
> [Figura 8 — pendiente: captura Chrome 2 con student3 en sala colaborativa]  
> [Figura 9 — pendiente: output terminal script Python con 2 hilos]

En Word ponlos como marcadores grises con ese texto. Cuando el tutor solucione los usuarios, ejecutas el script, capturas y sustituyes.

---

### 5.8 — Evaluación del cumplimiento (Tabla 8)

**LO QUE TIENES:** Tabla con columna Estado vacía.

**LO QUE HACES AHORA:** Rellena el Estado con los valores del doc CAMBIOS_EXACTOS_CAPITULO5.md (sección CAMBIO 5).

**Añade este párrafo ANTES de la tabla:**

> La Tabla 8 consolida el estado de cumplimiento de los requisitos obligatorios identificados en el Capítulo 3, cruzados con las pruebas ejecutadas en este trabajo. Los requisitos se clasifican en tres estados: CUMPLE (verificado positivamente), NO CUMPLE (incumplimiento confirmado), y PENDIENTE (validación condicionada a la resolución de la incidencia de usuarios en el servidor). Tres requisitos quedan fuera del alcance por dependencia de infraestructura externa (ver sección 5.8.2).

---

### 5.9 — Propuesta de mejoras

**LO QUE TIENES:** Vacío.

**TEXTO COMPLETO para añadir ahora** (del doc CAMBIOS_EXACTOS_CAPITULO5.md, sección CAMBIO 6).

---

### 5.10 — Conclusiones

**LO QUE TIENES:** Vacío.

**TEXTO COMPLETO para añadir ahora** (del doc CAMBIOS_EXACTOS_CAPITULO5.md, sección CAMBIO 7).

**IMPORTANTE:** Tras escribirlas, vuelve al Capítulo 6 (Conclusiones generales) y en la sección 6.1.1 "Revisión de objetivos específicos" escribe UN PÁRRAFO por cada objetivo de tu lista (los 5 que tienes). Sigue el patrón de Lucian:

> Objetivo 1 — Identificar requisitos. [1 frase de logro] + evidencia (Capítulo 3, Tabla 1). [1 frase de límite].
> Objetivo 2 — Clasificar requisitos. [1 frase] + evidencia (Tabla 8). [límite].
> etc.

---

## ÍNDICE DE FIGURAS ACTUALIZADO

Cuando termines, tu índice de figuras debe quedar así (pégalo en Word):

| Figura | Descripción | Sección |
|--------|-------------|---------|
| Figura 1 | Estrategia iterativa de evaluación (3 fases). Elaboración propia. | 5.1 |
| Figura 2 | Output de phplint sobre mod_sqlab. | 5.6.1 |
| Figura 3 | Output parcial de phpcs (moodle-cs) sobre mod_sqlab. | 5.6.2 |
| Figura 4 | Error de generador ausente al ejecutar escenarios Behat. | 5.7.1 |
| Figura 5 | Output de suite UNI-02 ejecutada por el tutor (servidor UCLM). | 5.7.2 |
| Figura 6 | Error fatal de --group por bug en qtype_sqlquestion. | 5.7.2 |
| Figura 7 | Chrome 1: student2 en sala colaborativa sqlab. [pendiente] | 5.7.3 |
| Figura 8 | Chrome 2: student3 en sala colaborativa sqlab. [pendiente] | 5.7.3 |
| Figura 9 | Output terminal del script selenium_colaborativo_sqlab.py. [pendiente] | 5.7.3 |

Actualmente tienes 2 figuras. Objetivo: 9 figuras. Puedes tener 6 HOY sin esperar al servidor.

---

## TABLA RESUMEN: QUÉ HACER CUÁNDO

| Tarea | Cuándo | Tiempo |
|-------|--------|--------|
| Pegar Figura 1 en Word (SmartArt o imagen del SVG) | HOY | 5 min |
| Mover Tabla 5 (casos de uso) al Anexo | HOY | 5 min |
| Comprimir Tabla 6 Behat a 3 filas | HOY | 10 min |
| Añadir párrafo pivot Behat → Selenium | HOY | 10 min |
| Añadir Figura 2 (output phplint) | HOY | 10 min |
| Añadir Figura 3 (output phpcs parcial) | HOY | 10 min |
| Añadir Figura 4 (error generador) | HOY | 5 min |
| Añadir Figura 5 (output tutor UNI-02) | HOY | 5 min |
| Añadir Figura 6 (error fatal --group) | HOY | 5 min |
| Añadir párrafo incidencias PHPUnit | HOY | 10 min |
| Añadir sección 5.7.3 Selenium (con placeholders) | HOY | 20 min |
| Rellenar Tabla 8 | HOY | 15 min |
| Escribir 5.9 y 5.10 | HOY | 30 min |
| Completar Cap 6 con objetivos mapeados | HOY | 30 min |
| **Figuras 7-8-9 Selenium** | Cuando tutor resuelva usuarios | 10 min |
| **Tabla 7 UNI-06 confirmada** | Cuando tutor confirme v2 | 10 min |

**Total hoy: ~2.5 horas. Resultado: de 2 figuras a 6 figuras, capítulo 5 completo excepto capturas de Selenium.**

---

## SOBRE LOS ANEXOS — NADA QUE CAMBIAR

Tus Anexos II y III ya están muy bien estructurados. Lucian tiene su Annex III con solo 3 páginas. El tuyo es más detallado y eso está bien — es tu aportación metodológica. Solo añade en Anexo III, Tabla 17 (Entregables) las 4 filas nuevas del doc CAMBIOS_EXACTOS_CAPITULO5.md.

El Anexo II con los comandos Docker y la guía de Behat es MEJOR que lo que tiene Lucian. No toques nada ahí.

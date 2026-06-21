# GUÍA DEFINITIVA — QUÉ CAMBIAR EN TU TFG
## Comparativa con la memoria de Lucian (10/10)

---

## DIAGNÓSTICO RÁPIDO

| Métrica | Lucian (10) | Carmen (actual) | Carmen (objetivo) |
|---------|-------------|-----------------|-------------------|
| Figuras en Cap. 5 | 15 figuras | 0 figuras | 6–8 figuras |
| Tablas en Cap. 5 | 0 tablas | ~8 tablas | 4–5 tablas |
| Capturas de ejecución real | 9 capturas | 0 capturas | 3–4 capturas |
| Outputs de herramientas como figura | 4 | 0 | 3 |
| Narrativa "riesgo → decisión → resultado" | Sí, en cada sección | No | Añadir intro en cada subsección |
| Conclusiones mapeadas a objetivos | Sí (6.1.1) | No (vacío) | Añadir (5.10) |
| Validación de reproducibilidad | Sí (5.8.3) | No | Añadir párrafo en 5.7.3 |

**El problema central:** Lucian tiene EVIDENCIAS en cada sección. Tú tienes tablas con resultados esperados o diseñados. La diferencia entre un 7 y un 9/10 son las capturas reales de la ejecución.

---

## PARTE 1 — QUÉ FIGURAS AÑADIR (y dónde)

### FIGURA 1 — Diagrama de la estrategia iterativa (sección 5.1)

**Qué es:** Un diagrama de 3 cajas en horizontal: Fase 1 (local) → Fase 2 (servidor tutor) → Fase 3 (Selenium).

**Por qué:** Lucian tiene la Figura 1 (Extended Scrum Model) explicando su metodología. Tú necesitas algo visual que muestre la evolución de las tres fases. Sin diagrama, el texto de 5.1 es denso.

**Cómo hacerlo:** En Word, insertar → SmartArt → Proceso → 3 cajas. Tarda 5 minutos.

Contenido de cada caja:
- Caja 1: "Fase 1 · Entorno local · moodle-docker · Versión base"
- Caja 2: "Fase 2 · Servidor UCLM · Tutor confirma UNI-02 · Descubren incidencias"
- Caja 3: "Fase 3 · Selenium standalone · FUN-10 colaborativo · 2 sesiones paralelas"

---

### FIGURA 2 — Output real de PHPUnit del tutor (sección 5.7.2)

**Qué es:** Captura de pantalla (o bloque de código formateado) mostrando el output real que el tutor te envió.

**Por qué:** Lucian tiene la Figura 5 (Kafka console consumer output) como evidencia de que su pipeline funciona. Tú tienes el output del tutor en ficheros TXT — úsalo como figura. Es tu equivalente.

**Cómo hacerlo:** Copia el output real en un bloque con fuente monoespaciada en Word (o captura la pantalla del txt):

```
Moodle 4.3.3 (Build: 20240212)
PHPUnit 9.5.28

mod_sqlab_version_test
 ✔ Plugin is installed
 ✔ Version file has required fields
 ✘ Can create sqlab instance
   │ coding_exception: Component mod_sqlab does not support generators yet.
   │ Missing tests/generator/lib.php.
 ✔ Database table exists

Tests: 4, Assertions: 7, Errors: 1.
```

**Texto de referencia en el cuerpo:** "Como muestra la Figura X, el tutor ejecutó la suite UNI-02 en el servidor UCLM (Moodle 4.3.3, PHP 8.2.30), obteniendo 3 tests PASSED y 1 ERROR en UNI-02c."

---

### FIGURA 3 — Output del error fatal de `--group` (sección 5.7.2)

**Qué es:** El output del fichero `phpunit-output-f52d1663.txt` mostrando el error fatal de `qtype_sqlquestion\privacy\provider`.

**Por qué:** Sin esta figura, el párrafo que explica la incidencia 1 (el bug del plugin dependencia) es solo texto. Con la figura es una evidencia. Lucian hace esto exactamente con sus figuras de código.

**Cómo hacerlo:** Mismo formato que la Figura 2. Muestra solo las primeras líneas relevantes del error:

```
Fatal error: Class qtype_sqlquestion\privacy\provider contains 2 abstract methods
and must therefore be declared abstract or implement the remaining methods
(core_privacy\local\metadata\provider::get_metadata,
core_privacy\local\request\plugin\provider::export_user_preferences)
in /var/www/html/question/type/sqlquestion/classes/privacy/provider.php on line 42
```

---

### FIGURA 4 — Error del generador en Behat / PHPUnit (sección 5.7.1)

**Qué es:** El mensaje de error `coding_exception: Missing tests/generator/lib.php` como bloque formateado.

**Por qué:** Es la justificación técnica de por qué Behat no funciona. El tutor te dijo "es imposible la ejecución de los tests de Behat" — tienes que mostrar el error que lo demuestra. Lucian hace esto en cada sección con sus figuras de código.

**Cómo hacerlo:** Igual que las anteriores. Ya tienes el texto exacto del error de los ficheros del tutor.

---

### FIGURA 5 — Output de phpcs (sección 5.6)

**Qué es:** Captura o bloque del output de phpcs mostrando las violaciones detectadas.

**Por qué:** Tu sección 5.6 de análisis estático habla de que phpcs detectó errores de licencia GPL, pero no hay ninguna evidencia visual. Con una figura es concreto e irrefutable. Es como la Figura 4 de Lucian (el PHP wrapper de Kafka) — muestra el código real.

**Cómo hacerlo:** Si tienes el output de phpcs guardado en algún fichero, copia las primeras líneas relevantes. Si no, ejecuta `phpcs` manualmente en tu entorno local y copia el output.

---

### FIGURAS 6, 7, 8 — Capturas de Selenium (sección 5.7.3) ← LAS MÁS IMPORTANTES

**Qué son:** Capturas reales de la ejecución del script de colaboración:
- Figura 6: Chrome 1 abierto con student2 en la sala colaborativa de sqlab, mostrando el indicador de presencia de student3
- Figura 7: Chrome 2 abierto con student3 en la misma sala, mostrando el indicador de presencia de student2
- Figura 8: Terminal Windows mostrando la salida del script Python con los 2 hilos ejecutándose

**Por qué:** Lucian tiene NUEVE capturas solo de los tests (Figuras 8–16). Su sección de testing es la más fuerte de toda la memoria. La tuya actualmente dice "pendiente de resolución de incidencia". Si entregas así, pierdes 2 puntos fácilmente.

**ESTO ES LO MÁS URGENTE DE TODO EL DOCUMENTO.**

**Cómo hacerlo (cuando el tutor solucione los usuarios):**
1. Ejecuta el script: `python selenium_colaborativo_sqlab.py`
2. Cuando ambos Chromes estén abiertos, haz Win+Shift+S (captura de pantalla parcial) de cada uno
3. Haz captura de la terminal con la salida del script

---

### FIGURA OPCIONAL — Diagrama de arquitectura Selenium (sección 5.7.3)

**Qué es:** El esquema de hilos (Hilo 1 / Hilo 2) que ya está en el documento de cambios.

**Por qué:** Lucian tiene el diagrama de arquitectura (Figura 2, Figura 17) explicando sus dos loops (sync/async). Tú puedes hacer lo mismo con el diagrama de hilos. No es obligatorio pero refuerza mucho la sección.

**Cómo hacerlo:** En Word, tabla de 2 columnas con el texto del esquema que ya tienes en CAMBIOS_EXACTOS_CAPITULO5.md (sección 5.7.3).

---

## PARTE 2 — QUÉ TABLAS QUITAR O COMPRIMIR

### Tabla 5 (Casos de uso) — MOVER AL ANEXO

**Justificación:** Lucian no tiene tablas de casos de uso en su Capítulo 5. Los casos de uso pertenecen a la metodología (su Capítulo 4) o a un anexo. En tu Cap. 5 ocupan espacio que debería ser evidencias y resultados. Mueve la Tabla 5 (CU-00 a CU-12) al Anexo II o III. Solo pon una referencia: "Los casos de uso que guiaron el diseño de los escenarios se recogen en el Anexo II."

**Espacio liberado:** ~1 página. La usas para las figuras de evidencias.

---

### Tabla 6 (Behat) — COMPRIMIR DE 10 FILAS A 3

**Justificación:** Actualmente 10 filas que dicen prácticamente lo mismo ("No ejecutable — generador ausente"). Es redundante y visualmente abrumador. Lucian nunca repetiría 10 filas con el mismo resultado. Comprime así:

| ID | Descripción | Resultado | Motivo |
|----|-------------|-----------|--------|
| FUN-01 a FUN-09 | Escenarios funcionales (usabilidad, SQL, interfaz, navegación) | No ejecutable | Generador de datos ausente en todos los escenarios. Ver Figura X (error completo). |
| FUN-10 | Entorno colaborativo (dos usuarios simultáneos) | No ejecutable (Behat) | Generador ausente + limitación de un solo hilo de ejecución. Validado con Selenium standalone (sección 5.7.3) |

**Espacio liberado:** ~0.5 páginas.

---

### Tabla 7 (PHPUnit) — REESTRUCTURAR, NO QUITAR

**Justificación:** La tabla es necesaria y está bien, pero necesita una columna "Entorno" para indicar dónde se ejecutó cada resultado. Actualmente mezcla resultados del servidor del tutor (UNI-02, confirmados) con resultados de tu entorno local (UNI-03, 04, 05) y resultados esperados (UNI-06). Sin esta distinción parece que todo está confirmado en el servidor, lo cual no es honesto y podría ser cuestionado.

**Añade una columna "Confirmado en":**
- UNI-02: "Servidor UCLM (tutor)"
- UNI-03, 04, 05: "Entorno local"
- UNI-06: "Esperado (v2 pendiente)"

---

### Tabla 8 (Cumplimiento) — MANTENER, es tu aportación diferencial

**Justificación:** Lucian no tiene esta tabla porque su objetivo era construir un prototipo, no evaluar publicabilidad. Esta tabla ES tu valor añadido. Es lo que hace que tu TFG tenga contenido original propio. No la elimines. Pero completa la columna Estado con los cambios del documento CAMBIOS_EXACTOS_CAPITULO5.md.

---

## PARTE 3 — QUÉ REESCRIBIR EN ESTILO "LUCIAN"

### 3.1 Añadir frase de "motivación y riesgo" al inicio de 5.7.1, 5.7.2 y 5.7.3

**Justificación:** Cada sección de Lucian empieza con "5.X.1 Motivation and Addressed Risks". Tus secciones entran directamente en las tablas. Añade 2-3 frases antes de cada tabla explicando QUÉ problema/riesgo pretendía resolver esa herramienta y qué reveló el resultado.

**Ejemplo para 5.7.1 (Behat):**
> Los escenarios Behat se diseñaron para validar la correctitud funcional del plugin desde la perspectiva del usuario (flujos de acceso, visualización de interfaz, ejecución de consultas SQL). La hipótesis inicial era que Behat, como herramienta estándar de Moodle para pruebas funcionales, daría cobertura completa a los requisitos FUN-01 a FUN-10. Sin embargo, la ejecución reveló una carencia estructural del plugin que invalidó esta hipótesis: la ausencia del generador de datos.

**Ejemplo para 5.7.2 (PHPUnit):**
> Las suites PHPUnit se diseñaron para cubrir los cuatro pilares de la certificación técnica en Moodle: instalación, Privacy API, Backup & Restore API y estructura de ficheros. El principal riesgo era que el entorno de pruebas del tutor (servidor UCLM, versión definitiva del plugin) divergiera del entorno local. La ejecución en el servidor confirmó los resultados de UNI-02 e identificó dos incidencias de infraestructura nuevas no detectadas en local.

**Ejemplo para 5.7.3 (Selenium):**
> La validación de FUN-10 presentaba un riesgo arquitectónico específico: ninguna herramienta estándar de Moodle permite mantener dos sesiones de usuario simultáneas en un solo proceso de ejecución. El análisis de Behat confirmó esta limitación (un solo WebDriver, un solo hilo). La solución adoptada — Python con threading.Thread y dos instancias de WebDriver — elimina este límite manteniendo la independencia total entre sesiones.

---

### 3.2 Añadir sección de reproducibilidad al final de 5.7.3

Lucian tiene la sección 5.8.3 "Deployment Validation" — prueba de instalación limpia. Tú puedes hacer lo equivalente con un párrafo al final de 5.7.3:

> **Reproducibilidad del escenario.** A diferencia de las suites PHPUnit (que requieren un entorno moodle-docker completo), el script `selenium_colaborativo_sqlab.py` puede ejecutarse desde cualquier máquina Windows o Linux con acceso VPN al servidor UCLM con solo tres requisitos: Python 3.8+, Google Chrome y `pip install selenium`. No es necesario ningún servidor local ni configuración de Moodle. Esto garantiza que el escenario SEL-COLAB-01 puede ser reproducido por cualquier evaluador en menos de 5 minutos, simplificando la verificación independiente de los resultados.

---

### 3.3 Reescribir 5.10 Conclusiones mapeando a los 3 objetivos específicos del Cap. 1

**Justificación:** El 6.1.1 de Lucian es su sección más valorada por el tribunal. Por cada objetivo específico dice "se logró porque [evidencia concreta]". Tú debes hacer lo mismo con tus objetivos. Busca en tu Capítulo 1 cuáles son tus objetivos específicos numerados (los que empiezan con "Obj-1", "Obj-2"...) y escribe un párrafo por cada uno con la evidencia de Cap. 5.

Estructura para cada objetivo:
1. **Logro respecto a [Nombre del Objetivo]:** [1 frase diciendo si se logró]
   - **Evidencia técnica:** [referencia a tabla o figura específica del Cap. 5]
   - **Límite identificado:** [qué quedó pendiente y por qué]

---

### 3.4 Reformular el párrafo de FUN-10 en 5.7.1 como "pivot arquitectónico"

Actualmente en tu Tabla 6 FUN-10 solo dice "Behat no soporta sesiones simultáneas". Eso es correcto pero plano. Lucian describe su "congestion crisis" (el servidor se colgaba con 1 request por tecla) como el punto de inflexión que justificó el rediseño. Tú debes hacer lo mismo con FUN-10:

> El análisis de Behat para FUN-10 reveló una limitación arquitectónica fundamental que no era evidente en el diseño inicial: Behat opera en un único hilo con una sola instancia de WebDriver, lo que hace técnicamente imposible simular la presencia simultánea de dos usuarios en la misma sala colaborativa. Este hallazgo —confirmado por el tutor— constituyó el punto de inflexión metodológico del capítulo: en lugar de documentar FUN-10 como "no testeable", se adoptó Selenium standalone con hilos independientes como solución arquitectónica equivalente que sí permite la simultaneidad real. Esta decisión es la que da título a la sección 5.7.3.

---

## PARTE 4 — ORDEN DE EJECUCIÓN

### Hoy mismo (sin esperar al servidor):

1. ✅ **Aplicar cambios del documento CAMBIOS_EXACTOS_CAPITULO5.md** (ya tienes el texto listo)
2. ✅ **Mover Tabla 5 al Anexo** — 5 minutos en Word
3. ✅ **Comprimir Tabla 6 de 10 filas a 3** — 10 minutos
4. ✅ **Añadir columna "Confirmado en" a Tabla 7** — 10 minutos
5. ✅ **Crear Figura 1** (diagrama SmartArt de 3 fases) — 5 minutos
6. ✅ **Crear Figura 2** (output PHPUnit del tutor como bloque de código) — 5 minutos
7. ✅ **Crear Figura 3** (error fatal de --group) — 5 minutos
8. ✅ **Crear Figura 4** (error del generador en Behat) — 5 minutos
9. ✅ **Añadir párrafos de "motivación y riesgo"** a 5.7.1, 5.7.2, 5.7.3 — 20 minutos
10. ✅ **Añadir párrafo de reproducibilidad** al final de 5.7.3 — 5 minutos
11. ✅ **Reescribir 5.10 Conclusiones** mapeando a tus objetivos del Cap. 1 — 30 minutos

**Total estimado: ~1.5 horas de trabajo en Word.**

---

### Cuando el tutor resuelva los usuarios del servidor:

12. ⏳ **Ejecutar `python selenium_colaborativo_sqlab.py`**
13. ⏳ **Capturar Figura 6** (Chrome 1, student2 en sala colaborativa)
14. ⏳ **Capturar Figura 7** (Chrome 2, student3 en sala colaborativa)
15. ⏳ **Capturar Figura 8** (terminal con output del script)
16. ⏳ **Actualizar 5.7.3**: quitar "pendiente de incidencia", añadir resultados reales
17. ⏳ **Actualizar Tabla 8**: cambiar SEL-COLAB-01 de PENDIENTE a CUMPLE/PARCIAL

---

## PARTE 5 — QUÉ NO CAMBIAR (lo que tienes mejor que Lucian)

1. **Tabla 8 (cumplimiento)** — Lucian no tiene nada equivalente. Es tu aportación diferencial. Sin esto tu TFG sería una evaluación sin conclusión. Mantenla y complétala.

2. **Análisis estático (5.6)** — Lucian no hace phpcs ni phplint. Tú sí. Es evidencia de trabajo técnico real. No la elimines, mejórala añadiendo la Figura de output.

3. **La sección de Behat (5.7.1)** — Aunque no ejecutó ningún test, documenta correctamente un hallazgo real (la ausencia del generador). Esto es evaluación de calidad real, no un fracaso. Lucian documenta sus fracasos como progreso — tú debes hacer lo mismo con Behat.

4. **Los tests PHPUnit en sí** — Tener 4 suites (UNI-02 a UNI-05) + UNI-06 nueva es más cobertura que lo que tiene Lucian en términos de validación de estándares de Moodle.

5. **La propuesta de mejoras (5.9)** — Lucian tiene future work vago y general. Tu 5.9 con 6 mejoras concretas y técnicamente justificadas es más sólida.

---

## RESUMEN VISUAL: ANTES vs DESPUÉS

```
ANTES (tu Cap. 5 actual):
5.1 Intro (2 fases, sin diagrama)
5.2 Requisitos
5.3 Herramientas
5.4 Plan de pruebas
5.5 Entorno
5.6 Análisis estático (texto, sin capturas)
5.7.1 Behat → Tabla 6 (10 filas iguales, sin narrativa)
5.7.2 PHPUnit → Tabla 7 (sin columna origen, sin figuras de output)
[5.7.3 no existe]
5.8 Tabla 8 (Estado vacío)
5.9 vacío
5.10 vacío

DESPUÉS (objetivo):
5.1 Intro (3 fases + Figura 1 diagrama SmartArt)
5.2 Requisitos
5.3 Herramientas
5.4 Plan de pruebas
5.5 Entorno
5.6 Análisis estático + Figura 5 (output phpcs)
5.7.1 Behat → párrafo motivación + Figura 4 (error generador) + Tabla 6 (3 filas comprimidas)
5.7.2 PHPUnit → párrafo motivación + Figura 2 (output tutor) + Figura 3 (error fatal) + Tabla 7 (con columna origen + v2)
5.7.3 Selenium → párrafo motivación + arquitectura + Figura 6-7-8 (capturas reales) + párrafo reproducibilidad
5.8 Tabla 8 (Estado completo)
5.9 Propuesta de mejoras (6 puntos justificados)
5.10 Conclusiones mapeadas a 3 objetivos específicos
```

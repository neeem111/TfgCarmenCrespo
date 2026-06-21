# Guía de Tests Selenium — mod_sqlab

**TFG — Carmen Respona Navarro**  
Grado en Ingeniería Informática · UCLM  
Plugin Moodle: `mod_sqlab` (v2024060100, release 4.5.8)

---

## 1. Contexto

Este documento describe el conjunto de pruebas funcionales automatizadas con **Selenium WebDriver + Python** desarrolladas para validar el plugin `mod_sqlab`. Las pruebas cubren los mismos requisitos que los tests Behat originales del repositorio, pero ejecutados contra el entorno real de despliegue (`moodle.repobcam.i3a.uclm.es:10443`), con usuarios reales y sin necesidad de reiniciar Moodle.

Cada fichero `selenium_FUNxx.py` se ejecuta de forma independiente:

```
python selenium_FUN01.py
```

Y devuelve una salida del tipo:
```
  ✔ PASS  Descripción del escenario
  ✘ FAIL  Motivo del fallo
  TOTAL: 3/3 pasados
```

---

## 2. Descripción de cada test

### FUN-01 — Acceso al curso y verificación de roles
**Fichero:** `selenium_FUN01.py`

Verifica que el sistema de roles de Moodle funciona correctamente para la actividad SQLab.

- **SC1 — Estudiante accede al curso:** `student1` inicia sesión, navega al curso y comprueba que ve la actividad `"actividad grupal"` y el shortname `"BBDD"` del curso. Valida el caso de uso CU-00.
- **SC2 — Profesor activa modo edición:** `carmenprof` activa el modo edición del curso. Confirma que el rol de profesor tiene permisos de edición (CU-07).
- **SC3 — Estudiante no puede editar:** `student1` accede al mismo curso y comprueba que no aparece ningún botón de edición. Verifica la restricción de rol.

**Usuarios:** `student1`, `carmenprof` (ambos con `Stu1234!`)

---

### FUN-02 — Acceso a la actividad SQLab
**Fichero:** `selenium_FUN02.py`

Valida que una actividad de tipo SQLab es accesible desde el curso.

- **SC1 — Actividad visible en el curso:** Comprueba que `"actividad grupal"` aparece en la lista del curso y que el enlace apunta a `/mod/sqlab/`. Confirma CU-01.
- **SC2 — Abrir actividad sin errores PHP:** Abre la actividad (`view.php?id=5`) y verifica que no hay errores PHP en la respuesta y que la URL es la del intento de SQLab.

**Usuarios:** `student1`

---

### FUN-03 — Plugin mod_sqlab instalado correctamente
**Fichero:** `selenium_FUN03.py`

Comprueba la infraestructura de instalación del plugin desde el panel de administración.

- **SC1 — Plugin "SQLab" en la lista de módulos:** Abre `admin/modules.php` y busca el texto `"SQLab"` (capitalización exacta confirmada tras el fallo del test Behat original con `"sqlab"` en minúsculas).
- **SC2 — Sin errores PHP en admin:** Verifica que la página de administración no contiene errores PHP, warnings ni notices.
- **SC3 — Plugin habilitado sin avisos de error:** Confirma que no aparecen textos como "Plugin not installed" o "Missing from disk".

**Usuarios:** `admin` (único test que requiere admin; es verificación de infraestructura)

> **Corrección aplicada:** el texto en la UI es `"SQLab"` y no `"sqlab"`. El test Behat original fallaba en este paso.

---

### FUN-04 — Interfaz de la actividad SQLab
**Fichero:** `selenium_FUN04.py`

Valida los elementos de la interfaz visible al estudiar la actividad.

- **SC1 — Enunciado visible:** Comprueba que `"Pregunta 1"` (o equivalente) es visible tras abrir el intento. Valida CU-02.
- **SC2 — "Resultados esperados" presente:** Verifica la sección que muestra la solución esperada al estudiante.
- **SC3 — Editor SQL presente:** Detecta la presencia del editor CodeMirror (por clase `.CodeMirror`, textarea o el botón "Ejecutar código").

**Usuarios:** `student1`

---

### FUN-05 — Configuración de la actividad (formulario de profesor)
**Fichero:** `selenium_FUN05.py`

Verifica que el plugin proporciona los campos de configuración correctos.

- **SC1 — SQLab en el picker de actividades:** `carmenprof` activa el modo edición, abre el selector de actividades y confirma que "SQLab" aparece disponible (CU-08).
- **SC2 — Campos del formulario de configuración:** Abre el formulario de edición de la actividad existente (`modedit.php?update=5`) y verifica que los campos `name`, `quizid` y `activitypassword` están presentes. Confirma que el plugin registra correctamente sus parámetros en `mod_form.php`.

**Usuarios:** `carmenprof`

> **Corrección aplicada:** La versión anterior intentaba crear una actividad nueva, lo que fallaba porque el campo `quizid` (requerido) no tenía un valor real del sistema. El SC2 rediseñado verifica los campos del formulario en una actividad existente en lugar de crear una nueva.

---

### FUN-06 — Ejecución de SQL y feedback
**Fichero:** `selenium_FUN06.py`

Valida el botón "Ejecutar código" (CU-03 y CU-04).

- **SC1 — Ejecutar código muestra resultado:** Escribe `SELECT 1;` en el editor, pulsa "Ejecutar código" y comprueba que hay resultado visible (no solo ausencia de error PHP, sino al menos uno de los marcadores: `row`, `fila`, `selected data`, `?column?`, `ERROR`…).
- **SC2 — Evaluar código sin errores PHP:** Pulsa "Evaluar código" y comprueba que no hay errores PHP en la respuesta del servidor.
- **SC3 — SQL inválido gestionado correctamente:** Introduce SQL sintácticamente incorrecto y verifica que el servidor lo maneja sin lanzar un error PHP visible (el error de SQL debe mostrarse como mensaje de la aplicación, no como stack trace).

**Usuarios:** `student1`

> **Corrección aplicada:** SC1 ahora comprueba `has_result()` además de la ausencia de error PHP.

---

### FUN-07 — Navegación entre preguntas
**Fichero:** `selenium_FUN07.py`

Valida que el estudiante puede moverse entre las preguntas de la actividad (CU-05).

- **SC1 — Sidebar con Pregunta 1 y Pregunta 2:** Verifica que el índice lateral muestra al menos dos preguntas navegables.
- **SC2 — Avanzar a Pregunta 2:** Clic en el enlace de Pregunta 2 y confirma que la navegación ocurre sin errores PHP.
- **SC3 — Volver a Pregunta 1:** Desde Pregunta 2 vuelve a Pregunta 1 verificando la misma condición.

**Usuarios:** `student1`

---

### FUN-08 — Visualización de puntuación
**Fichero:** `selenium_FUN08.py`

Valida la funcionalidad de evaluación y puntuación (CU-06).

- **SC1 — "Puntúa como" visible:** Comprueba que el indicador de puntuación de la pregunta es visible sin necesidad de interactuar.
- **SC2 — Evaluar código muestra feedback:** Escribe SQL, pulsa "Evaluar código" y verifica que aparece alguna respuesta de corrección (palabras: `correcto`, `incorrecto`, `puntuaci`, `Feedback`…).
- **SC3 — SQL inválido + evaluar sin error PHP:** Igual que SC2 pero con SQL incorrecto para confirmar que el servidor gestiona el error a nivel de aplicación.

**Usuarios:** `student1`

> **Corrección aplicada:** SC2 ahora comprueba `has_feedback()` además de la ausencia de error PHP.

---

### FUN-09 — Menú del diccionario de datos
**Fichero:** `selenium_FUN09.py`

Valida el menú jerárquico de snippets SQL del diccionario (CU-09).

- **SC1 — Botón del diccionario visible:** Comprueba que existe un elemento con `aria-label="Consultas del diccionario de datos"` (selector confirmado del HTML real) o el contenedor `#vertical-menu-sqledi-snippet-menu-btn`.
- **SC2 — Menú se despliega sin error PHP:** Abre el menú con el selector de aria-label y verifica que no hay errores PHP tras la interacción.
- **SC3 — "Schema list" inserta snippet:** Clic en el ítem del menú `"Schema list"` (los ítems son en **inglés**: `Schema list`, `Tables`, `Views`) y lee el contenido del editor CodeMirror para confirmar que se insertó el snippet `select nspname from pg_namespace;`.

**Usuarios:** `student1`

> **Correcciones aplicadas:**  
> 1. El selector anterior usaba texto en español (`"Diccionario"`, clases CSS genéricas). Corregido a `aria-label="Consultas del diccionario de datos"` (confirmado del DOM real).  
> 2. Los ítems del menú son en inglés (`"Schema list"`), no `"Tablas"` en español como asumía el script anterior.

---

### FUN-10 — Entorno colaborativo (creación y unión a sala)
**Fichero:** `selenium_FUN10.py`

Valida los casos de uso CU-10 (crear sala), CU-11 (unirse) y CU-12 (awareness).

- **SC1 — UI de sala visible:** Comprueba que existe el elemento `#roomidtext` (input para el ID de sala) y texto "Sala" visible.
- **SC2 — Botón "Unirme a la sala" visible:** Verifica `#btn-connect` en el DOM.
- **SC3 — "Participantes conectados" visible:** Comprueba el texto del widget de awareness.
- **SC4 — Room ID extraíble:** Espera a que `#room-id-display` cambie de `"N/A"` (valor inicial) a un ID real (requiere conexión WebSocket establecida).
- **SC5 — Formulario de unión:** Clic en `#btn-connect` sin ID y descarta el alert JS resultante; verifica que no hay error PHP.
- **SC6 — 2 usuarios simultáneos:** `student1` extrae su Room ID desde `#room-id-display`, lo comparte con `student2` (via threading), y `student2` introduce el ID en `#roomidtext`, pulsa `#btn-connect` y verifica que la unión es exitosa.

**Usuarios:** `student1`, `student2`

> **Correcciones aplicadas:**  
> 1. `extract_room_id()` ahora lee `#room-id-display` via JS y espera a que no sea `"N/A"`.  
> 2. Input: `By.ID, "roomidtext"` en lugar de XPath por placeholder.  
> 3. Botón: `By.ID, "btn-connect"` en lugar de XPath por texto.

---

### FUN-11 — Funcionalidad colaborativa avanzada (awareness completo)
**Fichero:** `selenium_FUN11.py`

Prueba completa del flujo colaborativo con 2 usuarios en threading.

- **SC1 — student1 crea sala:** `student1` abre la actividad, espera a que el WebSocket conecte y lee su Room ID de `#room-id-display`.
- **SC2 — student2 se une:** Introduce el ID en `#roomidtext` y pulsa `#btn-connect`.
- **SC3 — Awareness bidireccional:** Ambos ven al menos 1 participante conectado (leído de `#participant-count`).
- **SC4 — Modal de participantes:** Clic en `#participant-count` → aparece `#modalOverlay` con la lista de usuarios → se cierra con `#modalCloseBtn`.
- **SC5 — Clic en ID de sala:** Clic en `#room-id-display` (copia URL o abre funcionalidad de compartir) sin error PHP.

**Usuarios:** `student1`, `student2`

---

### FUN-12 — Demo integral para el tribunal *(vídeo)*
**Fichero:** `selenium_FUN12.py`

Test de demostración completo diseñado para grabar como vídeo de presentación al tribunal. Cubre 11 escenarios en una sola sesión colaborativa con **dos ventanas Chrome lado a lado** (student1 izquierda, student2 derecha).

| Escenario | Qué demuestra |
|-----------|--------------|
| SC1 | student1 y student2 en la misma sala (Room ID compartido) |
| SC2 | Awareness: contador de participantes en tiempo real |
| SC3 | Modal con lista de usuarios conectados |
| SC4 | Clic en ID de sala (funcionalidad de compartir) |
| SC5 | Botón del diccionario SQL visible |
| SC6 | Inserción de snippet "Schema list" en el editor |
| SC7 | Submenús "Tables" y "Views" del diccionario |
| SC8 | Sincronización del editor: código de student1 aparece en student2 |
| SC9 | student1 ejecuta SQL → resultado visible |
| SC10 | student1 evalúa código → feedback visible |
| SC11 | student2 ejecuta SQL independientemente → resultado visible |

La consola imprime narración detallada con emojis y fases numeradas para seguir el flujo durante la grabación.

---

## 3. Arquitectura técnica común

Todos los scripts comparten los siguientes patrones:

```python
# Configuración Chrome (certificado autofirmado + sandbox)
options.add_argument("--ignore-certificate-errors")
options.add_argument("--no-sandbox")
options.add_argument("--disable-dev-shm-usage")

# Esperas explícitas (no time.sleep fijo)
WebDriverWait(driver, 15).until(EC.element_to_be_clickable(...))

# CodeMirror: escribir via API JavaScript
driver.execute_script("document.querySelectorAll('.CodeMirror')[0].CodeMirror.setValue(sql)")

# Room ID: leer del DOM, esperar cambio de "N/A"
WebDriverWait(d, 15).until(lambda x: x.execute_script(
    "var el=document.getElementById('room-id-display');"
    "return el && el.textContent.trim() !== 'N/A';"))

# Tests colaborativos: threading.Thread + threading.Event para sincronización
```

---

## 4. Declaración de uso de IA

De acuerdo con la normativa del TFG sobre el uso de herramientas de inteligencia artificial, se declara lo siguiente:

### Herramienta utilizada
**Claude (Anthropic)** — modelo de lenguaje grande accedido mediante la interfaz de Cowork (desktop).

### Secciones del proyecto afectadas
Los scripts Selenium (`selenium_FUN01.py` a `selenium_FUN12.py`) y este documento guía fueron generados con asistencia de IA.

### Tipo y alcance del uso

| Tarea | Descripción del uso de IA |
|-------|--------------------------|
| **Generación de scripts** | Se describió a la IA la estructura del plugin (Behat tests originales, HTML real del DOM, usuarios del sistema) y se solicitó generar los scripts Python correspondientes. |
| **Corrección de bugs** | Se le proporcionaron los outputs de los Behat tests fallidos (p.ej. `"sqlab"` vs `"SQLab"`) y el HTML real de la página para que identificara los selectores incorrectos y los corrigiera. |
| **Revisión de cobertura** | Se le pidió comparar cada script Selenium con el Behat original para verificar que los escenarios cubiertos eran equivalentes. |
| **Adaptación de roles** | Se le indicó que los tests no podían usar `admin` (salvo FUN-03) y la IA rediseñó los scripts usando `carmenprof` y estudiantes. |
| **FUN-12 vídeo tribunal** | Se le pidió un test "atractivo para vídeo" con narración en consola, ventanas lado a lado y cobertura exhaustiva de las funcionalidades colaborativas. |

### Proceso de validación humana
- Todos los selectores DOM fueron verificados manualmente contra el HTML real de la página de intento.
- Los usuarios, contraseñas y URLs fueron proporcionados explícitamente; la IA no los inventó.
- La lógica de los escenarios fue revisada paso a paso contra los requisitos Behat originales.
- Los scripts fueron ejecutados en el entorno real y ajustados según los resultados.

### Limitaciones y trabajo propio
La IA no tuvo acceso directo al entorno Moodle ni al código fuente del plugin. El conocimiento del sistema (mod_form.php, lang strings, estructura del plugin) fue obtenido por la autora directamente del repositorio y proporcionado como contexto. El diseño de los casos de prueba, la elección de los escenarios y la interpretación de los resultados son trabajo de la autora.

---

## 5. Referencia rápida: selectores DOM confirmados

| Elemento | Selector |
|----------|---------|
| Botón Ejecutar SQL | `#executeSqlButton` |
| Botón Evaluar SQL | `#evaluateSqlButton` |
| Botón diccionario | `[aria-label="Consultas del diccionario de datos"]` |
| Contenedor diccionario | `#vertical-menu-sqledi-snippet-menu-btn` |
| Editor SQL | `#codemirror-editor` / `.CodeMirror` |
| Room ID display | `#room-id-display` (inicia en "N/A") |
| Contador participantes | `#participant-count` |
| Input ID sala | `#roomidtext` |
| Botón unirse | `#btn-connect` |
| Modal participantes | `#modalOverlay` |
| Cerrar modal | `#modalCloseBtn` |

---

## 6. Ejecución rápida de todos los tests

```bash
# Instalar dependencias
pip install selenium

# Descargar ChromeDriver compatible con tu Chrome
# https://googlechromelabs.github.io/chrome-for-testing/

# Ejecutar todos los tests en secuencia
for f in selenium_FUN0{1..9}.py selenium_FUN1{0..2}.py; do
    echo "=== $f ==="; python "$f"; echo
done
```

> Los tests FUN-10, FUN-11 y FUN-12 son los más lentos (~2 minutos cada uno)
> porque incluyen sesiones de 2 usuarios simultáneos con WebSocket.
> FUN-12 está diseñado para grabarse; ejecutarlo con la pantalla visible.

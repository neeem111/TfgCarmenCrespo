# GUÍA COMPLETA DE CONTEXTO PARA SCRIPTS SELENIUM — mod_sqlab (TFG)

Copia este documento entero en el chat nuevo. Contiene todo lo necesario para generar o corregir scripts Selenium para la actividad SQLab en Moodle.

---

## 1. ENTORNO

| Parámetro | Valor |
|-----------|-------|
| URL base Moodle | `https://moodle.repobcam.i3a.uclm.es:10443` |
| Certificado SSL | autofirmado → Chrome con `--ignore-certificate-errors` |
| ChromeDriver | en la misma carpeta que los scripts (`chromedriver.exe`) |
| Python | 3.x con `selenium` instalado |
| SO | Windows |

**Opciones Chrome estándar (todas las funciones las usan):**
```python
from selenium.webdriver.chrome.options import Options
o = Options()
o.add_argument("--ignore-certificate-errors")
o.add_argument("--no-sandbox")
o.add_argument("--disable-dev-shm-usage")
d = webdriver.Chrome(options=o)
d.set_page_load_timeout(30)
```

---

## 2. USUARIOS

| Usuario | Contraseña | Rol |
|---------|------------|-----|
| `admin` | `Admin1234!` | Administrador Moodle |
| `student1` | `Stu1234!` | Estudiante matriculado en el curso |
| `student2` | `Stu1234!` | Estudiante matriculado en el curso |

**Login (IDs del formulario Moodle estándar):**
- Campo usuario: `By.ID, "username"`
- Campo contraseña: `By.ID, "password"`
- Botón submit: `By.ID, "loginbtn"`
- URL login: `{BASE_URL}/login/index.php`
- Detectar login fallido: si `"login/index"` sigue en `d.current_url` → falló

---

## 3. CURSO

| Parámetro | Valor |
|-----------|-------|
| Nombre | `bbdd` (o `BBDD`) — contiene esa cadena en el source |
| Course ID | `2` |
| URL | `{BASE_URL}/course/view.php?id=2` |

---

## 4. PLUGIN mod_sqlab

| Parámetro | Valor |
|-----------|-------|
| Nombre | `SQLab` / `sqlab` |
| Versión | `2024060100` (release `4.5.8`) |
| Componente | `mod_sqlab` |
| Madurez | `MATURITY_STABLE` |
| Admin módulos | `{BASE_URL}/admin/modules.php` |

---

## 5. ACTIVIDAD SQLAB DE PRUEBA

| Parámetro | Valor |
|-----------|-------|
| Course Module ID | `5` |
| URL de acceso | `{BASE_URL}/mod/sqlab/view.php?id=5` |
| URL intento | `{BASE_URL}/mod/sqlab/attempt.php?...` |

La actividad tiene **al menos 2 preguntas** (Pregunta 1 y Pregunta 2).

---

## 6. FORMULARIO DE CREACIÓN DE ACTIVIDAD (mod_form.php)

Cuando el admin crea una actividad SQLab, el formulario tiene estos campos:

| Campo | Nombre HTML (`name=`) | Tipo | Obligatorio |
|-------|-----------------------|------|-------------|
| Nombre de la actividad | `name` | `text` (size=50) | Sí |
| ID del Cuestionario | `quizid` | `text` (size=20, PARAM_INT) | Sí |
| Contraseña de la actividad | `activitypassword` | `passwordunmask` | No |

- Sección "General": cabecera estándar Moodle
- Sección "Seguridad" (`securitysettings`): contiene el campo de contraseña
- Botones estándar: "Guardar cambios y regresar" / "Save and return to course"

---

## 7. UI DENTRO DEL INTENTO (attempt.php / view.php)

### Botones para iniciar/continuar intento
El texto varía según el estado. Buscar en este orden:
```
"Continuar el último intento"
"Intentar ahora"
"Comenzar el intento"
"Iniciar actividad"
"Comenzar un nuevo intento"
```
Puede aparecer un **modal de confirmación** con:
```
"Comenzar" / "Iniciar un nuevo intento" / "Confirmar" / "Aceptar"
```

### Botones principales de la actividad
| Texto en UI | Función |
|-------------|---------|
| `Ejecutar código` | Ejecuta SQL sin contar para nota |
| `Evaluar código` | Evalúa SQL como respuesta final |

También existe un **botón del diccionario SQL** (icono entre los dos botones anteriores):
- No tiene texto, tiene imagen/icono
- Selectores intentados: class con `sql`, `dict`, title con `Diccionario`/`sql`, img alt con `sql`

### Editor SQL
El editor puede ser:
- `<textarea>` convencional → `By.TAG_NAME, "textarea"`
- **CodeMirror** → `By.CLASS_NAME, "CodeMirror"` (más frecuente)

Para escribir en CodeMirror:
```python
d.execute_script(
    "var cms=document.querySelectorAll('.CodeMirror');"
    "if(cms.length>0&&cms[0].CodeMirror){cms[0].CodeMirror.setValue(arguments[0]);cms[0].CodeMirror.focus();return true;}"
    "return false;", sql)
```

Para leer el editor (DOM, no API):
```python
d.execute_script("var c=document.querySelector('.CodeMirror-code'); return c?c.innerText.trim():''")
```

### Textos visibles en la página de intento
| Texto | Descripción |
|-------|-------------|
| `Pregunta 1` | Enunciado primera pregunta (sidebar + cabecera) |
| `Pregunta 2` | Enunciado segunda pregunta |
| `Resultados esperados` | Sección con tabla de resultados esperados |
| `Puntúa como` | Sección de puntuación (p.ej. "Puntúa como 1.00") |
| `Ejecutar código` | Botón de ejecución |
| `Evaluar código` | Botón de evaluación |
| `Conceptos relacionados` | Sección de conceptos |
| `Pistas` | Sección de hints |

### Botones de navegación entre preguntas
- Link/botón con texto `Pregunta 1`, `Pregunta 2`
- O `//a[contains(@href,'page=0')]` para P1, `//a[contains(@href,'page=1')]` para P2

### Botones al terminar
| Texto | Acción |
|-------|--------|
| `Terminar intento...` | Lleva al resumen |
| `Enviar todo y terminar` | Finaliza definitivamente |
| `Continuar el último intento` | Si hay intento en curso |

---

## 8. UI — MODO COLABORATIVO (Awareness/WebSocket)

El entorno colaborativo aparece en el lateral/inferior de la página de intento.

### Elementos DOM conocidos
| Elemento | ID/selector | Descripción |
|----------|-------------|-------------|
| Room ID display | `#room-id-display` o `span[title*="Copiar"]` | Muestra el ID de sala; clic copia URL |
| Contador participantes | `#participant-count` o `span.text-action-element` | Nº de participantes; clic abre modal |

### Textos visibles en awareness
| Texto | Descripción |
|-------|-------------|
| `Sala` | Sección del widget de sala |
| `Sala ID:` seguido de número ≥4 dígitos | ID de la sala actual |
| `Escriba el ID de la sala` | Placeholder del input para unirse |
| `Unirme a la sala` | Botón para unirse con el ID |
| `Participantes conectados:` seguido de número | Contador de participantes |

### Input para unirse a sala
Placeholder buscado con estos selectores (en orden de preferencia):
```python
"//input[contains(@placeholder,'ID de la sala')]"
"//input[contains(@placeholder,'Sala')]"
"//input[contains(@placeholder,'sala')]"
"//input[contains(@placeholder,'ID')]"
"//input[@type='text']"
```

### Regex para extraer el room_id
```python
import re
m = re.search(r'[Ss]ala\s+ID[^0-9]*([0-9]{4,})', d.page_source)
room_id = re.sub(r'[<\s].*', '', m.group(1)).strip() if m else None
```

### Regex para extraer participantes
```python
plain = re.sub(r'<[^>]+>', ' ', d.page_source)
m = re.search(r'Participantes\s+conectados:\s*(\d+)', plain)
count = int(m.group(1)) if m else None
```

### Modal de lista de participantes (SC4)
Tras clic en `#participant-count`, el modal contiene alguno de:
- `"No hay usuarios conectados"`
- `"Cerrar"`
- `student1` o `student2` en el source

Cerrar modal:
```python
WebDriverWait(d, 3).until(EC.element_to_be_clickable(
    (By.XPATH, "//button[normalize-space()='Cerrar']"))).click()
```

---

## 9. MENÚ DICCIONARIO SQL (FUN-09)

Botón entre "Ejecutar código" y "Evaluar código". **Es un icono, sin texto**.

Función JS para abrirlo:
```python
opened = d.execute_script("""
    var candidates = Array.from(document.querySelectorAll('button,a,[role="button"]'));
    var btn = candidates.find(function(el) {
        var cls = (el.className||'').toLowerCase();
        var ttl = (el.title||'').toLowerCase();
        var img = el.querySelector('img');
        var alt = img ? (img.alt||'').toLowerCase() : '';
        var src = img ? (img.src||'').toLowerCase() : '';
        return cls.includes('sql')||ttl.includes('sql')||alt.includes('sql')
            ||src.includes('sql')||cls.includes('dict')||ttl.includes('diccionario');
    });
    if (btn) { btn.click(); return true; }
    return false;
""")
```

### Ítems del menú diccionario (FUN-12)
| Ítem | Subítems al hacer hover |
|------|------------------------|
| `Schema list` | (clic directo → snippet en editor) |
| `Tables` | `List of tables`, `Table relational schema` |
| `Views` | `List of views` |

---

## 10. BOTONES ESPECIALES (JS click para evitar intercepto del editor)

```python
# Ejecutar código
d.execute_script("""
    var btn = document.getElementById('executeSqlButton');
    if (!btn) btn = Array.from(document.querySelectorAll('button')).find(
        function(b) { return b.textContent.trim() === 'Ejecutar código'; });
    if (btn) { btn.click(); return true; }
    return false;
""")

# Evaluar código
d.execute_script("""
    var btn = Array.from(document.querySelectorAll('button')).find(
        function(b) { return b.textContent.trim() === 'Evaluar código'; });
    if (btn) { btn.click(); return true; }
    return false;
""")
```

**ID conocido del botón Ejecutar:** `executeSqlButton`

---

## 11. DETECCIÓN DE RESULTADOS

### Tras "Ejecutar código"
```python
markers = ["successfully executed", "selected data", "?column?",
           "ERROR", "error", "fila", "rows", "row"]
has_result = any(m in d.page_source for m in markers)
```

### Tras "Evaluar código"
```python
markers = ["correcto", "incorrecto", "Correcto", "Incorrecto",
           "correct", "incorrect", "grade", "nota", "puntuaci",
           "Mark", "Feedback", "feedback", "evaluated", "evaluado"]
has_eval = any(m in d.page_source for m in markers)
```

### Errores PHP a detectar
```python
PHP_ERRORS = ["Fatal error", "Warning:", "Notice:", "Strict Standards:"]
err = next((e for e in PHP_ERRORS if e in d.page_source), None)
# err is None → sin error PHP
```

---

## 12. MODO EDICIÓN (admin)

```python
# Activar modo edición
for label in ["Turn editing on", "Activar edición", "Edit mode"]:
    # buscar y hacer clic

# Verificar que está activo
see(d, "Añadir", 6) or see(d, "Add an activity", 4)

# Añadir actividad
"//*[contains(text(),'Añadir una actividad') or contains(text(),'Add an activity')]"
```

---

## 13. TABLA DE SCRIPTS EXISTENTES

| Script | FUN | CU cubiertos | Escenarios |
|--------|-----|--------------|------------|
| `selenium_FUN01.py` | FUN-01 | CU-00, CU-07 | SC1: estudiante accede al curso; SC2: admin activa edición; SC3: estudiante NO ve edición |
| `selenium_FUN02.py` | FUN-02 | CU-01 | SC1: actividad SQLab visible en curso; SC2: abrir actividad sin errores PHP |
| `selenium_FUN03.py` | FUN-03 | instalación | SC1: plugin en lista de módulos; SC2: sin errores PHP admin; SC3: plugin habilitado |
| `selenium_FUN04.py` | FUN-04 | CU-02 | SC1: Pregunta 1 visible; SC2: Resultados esperados visible; SC3: editor SQL presente |
| `selenium_FUN05.py` | FUN-05 | CU-08 | SC1: SQLab en picker; SC2: admin crea actividad SQLab |
| `selenium_FUN06.py` | FUN-06 | CU-03, CU-04 | SC1: Ejecutar código sin PHP error; SC2: Evaluar código sin PHP error; SC3: SQL inválido sin PHP error |
| `selenium_FUN07.py` | FUN-07 | CU-05 | SC1: sidebar con P1 y P2; SC2: avanzar a P2; SC3: volver a P1 |
| `selenium_FUN08.py` | FUN-08 | CU-06 | SC1: "Puntúa como" visible; SC2: Evaluar sin PHP error; SC3: SQL inválido+evaluar sin PHP error |
| `selenium_FUN09.py` | FUN-09 | CU-09 | SC1: botón diccionario visible; SC2: abrirlo sin PHP error; SC3: snippet "Tablas" en editor |
| `selenium_FUN10.py` | FUN-10 | CU-10, CU-11, CU-12 | SC1-SC5: elementos colaborativos con 1 usuario; SC6: 2 usuarios simultáneos (threading) |
| `selenium_FUN10_colaborativo.py` | FUN-10 v2 | CU-10, CU-11, CU-12 | Variante mejorada del colaborativo |
| `selenium_FUN11.py` | FUN-11 | awareness | SC1-SC5: flujo completo awareness con 2 usuarios (threading) |
| `selenium_FUN12.py` | FUN-12 | integral | SC1-SC11: prueba integral colaborativa (2 usuarios, sync editor, dict, exec, eval) |

---

## 14. PATRÓN GENERAL DE TODOS LOS SCRIPTS

```python
TIMEOUT = 12
results = []

def run(name, fn):
    print(f"  ▶ {name}...", end=" ", flush=True)
    try:
        ok, msg = fn()
        print(f"{'✔ PASS' if ok else '✘ FAIL'}  {msg}")
        results.append((name, ok, msg))
    except Exception as e:
        print(f"✘ ERROR  {e}")
        results.append((name, False, str(e)))

def see(d, text, t=TIMEOUT):
    try:
        WebDriverWait(d, t).until(lambda x: text in x.page_source)
        return True
    except TimeoutException:
        return False

def start_attempt(d):
    d.get(ACTIVITY_URL)
    time.sleep(1)
    for label in ["Continuar el último intento", "Intentar ahora",
                  "Comenzar el intento", "Iniciar actividad", "Comenzar un nuevo intento"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH, f"//button[contains(text(),'{label}')] | //a[contains(text(),'{label}')]")
            )).click()
            time.sleep(2); break
        except TimeoutException:
            continue

# Cada escenario retorna (bool, str)
def sc_ejemplo():
    d = driver()
    try:
        login(d, S1_USER, S1_PASS)
        start_attempt(d)
        ok = see(d, "algo", 8)
        return ok, ("mensaje PASS" if ok else "mensaje FAIL")
    finally:
        d.quit()

if __name__ == "__main__":
    run("SC1 — descripción", sc_ejemplo)
    passed = sum(1 for _,ok,_ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)
```

---

## 15. DATOS QUE PUEDEN NECESITAR AJUSTE EN EL ENTORNO REAL

Estos son los datos que pueden **variar** y que habría que confirmar ejecutando contra el Moodle real:

| Dato | Valor asumido | Dónde se usa |
|------|---------------|--------------|
| Course ID | `2` | COURSE_URL |
| CM ID de la actividad de prueba | `5` | ACTIVITY_URL |
| Nombre del curso | contiene `bbdd`/`BBDD` | FUN-01 SC1 |
| Nombre actividad en lista | `"actividad grupal"` o `"individualSQLab"` | FUN-02 SC1 |
| SQL correcto que da "Correcto" | `SELECT 1;` (solo para no dar PHP error) | FUN-06/08 |
| Botón diccionario: selector exacto | múltiples fallbacks en `open_dict_menu()` | FUN-09/12 |
| Ítems menú dict: `Schema list`, `Tables`, `Views` | asumidos en inglés | FUN-12 |
| `#participant-count` y `#room-id-display` | IDs exactos del DOM colaborativo | FUN-11/12 |
| `executeSqlButton` | ID del botón Ejecutar | FUN-12 |
| Número de preguntas en la actividad | mínimo 2 | FUN-07 |

---

## 16. STRINGS DE LA INTERFAZ (lang/es/sqlab.php)

Strings clave tal como aparecen en la UI en español:

```
'Ejecutar código'         → botón ejecución
'Evaluar código'          → botón evaluación
'Puntúa como'             → sección puntuación
'Resultados esperados'    → sección resultados
'Pregunta'                → prefijo de cada pregunta (Pregunta 1, Pregunta 2…)
'Continuar el último intento'
'Comenzar un nuevo intento'
'Terminar intento...'
'Enviar todo y terminar'
'Nombre de la actividad'  → label campo name en mod_form
'ID del Cuestionario'     → label campo quizid en mod_form
'Contraseña de la actividad' → label campo activitypassword
'Seguridad'               → header sección seguridad
'Intentos ilimitados.'
'Intentos permitidos: '
'Calificación'
'Revisión'
'Revisar intento'
'Pistas'
'Conceptos relacionados'
'Su respuesta'
'Solución'
'Todas las filas son correctas.'
'No se ha proporcionado una respuesta.'
'Para acceder a este SQLab es necesario conocer la contraseña.'
'Contraseña'              → título modal password
'Por favor, ingrese la contraseña para continuar:'
'Cerrar'                  → botón cerrar modal
'Continuar'               → botón enviar contraseña
'Participantes conectados:' → awareness
'Sala'                    → widget colaborativo
'Unirme a la sala'        → botón join
```

---

## 17. ESTRUCTURA DE ARCHIVOS DEL PLUGIN

```
mod/sqlab/
├── version.php              → versión 2024060100, release 4.5.8
├── mod_form.php             → formulario creación (campos: name, quizid, activitypassword)
├── view.php                 → página principal de la actividad
├── attempt.php              → página del intento con editor SQL
├── processattempt.php       → procesa envío del intento
├── execute_sql.php          → ejecuta SQL (AJAX)
├── execute_context_resultdata.php → datos contexto/resultado
├── create_attempt.php       → crea nuevo intento
├── summary.php              → resumen del intento
├── review.php               → revisión de intento finalizado
├── password_check.php       → verifica contraseña de actividad
├── update_grade.php         → actualiza nota
├── lib.php                  → funciones estándar Moodle
├── classes/
│   ├── attempt_manager.php  → gestión de intentos (FINISHED, IN_PROGRESS)
│   ├── grader.php           → corrección automática
│   ├── user_query_executor.php → ejecuta consultas del usuario
│   ├── database_manager.php → gestión BD
│   ├── schema_manager.php   → gestión esquemas
│   ├── dbconnector.php      → conexión BD
│   ├── internal_sql_executor.php
│   ├── encoder.php
│   └── observer.php
├── lang/es/sqlab.php        → strings UI en español
└── db/
    ├── access.php           → capacidades (sqlab:view, sqlab:attempt, sqlab:manage)
    └── events.php
```

---

## 18. NOTAS DE THREADING (scripts colaborativos FUN-10, 11, 12)

Los scripts colaborativos usan `threading.Thread`:
- `student1` arranca primero, crea la sala, extrae el `room_id`
- Señal con `threading.Event()` para sincronizar los threads
- `student2` espera la señal, usa el `room_id` extraído, se une
- Timeouts de join: 55s (FUN-10), 75s (FUN-11), 120s (FUN-12)

Estructura de `shared` dict con `threading.Event` para:
- `room_ready`: S1 ha obtenido el room_id
- `s2_joined_event`: S2 se ha unido
- `s2_done_event`: S2 ha terminado
- `code_set_event`: S1 ha escrito código en editor (para SC8 sync)

---

*Documento generado el 2026-06-19 a partir del código real de los scripts selenium_FUN01..FUN12.py y los fuentes del plugin mod_sqlab.*

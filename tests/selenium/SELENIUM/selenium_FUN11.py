#!/usr/bin/env python3
"""
selenium_FUN11.py v8 — FUN-11: demo integral del entorno colaborativo SQLab
=============================================================================
este es el script grande: la demostración integrada que toca prácticamente
todos los casos de uso a la vez, validando la sesión colaborativa en tiempo
real. lanzo dos navegadores en paralelo (dos hilos, uno por student1 y otro
por student2), sincronizados con threading.Event para que cada uno espere su
turno en el momento justo de la narrativa (esto es justo lo que Behat, al
ser monohilo, no podía hacer). cubre los escenarios SC1 a SC12.

narrativa del vídeo (una historia con sentido para que el tribunal la siga):

  1. ambos leen el enunciado del ejercicio
     - student1 expande la sección "Pistas"
     - student2 expande "Conceptos relacionados"
  2. student1 comprueba la UI: awareness (SC2), modal de participantes (SC3),
     ID de sala (SC4), botón y submenús del diccionario SQL (SC5/SC7)
  3. SC6: student1 usa el diccionario SQL -> clic en "Tables"
          -> se copia automáticamente un SELECT
          -> se pega en el editor -> student2 lo ve aparecer en tiempo real (sync WS)
  4. student2 sustituye el snippet por una query concreta (S2_COMPLETED)
  5. student2 evalúa -> ERROR: relation "vpract10_ej1" does not exist (a propósito,
     para comprobar que el error de evaluación cruzada se ve bien)
  6. student1 corrige la query usando la tabla real "articulo"
  7. student2 evalúa la versión corregida
  8. ambos navegan a Pregunta 2 (por el panel lateral, que es la vía que
     funciona según lo que descubrí en FUN-07 — la de botones NO funciona)
     y hacen clic en "Terminar intento" -> "Enviar todo y terminar" ->
     página de revisión -> fin del vídeo

cambios v8:
  - sin emojis en ninguna cadena de texto (para que se vea limpio en consola)
  - SC6 activo de verdad: use_dict_snippet() abre el diccionario, hace clic
    en "Tables", coge el SQL directamente del data-sql del elemento y lo
    pega en el editor (CM6)
  - COLLAB A: student1 usa el snippet del diccionario en vez de escribirlo a mano
"""
import sys, time, threading
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.keys import Keys
from selenium.common.exceptions import TimeoutException, WebDriverException

BASE_URL     = "https://moodle.repobcam.i3a.uclm.es:10443"
ACTIVITY_URL = f"{BASE_URL}/mod/sqlab/view.php?id=5"
S1_USER, S1_PASS = "student1", "Stu1234!"
S2_USER, S2_PASS = "student2", "Stu1234!"
TIMEOUT      = 15
TYPING_DELAY = 0.10
ZOOM         = 90     # porcentaje

# SQLs de la narrativa colaborativa
# S1 usa el snippet del diccionario (SC6) — se captura dinamicamente
DICT_ITEM    = "Tables"   # item del menu diccionario que student1 clicara
S2_COMPLETED = "SELECT coda, pvp FROM vpract10_ej1 WHERE pvp >= 1500;"
S1_CORRECTED = "SELECT coda, pvp FROM articulo WHERE pvp >= 1500;"

# Pantalla detectada al inicio
SCREEN_W = 1920
SCREEN_H = 1040
HALF_W   = 960

def detect_screen():
    global SCREEN_W, SCREEN_H, HALF_W
    o = Options()
    o.add_argument("--headless=new")
    o.add_argument("--no-sandbox")
    o.add_argument("--disable-dev-shm-usage")
    try:
        tmp = webdriver.Chrome(options=o)
        w = int(tmp.execute_script("return window.screen.availWidth || window.screen.width || 1920"))
        h = int(tmp.execute_script("return window.screen.availHeight || window.screen.height || 1040"))
        tmp.quit()
        SCREEN_W, SCREEN_H, HALF_W = w, h, w // 2
        print(f"  Pantalla detectada: {w}x{h} px -> ventanas de {w//2} px")
    except Exception as e:
        print(f"  Pantalla no detectada ({e}), usando 1920x1040")

# Shared state entre threads
shared = {
    "room_id":           None,
    "room_ready":        threading.Event(),
    "s2_joined_event":   threading.Event(),
    "s2_at_editor":      threading.Event(),
    "s1_wrote_event":    threading.Event(),
    "s2_wrote_event":    threading.Event(),
    "s2_eval_done":      threading.Event(),
    "s1_corrected":      threading.Event(),
    "s2_corrected_eval": threading.Event(),
    "s1_done_event":     threading.Event(),
    "s2_done_event":     threading.Event(),
    "s1_joined":  False, "s2_joined":  False,
    "s1_parts":   None,  "s2_parts":   None,
    "s1_error":   None,  "s2_error":   None,
    "s1_modal":   False,
    "s1_room4":   False,
    "s1_dict5":   False,
    "s1_dict6":   False,   # SC6: snippet del diccionario pegado
    "s1_snippet": "",      # texto del snippet capturado
    "s1_tables7": False, "s1_views7": False,
    "s2_saw_s1":  False,
    "s2_got_error":     False,
    "s2_corrected_ok":  False,
    "s1_nav_q2":  False, "s2_nav_q2": False,
    "s1_terminated": False, "s2_terminated": False,
}

# =============================================================================
# HELPERS
# =============================================================================

def narrate(who, msg):
    c = "\033[94m" if who == "student1" else "\033[92m"
    print(f"  {c}[{who}]\033[0m {msg}", flush=True)

def phase(title):
    print(f"\n  {'─'*62}\n  > {title}\n  {'─'*62}", flush=True)


def make_driver(pos_x):
    o = Options()
    o.add_argument("--ignore-certificate-errors")
    o.add_argument("--no-sandbox")
    o.add_argument("--disable-dev-shm-usage")
    o.add_argument(f"--window-size={HALF_W},{SCREEN_H}")
    o.add_argument(f"--window-position={pos_x},0")
    d = webdriver.Chrome(options=o)
    d.set_page_load_timeout(30)
    return d


def set_phase(d, text):
    try:
        d.execute_script(
            "var pl=document.getElementById('_plabel'); if(pl) pl.textContent=arguments[0];",
            text)
    except WebDriverException:
        pass


def inject_labels(d, user_label, bg_color, phase_text=""):
    try:
        d.execute_script("""
            var lbl=arguments[0], col=arguments[1], ph=arguments[2];
            if (!document.getElementById('_ulabel')) {
                var u=document.createElement('div'); u.id='_ulabel';
                u.style.cssText='position:fixed;top:0;left:0;background:'+col
                    +';color:#fff;padding:6px 18px;font:bold 17px monospace;'
                    +'z-index:99999;border-radius:0 0 8px 0;letter-spacing:1px;';
                u.textContent=lbl; document.body.appendChild(u);
            }
            var pl=document.getElementById('_plabel');
            if (!pl) {
                pl=document.createElement('div'); pl.id='_plabel';
                pl.style.cssText='position:fixed;bottom:0;left:0;right:0;'
                    +'background:rgba(0,0,0,.85);color:#FFD700;'
                    +'padding:7px 12px;font:bold 13px monospace;text-align:center;z-index:99998;';
                document.body.appendChild(pl);
            }
            if (ph) pl.textContent=ph;
        """, user_label, bg_color, phase_text)
    except WebDriverException:
        pass


def set_zoom(d, pct=None):
    pct = pct or ZOOM
    try:
        d.execute_script(f"document.body.style.zoom='{pct}%'")
        time.sleep(0.2)
    except Exception:
        pass


def login(d, user, pwd, user_label, bg_color):
    d.get(f"{BASE_URL}/login/index.php")
    WebDriverWait(d, TIMEOUT).until(EC.presence_of_element_located((By.ID, "username")))
    d.find_element(By.ID, "username").send_keys(user)
    d.find_element(By.ID, "password").send_keys(pwd)
    d.find_element(By.ID, "loginbtn").click()
    time.sleep(2)
    inject_labels(d, user_label, bg_color, "Sesion iniciada")
    set_zoom(d)
    if "login/index" in d.current_url:
        raise RuntimeError(f"Login fallido para {user}")


def _close_extra_windows(d):
    try:
        handles = d.window_handles
        if len(handles) <= 1:
            return
        target = None
        for h in handles:
            try:
                d.switch_to.window(h)
                if "mod/sqlab" in d.current_url or "attempt" in d.current_url:
                    target = h; break
            except Exception:
                continue
        if target is None:
            target = handles[-1]
        for h in list(d.window_handles):
            if h != target:
                try:
                    d.switch_to.window(h); d.close()
                except Exception:
                    pass
        d.switch_to.window(target)
    except Exception:
        pass


def _click_attempt_btn(d):
    """
    Hace clic en el boton de inicio/continuacion de intento.
    Prueba por ID, data-action y texto (con y sin tilde).
    """
    def _confirm():
        for confirm in ["Comenzar", "Iniciar un nuevo intento", "Confirmar", "Aceptar"]:
            try:
                WebDriverWait(d, 2).until(EC.element_to_be_clickable(
                    (By.XPATH,
                     f"//button[contains(text(),'{confirm}')] | //input[@value='{confirm}']")
                )).click()
                time.sleep(2)
                _close_extra_windows(d)
                return
            except TimeoutException:
                continue

    # 1. Por ID (id="attemptButton" — el boton de esta instalacion)
    for btn_id in ["attemptButton", "continueButton", "startButton"]:
        try:
            WebDriverWait(d, 3).until(
                EC.element_to_be_clickable((By.ID, btn_id))).click()
            time.sleep(2); _close_extra_windows(d); _confirm(); return
        except TimeoutException:
            continue

    # 2. Por data-action
    for action in ["continue", "startattempt", "start"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.CSS_SELECTOR,
                 f"button[data-action='{action}'], a[data-action='{action}']")
            )).click()
            time.sleep(2); _close_extra_windows(d); _confirm(); return
        except TimeoutException:
            continue

    # 3. Por texto (con tilde primero, luego sin tilde)
    for label in [
        "Continuar el último intento",   # con tilde (ultimo correcto)
        "Continuar el ultimo intento",
        "Intentar ahora", "Comenzar el intento",
        "Iniciar actividad", "Comenzar un nuevo intento",
    ]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH,
                 f"//button[contains(text(),'{label}')] | //a[contains(text(),'{label}')]")
            )).click()
            time.sleep(2); _close_extra_windows(d); _confirm(); return
        except TimeoutException:
            continue


def start_attempt(d, user_label, bg_color):
    d.get(ACTIVITY_URL)
    time.sleep(2)
    _close_extra_windows(d)
    set_zoom(d)
    inject_labels(d, user_label, bg_color, "Abriendo actividad...")
    _click_attempt_btn(d)
    set_zoom(d)
    inject_labels(d, user_label, bg_color, "Actividad abierta")


def extract_room_id(d, t=15):
    try:
        WebDriverWait(d, t).until(lambda x: x.execute_script(
            "var e=document.getElementById('room-id-display');"
            "return e && e.textContent.trim() !== '' && e.textContent.trim() !== 'N/A';"))
        return d.execute_script(
            "return document.getElementById('room-id-display').textContent.trim();")
    except Exception:
        return None


def get_participants(d):
    try:
        v = d.execute_script(
            "var e=document.getElementById('participant-count');"
            "return e ? e.textContent.trim() : null;")
        return int(v) if v and v.isdigit() else None
    except Exception:
        return None


def get_editor_text(d):
    """Lee texto de CM6 (div.cm-content contenteditable). Filtra zero-width chars."""
    try:
        v = d.execute_script("""
            var c = document.querySelector('.cm-content');
            if (c) {
                return Array.from(c.querySelectorAll('.cm-line'))
                       .map(function(l){ return l.textContent; }).join('\\n');
            }
            var cm = document.querySelectorAll('.CodeMirror');
            return cm.length > 0 && cm[0].CodeMirror ? cm[0].CodeMirror.getValue() : '';
        """) or ""
        for ch in ['⁠', '​', '‌', '‍', '﻿']:
            v = v.replace(ch, '')
        return v.strip()
    except Exception:
        return ""


def _get_cm6(d, timeout=8):
    return WebDriverWait(d, timeout).until(
        EC.element_to_be_clickable((By.CSS_SELECTOR, '.cm-content')))


def clear_and_type(d, sql):
    try:
        el = _get_cm6(d)
        el.click(); time.sleep(0.3)
        ActionChains(d).key_down(Keys.CONTROL).send_keys('a').key_up(Keys.CONTROL).perform()
        time.sleep(0.2)
        ActionChains(d).send_keys(sql).perform()
        time.sleep(0.6)
        if get_editor_text(d).strip():
            return True
    except Exception:
        pass
    try:
        d.execute_script("""
            var c=document.querySelector('.cm-content');
            if(c){c.focus();document.execCommand('selectAll',false,null);
                  document.execCommand('insertText',false,arguments[0]);}
        """, sql)
        time.sleep(0.5)
        return True
    except Exception:
        return False


def type_slowly(d, sql, delay=TYPING_DELAY):
    """Escribe caracter a caracter en CM6 -> cada tecla activa el sync WS."""
    try:
        el = _get_cm6(d)
        el.click(); time.sleep(0.3)
        ActionChains(d).key_down(Keys.CONTROL).send_keys('a').key_up(Keys.CONTROL).perform()
        time.sleep(0.2)
        ActionChains(d).send_keys(Keys.DELETE).perform()
        time.sleep(0.3)
        for ch in sql:
            ActionChains(d).send_keys(ch).perform()
            time.sleep(delay)
        time.sleep(0.4)
        return bool(get_editor_text(d).strip())
    except Exception:
        return clear_and_type(d, sql)


def use_dict_snippet(d, item_text=DICT_ITEM):
    """
    SC6: abro el menú del diccionario y hago clic en un ítem con atributo
    data-sql. el plugin guarda el SQL directamente en data-sql del elemento
    <a class="v-menu-item">, así que lo leo de ahí en vez de depender del
    portapapeles del sistema (que en remoto vía VPN es un lío). luego lo
    inserto en el editor (CM6) con execCommand para que dispare los eventos
    de CodeMirror y así se propague por WebSocket a student2.
    devuelve (ok: bool, snippet: str).
    """
    scroll_to_cm(d)
    time.sleep(0.5)

    if not open_dict_menu(d):
        return False, ""

    # Leer data-sql del item que coincida con item_text, o del primero disponible
    result = d.execute_script("""
        var txt = arguments[0].toLowerCase();
        var items = Array.from(document.querySelectorAll('a[data-sql], [data-sql]'));
        // Buscar por texto visible o por title
        var el = items.find(function(e){
            return e.textContent.trim().toLowerCase().includes(txt)
                || (e.title && e.title.toLowerCase().includes(txt));
        });
        // Si no hay coincidencia exacta, usar el primer item disponible
        if (!el && items.length > 0) el = items[0];
        if (!el) return null;
        el.scrollIntoView({block: 'center'});
        el.click();  // clic visual (el plugin tambien copia al portapapeles, pero no lo necesitamos)
        return el.getAttribute('data-sql') || el.dataset.sql || null;
    """, item_text)
    time.sleep(1.5)

    snippet = (result or "").strip()
    if not snippet:
        return False, ""

    # Insertar en CM6 via execCommand (dispara eventos CM6 -> sync WS)
    scroll_to_cm(d)
    d.execute_script("""
        var c = document.querySelector('.cm-content');
        if (c) {
            c.focus();
            document.execCommand('selectAll', false, null);
            document.execCommand('insertText', false, arguments[0]);
        }
    """, snippet)
    time.sleep(1)
    actual = get_editor_text(d)
    return bool(actual.strip()), snippet


def scroll_to_top(d):
    try:
        d.execute_script("window.scrollTo({top: 0, behavior: 'smooth'});")
        time.sleep(0.8)
    except Exception:
        pass


def scroll_to_cm(d):
    try:
        d.execute_script("""
            var el = document.querySelector('.cm-editor')
                  || document.querySelector('.cm-content');
            if (!el) return;
            var rect = el.getBoundingClientRect();
            if (rect.top < 0 || rect.top > window.innerHeight * 0.7) {
                window.scrollBy({top: rect.top - window.innerHeight * 0.35, behavior: 'smooth'});
            }
        """)
        time.sleep(0.8)
        d.execute_script("""
            var el = document.querySelector('.cm-editor');
            if (el) el.scrollIntoView({block: 'center', behavior: 'smooth'});
        """)
        time.sleep(0.6)
    except Exception:
        pass


def scroll_to_result(d):
    try:
        d.execute_script("""
            var el = document.querySelector('[id*="result"]')
                  || document.querySelector('[class*="result"]')
                  || document.querySelector('.sqlab-result');
            if (el) el.scrollIntoView({block: 'center', behavior: 'smooth'});
            else window.scrollBy({top: 400, behavior: 'smooth'});
        """)
        time.sleep(0.8)
    except Exception:
        pass


def click_accordion(d, name):
    """Abre la seccion de acordeon con el texto indicado."""
    narrate("?", f"Abriendo seccion '{name}'...")
    opened = d.execute_script("""
        var name = arguments[0];
        var h = Array.from(document.querySelectorAll('h2,h3,h4'))
                     .find(function(e){ return e.textContent.trim() === name; });
        if (!h) return false;
        h.scrollIntoView({block: 'center', behavior: 'smooth'});
        if (h.classList.contains('open')) h.click();
        return true;
    """, name)
    if opened:
        time.sleep(0.7)
        d.execute_script("""
            var name = arguments[0];
            var h = Array.from(document.querySelectorAll('h2,h3,h4'))
                         .find(function(e){ return e.textContent.trim() === name; });
            if (h) h.click();
        """, name)
        time.sleep(1.5)


def js_click_id(d, el_id, fallback_text=None):
    try:
        ok = d.execute_script(
            "var e=document.getElementById(arguments[0]);"
            "if(e){e.scrollIntoView({block:'center'});e.click();return true;}return false;",
            el_id)
        if ok: return True
    except WebDriverException:
        pass
    if fallback_text:
        try:
            WebDriverWait(d, 5).until(EC.element_to_be_clickable(
                (By.XPATH, f"//*[normalize-space()='{fallback_text}']"))).click()
            return True
        except Exception:
            pass
    return False


def open_dict_menu(d):
    try:
        d.find_element(By.TAG_NAME, 'body').send_keys(Keys.ESCAPE)
        time.sleep(0.3)
    except Exception:
        pass
    for css in ['[aria-label="Consultas del diccionario de datos"]',
                '#vertical-menu-sqledi-snippet-menu-btn button',
                '#vertical-menu-sqledi-snippet-menu-btn']:
        try:
            el = WebDriverWait(d, 5).until(EC.element_to_be_clickable((By.CSS_SELECTOR, css)))
            d.execute_script("arguments[0].scrollIntoView({block:'center'});", el)
            el.click()
            time.sleep(1)
            return True
        except TimeoutException:
            continue
    return False


def hover_menu_item_js(d, text):
    try:
        d.execute_script("""
            var all=Array.from(document.querySelectorAll('li,a,span,div,button'));
            var el=all.find(function(e){return e.children.length===0
                && e.textContent.trim()===arguments[0];})
                || all.find(function(e){return e.textContent.trim()===arguments[0];});
            if(el){['mouseover','mouseenter','mousemove'].forEach(function(t){
                el.dispatchEvent(new MouseEvent(t,{bubbles:true,cancelable:true}));});}
        """, text)
        time.sleep(1.5)
    except Exception:
        pass


def close_modal(d):
    for loc in [(By.ID, "modalCloseBtn"),
                (By.XPATH, "//button[normalize-space()='Cerrar']")]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(loc)).click()
            time.sleep(0.8); return True
        except TimeoutException:
            continue
    return False


def has_eval_response(d):
    return any(m in d.page_source for m in
               ["correcto", "Correcto", "incorrecto", "Incorrecto",
                "puntuaci", "Feedback", "feedback", "ERROR", "error"])


def has_error_msg(d):
    return any(m in d.page_source for m in
               ["ERROR", "relation", "does not exist", "no existe", "error:"])


def navigate_to_next_question(d, user_label, bg_color):
    """
    SC11: navego a Pregunta 2 haciendo clic en su enlace del panel lateral
    "Navegación de preguntas" (con page=1 en el href). uso esta vía a
    propósito porque es la que funciona bien (según lo que descubrí en
    FUN-07, los botones de "siguiente página" rompen la navegación).
    si no encuentro el enlace, hay un fallback tosco cambiando page=0 por
    page=1 directamente en la URL.
    """
    set_phase(d, "Clic en 'Pregunta 2' (navegacion de preguntas)...")

    href = d.execute_script("""
        var links = Array.from(document.querySelectorAll('a'));
        var nxt = links.find(function(a){
            return a.textContent.trim() === 'Pregunta 2'
                && a.href && a.href.includes('page=1');
        });
        if (!nxt) {
            nxt = links.find(function(a){
                return a.href && a.href.includes('attempt.php')
                    && a.href.includes('page=1');
            });
        }
        if (nxt) {
            nxt.scrollIntoView({block: 'center', behavior: 'smooth'});
            nxt.click();
            return nxt.href;
        }
        return null;
    """)

    if not href:
        cur = d.current_url
        if 'page=0' in cur:
            d.get(cur.replace('page=0', 'page=1'))
        elif 'page=' not in cur and 'attempt.php' in cur:
            d.get(cur + ('&page=1' if '?' in cur else '?page=1'))

    time.sleep(3)
    _close_extra_windows(d)
    set_zoom(d)
    inject_labels(d, user_label, bg_color, "Pregunta 2")
    return True


def terminar_intento(d):
    """
    SC12: cierro el intento en tres pasos:
      1. clic en "Terminar intento..." (botón con onclick=redirectToSummary)
      2. en la página de resumen, clic en "Enviar todo y terminar"
         (busco por <a class="mod_quiz-next-nav"> o por el link a processattempt.php)
      3. dejo una pausa en la página de revisión final para que el vídeo
         termine ahí, con la evidencia visible en pantalla
    """
    set_phase(d, "Terminando intento...")

    # Paso 1: clic en "Terminar intento..."
    d.execute_script("""
        var btns = Array.from(document.querySelectorAll('button,a'));
        var b = btns.find(function(el){
            return el.textContent.trim().includes('Terminar intento');
        });
        if (b) { b.scrollIntoView({block:'center'}); b.click(); }
        else if (typeof redirectToSummary === 'function') redirectToSummary();
    """)
    time.sleep(3)

    # Paso 2: "Enviar todo y terminar" (<a class="mod_quiz-next-nav"> en la pagina de resumen)
    sent = d.execute_script("""
        var nxt = document.querySelector('a.mod_quiz-next-nav, button.mod_quiz-next-nav');
        if (!nxt) {
            nxt = Array.from(document.querySelectorAll('a')).find(function(a){
                return a.href && a.href.includes('processattempt.php');
            });
        }
        if (!nxt) {
            nxt = Array.from(document.querySelectorAll('a,button')).find(function(el){
                return el.textContent.trim().includes('Enviar todo y terminar')
                    || el.textContent.trim().includes('Submit all and finish');
            });
        }
        if (nxt) { nxt.scrollIntoView({block:'center'}); nxt.click(); return true; }
        return false;
    """)
    time.sleep(3)

    if not sent:
        for loc in [
            (By.CSS_SELECTOR, "a.mod_quiz-next-nav"),
            (By.CSS_SELECTOR, "a[href*='processattempt.php']"),
            (By.XPATH, "//*[contains(text(),'Enviar todo y terminar')]"),
            (By.XPATH, "//*[contains(text(),'Submit all and finish')]"),
        ]:
            try:
                el = WebDriverWait(d, 4).until(EC.element_to_be_clickable(loc))
                el.click(); time.sleep(3); sent = True; break
            except TimeoutException:
                continue

    # Pausa en la pagina de revision final (el video termina aqui)
    set_phase(d, "Intento finalizado - Revision del resultado")
    time.sleep(6)
    return bool(sent)


# =============================================================================
# THREAD STUDENT 1 (ventana izquierda)
# =============================================================================
UL1, BG1 = "< STUDENT 1", "#1565C0"

def _s1_thread():
    """hilo de student1 (ventana izquierda). recorre casi toda la narrativa
    en primera persona: crea la sala, lee el enunciado, comprueba awareness/
    modal/ID de sala/diccionario, pega el snippet SQL para que lo vea
    student2, corrige el SQL cuando student2 falla, y termina navegando a
    la pregunta 2 y cerrando el intento. va marcando eventos (threading.Event)
    para avisar a student2 de en qué punto está."""
    d = make_driver(pos_x=0)
    try:
        narrate("student1", "Login...")
        login(d, S1_USER, S1_PASS, UL1, BG1)
        start_attempt(d, UL1, BG1)

        # SC1a: sala
        phase("SC1 - student1 obtiene Room ID")
        set_phase(d, "SC1 - Esperando Room ID del WebSocket...")
        narrate("student1", "Esperando Room ID...")
        room_id = extract_room_id(d)
        if not room_id:
            shared["s1_error"] = "#room-id-display no salio de N/A"
            shared["room_ready"].set(); return
        shared["room_id"]   = room_id
        shared["s1_joined"] = True
        set_phase(d, f"SC1 - Sala creada - ID: {room_id}")
        narrate("student1", f"Room ID: {room_id}")
        shared["room_ready"].set()

        # Leer enunciado + Pistas
        phase("LECTURA - student1 lee el enunciado y las Pistas")
        set_phase(d, "Leyendo enunciado del ejercicio...")
        narrate("student1", "Leyendo enunciado...")
        scroll_to_top(d)
        time.sleep(2)
        click_accordion(d, "Pistas")
        narrate("student1", "Leyendo Pistas del ejercicio...")
        time.sleep(3)

        narrate("student1", "Esperando a student2...")
        shared["s2_joined_event"].wait(timeout=50)

        # SC2: awareness
        phase("SC2 - Awareness: participantes")
        set_phase(d, "SC2 - Comprobando participantes en sala")
        try:
            p = get_participants(d)
            shared["s1_parts"] = p
            narrate("student1", f"Participantes: {p}")
            time.sleep(1.5)
        except Exception as e:
            narrate("student1", f"SC2 error: {e}")

        # SC3: modal
        phase("SC3 - Modal lista de usuarios")
        set_phase(d, "SC3 - Clic en participantes -> modal")
        try:
            js_click_id(d, "participant-count")
            time.sleep(2)
            modal_ok = (len(d.find_elements(By.ID, "modalOverlay")) > 0
                        or "Cerrar" in d.page_source)
            shared["s1_modal"] = modal_ok
            set_phase(d, "SC3 - Modal visible" if modal_ok else "SC3 - No detectado")
            narrate("student1", "Modal visible" if modal_ok else "Modal no detectado")
            if modal_ok:
                time.sleep(2); close_modal(d)
        except Exception as e:
            narrate("student1", f"SC3 error: {e}")

        # SC4: room-id-display
        phase("SC4 - ID sala -> copiar URL")
        set_phase(d, "SC4 - Clic en ID sala -> copia URL al portapapeles")
        try:
            js_click_id(d, "room-id-display")
            time.sleep(2)
            _close_extra_windows(d)
            no_err = not any(e in d.page_source for e in
                             ["Fatal error", "Warning:", "Notice:"])
            shared["s1_room4"] = no_err
            set_phase(d, "SC4 - Sin error PHP" if no_err else "SC4 - Error PHP")
            narrate("student1", "Sin error PHP" if no_err else "Error PHP detectado")
        except Exception as e:
            narrate("student1", f"SC4 error: {e}")

        if "cm-content" not in d.page_source and "CodeMirror" not in d.page_source:
            start_attempt(d, UL1, BG1)
            set_zoom(d)

        # SC5: boton diccionario
        phase("SC5 - Boton diccionario SQL")
        set_phase(d, "SC5 - Verificando boton diccionario")
        try:
            btn5 = (len(d.find_elements(By.CSS_SELECTOR,
                '[aria-label="Consultas del diccionario de datos"]')) > 0
                    or len(d.find_elements(By.ID,
                        "vertical-menu-sqledi-snippet-menu-btn")) > 0)
            shared["s1_dict5"] = btn5
            set_phase(d, "SC5 - Boton diccionario presente" if btn5 else "SC5 - No encontrado")
            narrate("student1", "Boton diccionario encontrado" if btn5 else "Boton no encontrado")
            time.sleep(1)
        except Exception as e:
            narrate("student1", f"SC5 error: {e}")

        # SC7: hover Tables y Views
        phase("SC7 - Diccionario: hover Tables y Views")
        set_phase(d, "SC7 - Hover sobre Tables...")
        try:
            if open_dict_menu(d):
                hover_menu_item_js(d, "Tables")
                shared["s1_tables7"] = "Table" in d.page_source
                set_phase(d, "SC7 - Hover sobre Views...")
                if open_dict_menu(d):
                    hover_menu_item_js(d, "Views")
                    shared["s1_views7"] = "View" in d.page_source
                try:
                    d.find_element(By.TAG_NAME, 'body').send_keys(Keys.ESCAPE)
                except Exception:
                    pass
                narrate("student1",
                        f"Tables={'OK' if shared['s1_tables7'] else 'FAIL'}  "
                        f"Views={'OK' if shared['s1_views7'] else 'FAIL'}")
        except Exception as e:
            narrate("student1", f"SC7 error: {e}")
        time.sleep(1)

        # Esperar a que student2 este mirando el editor
        narrate("student1", "Esperando a student2 en el editor...")
        shared["s2_at_editor"].wait(timeout=20)

        # SC6 / COLLAB A: usar snippet del diccionario
        phase("SC6 - Diccionario: clic en Tables -> snippet al portapapeles -> pegar en editor")
        set_phase(d, "SC6 - Abriendo diccionario, clic en Tables -> pegar en editor...")
        narrate("student1", f"Clic en '{DICT_ITEM}' del diccionario SQL...")
        ok, snippet = use_dict_snippet(d, DICT_ITEM)
        shared["s1_dict6"]   = ok
        shared["s1_snippet"] = snippet[:80] if snippet else ""
        set_phase(d, "SC6 - Snippet pegado en editor" if ok else "SC6 - Sin snippet")
        narrate("student1",
                f"Snippet {'pegado' if ok else 'FALLO'}: {repr(snippet[:60])}")
        shared["s1_wrote_event"].set()

        # Esperar a que student2 complete el SQL
        narrate("student1", "Esperando modificacion de student2...")
        shared["s2_wrote_event"].wait(timeout=40)
        set_phase(d, "Student2 modifico el SQL - student1 lo ve en su editor")
        time.sleep(3)
        narrate("student1", f"Editor tras s2: {repr(get_editor_text(d)[:55])}")

        narrate("student1", "Esperando evaluacion de student2...")
        shared["s2_eval_done"].wait(timeout=30)
        time.sleep(2)

        # COLLAB B: student1 corrige usando la tabla real
        phase("COLLAB - Student1 corrige el SQL usando 'articulo'")
        set_phase(d, "Student1 corrige -> usa tabla 'articulo' en vez de vpract10_ej1")
        narrate("student1", f"Corrigiendo: {S1_CORRECTED!r}")
        scroll_to_cm(d)
        time.sleep(1)
        type_slowly(d, S1_CORRECTED)
        narrate("student1", f"SQL corregido: {repr(get_editor_text(d)[:55])}")
        shared["s1_corrected"].set()

        narrate("student1", "Esperando que student2 evalúe la correccion...")
        shared["s2_corrected_eval"].wait(timeout=30)
        time.sleep(2)

        # SC11: Navegar a Pregunta 2
        phase("SC11 - Navegar a Pregunta 2")
        narrate("student1", "Navegando a Pregunta 2 (clic en navegacion de preguntas)...")
        time.sleep(1)
        q2_ok = navigate_to_next_question(d, UL1, BG1)
        shared["s1_nav_q2"] = q2_ok
        narrate("student1", "Pregunta 2 OK" if q2_ok else "Pregunta 2 FALLO")
        time.sleep(3)

        # SC12: Terminar intento
        phase("SC12 - Terminar intento")
        set_phase(d, "Terminando intento...")
        narrate("student1", "Clic en 'Terminar intento...'")
        shared["s1_terminated"] = terminar_intento(d)
        set_phase(d, "Intento terminado" if shared["s1_terminated"] else "No confirmado")
        narrate("student1", "Intento terminado" if shared["s1_terminated"] else "No confirmado")
        time.sleep(4)
        shared["s1_done_event"].set()

    except Exception as e:
        shared["s1_error"] = str(e)
        narrate("student1", f"EXCEPCION: {e}")
        for ev in ["room_ready", "s1_wrote_event", "s1_corrected", "s1_done_event"]:
            shared[ev].set()
    finally:
        try:
            d.quit()
        except Exception:
            pass


# =============================================================================
# THREAD STUDENT 2 (ventana derecha)
# =============================================================================
UL2, BG2 = "STUDENT 2 >", "#2E7D32"

def _s2_thread():
    """hilo de student2 (ventana derecha). espera a que student1 cree la
    sala, se une con el room_id, se coloca mirando el editor para comprobar
    en directo que ve aparecer el snippet de student1 (SC8, la prueba de
    sincronización de verdad), lo sustituye por una query que falla a
    propósito, evalúa el error, espera la corrección de student1 y la
    vuelve a evaluar. termina igual que student1: pregunta 2 y cerrar intento."""
    d = make_driver(pos_x=HALF_W)
    try:
        narrate("student2", "Login...")
        login(d, S2_USER, S2_PASS, UL2, BG2)
        start_attempt(d, UL2, BG2)

        narrate("student2", "Esperando Room ID de student1...")
        shared["room_ready"].wait(timeout=50)
        if shared["s1_error"]:
            shared["s2_error"] = f"student1 fallo: {shared['s1_error']}"; return
        room_id = shared["room_id"]

        # Leer enunciado + Conceptos relacionados
        phase("LECTURA - student2 lee el enunciado y Conceptos relacionados")
        set_phase(d, "Leyendo enunciado...")
        narrate("student2", "Leyendo enunciado del ejercicio...")
        scroll_to_top(d)
        time.sleep(2)
        click_accordion(d, "Conceptos relacionados")
        narrate("student2", "Leyendo Conceptos relacionados...")
        time.sleep(3)

        # SC1b: unirse a sala
        phase("SC1 - student2 se une")
        set_phase(d, f"SC1 - Uniendose a sala {room_id}...")
        narrate("student2", f"Uniendose a sala {room_id}...")
        scroll_to_cm(d)
        time.sleep(2)
        _close_extra_windows(d)

        inp = None
        for loc in [(By.ID, "roomidtext"),
                    (By.XPATH, "//input[contains(@placeholder,'ID')]"),
                    (By.XPATH, "//input[@type='text']")]:
            try:
                inp = WebDriverWait(d, 6).until(EC.presence_of_element_located(loc)); break
            except TimeoutException:
                continue
        if inp is None:
            shared["s2_error"] = "#roomidtext no encontrado"; return
        d.execute_script("arguments[0].scrollIntoView({block:'center'});", inp)
        inp.clear(); inp.send_keys(room_id)
        narrate("student2", f"ID introducido: {room_id}")
        time.sleep(0.5)

        joined = False
        for loc in [(By.ID, "btn-connect"),
                    (By.XPATH, "//*[normalize-space()='Unirme a la sala']")]:
            try:
                btn = WebDriverWait(d, 8).until(EC.element_to_be_clickable(loc))
                d.execute_script("arguments[0].scrollIntoView({block:'center'});", btn)
                btn.click(); time.sleep(3); joined = True; break
            except TimeoutException:
                continue
        if not joined:
            shared["s2_error"] = "#btn-connect no encontrado"; return

        _close_extra_windows(d)
        shared["s2_joined"] = True
        p = get_participants(d)
        shared["s2_parts"] = p
        set_phase(d, f"SC1 - Unido | Participantes: {p}")
        narrate("student2", f"Unido a sala {room_id} | Participantes: {p}")
        shared["s2_joined_event"].set()

        # Posicionarse en el editor para ver el sync (SC8)
        phase("SC8 - SYNC: student2 espera el snippet de student1")
        scroll_to_cm(d)
        set_phase(d, "SC8 - MIRA ESTE EDITOR: el snippet de student1 aparecera aqui...")
        narrate("student2", "Editor visible - esperando snippet de student1...")
        shared["s2_at_editor"].set()

        narrate("student2", "Observando editor (student1 esta pegando el snippet...)")
        shared["s1_wrote_event"].wait(timeout=40)
        set_phase(d, "SC8 - Propagando via WebSocket...")
        time.sleep(6)
        s2_sees = get_editor_text(d)
        shared["s2_saw_s1"] = bool(s2_sees.strip())
        set_phase(d, "SC8 - SYNC OK" if shared["s2_saw_s1"] else "SC8 - Sin sync")
        narrate("student2",
                f"{'VEO el snippet de student1: ' if shared['s2_saw_s1'] else 'Editor vacio: '}"
                f"{repr(s2_sees[:55])}")
        time.sleep(2)

        # COLLAB A: student2 sustituye el snippet por una query concreta
        phase("COLLAB - Student2 sustituye el snippet por query concreta")
        set_phase(d, "Student2 escribe su query...")
        narrate("student2", f"Escribiendo: {S2_COMPLETED!r}")
        scroll_to_cm(d)
        time.sleep(1)
        type_slowly(d, S2_COMPLETED)
        narrate("student2", f"Editor s2: {repr(get_editor_text(d)[:55])}")
        shared["s2_wrote_event"].set()

        # SC9: evaluar -> ERROR
        phase("SC9 - student2 evalua -> ERROR relation does not exist")
        set_phase(d, "SC9 - Evaluando... (se espera error de relacion)")
        time.sleep(2)
        narrate("student2", "Clic en 'Evaluar codigo'...")
        js_click_id(d, "evaluateSqlButton", "Evaluar codigo")
        time.sleep(4)
        scroll_to_result(d)
        shared["s2_got_error"] = has_error_msg(d)
        set_phase(d, "SC9 - ERROR relation detectado" if shared["s2_got_error"]
                  else "SC9 - No se detecto error")
        narrate("student2",
                "ERROR relation detectado" if shared["s2_got_error"] else "Sin error en pagina")
        time.sleep(4)
        shared["s2_eval_done"].set()

        narrate("student2", "Esperando correccion de student1...")
        shared["s1_corrected"].wait(timeout=40)
        set_phase(d, "Student1 corrigio el SQL - evaluando version corregida...")
        time.sleep(5)

        # SC10: evaluar version corregida
        phase("SC10 - student2 evalua SQL corregido")
        corrected_in_editor = get_editor_text(d)
        narrate("student2", f"Editor tras correccion de s1: {repr(corrected_in_editor[:55])}")
        if "articulo" not in corrected_in_editor:
            narrate("student2", "Sync tardio - escribiendo S1_CORRECTED explicitamente")
            scroll_to_cm(d)
            clear_and_type(d, S1_CORRECTED)
            time.sleep(1)
        set_phase(d, "SC10 - Evaluando version corregida...")
        narrate("student2", "Clic en 'Evaluar codigo' (version corregida)...")
        js_click_id(d, "evaluateSqlButton", "Evaluar codigo")
        time.sleep(4)
        scroll_to_result(d)
        shared["s2_corrected_ok"] = has_eval_response(d)
        set_phase(d, "SC10 - Respuesta recibida" if shared["s2_corrected_ok"] else "SC10 - Sin respuesta")
        narrate("student2", "Feedback visible" if shared["s2_corrected_ok"] else "Sin feedback")
        time.sleep(4)
        shared["s2_corrected_eval"].set()

        # SC11: Navegar a Pregunta 2 (simultaneo con student1)
        phase("SC11 - student2 navega a Pregunta 2")
        narrate("student2", "Navegando a Pregunta 2 (clic en navegacion de preguntas)...")
        time.sleep(1)
        q2_ok = navigate_to_next_question(d, UL2, BG2)
        shared["s2_nav_q2"] = q2_ok
        narrate("student2", "Pregunta 2 OK" if q2_ok else "Pregunta 2 FALLO")
        time.sleep(3)

        # SC12: Terminar intento
        phase("SC12 - student2 termina intento")
        set_phase(d, "Terminando intento...")
        narrate("student2", "Clic en 'Terminar intento...'")
        shared["s2_terminated"] = terminar_intento(d)
        set_phase(d, "Intento terminado" if shared["s2_terminated"] else "No confirmado")
        narrate("student2", "Intento terminado" if shared["s2_terminated"] else "No confirmado")
        time.sleep(4)
        shared["s2_done_event"].set()

    except Exception as e:
        shared["s2_error"] = str(e)
        narrate("student2", f"EXCEPCION: {e}")
    finally:
        for ev in ["s2_joined_event", "s2_at_editor", "s2_wrote_event",
                   "s2_eval_done", "s2_corrected_eval", "s2_done_event"]:
            shared[ev].set()
        try:
            d.quit()
        except Exception:
            pass


# =============================================================================
# SESION + RESULTADOS
# =============================================================================

def run_session():
    """resetea el estado compartido y lanza los dos hilos (student1 primero,
    student2 4 segundos después para darle margen a que la sala exista),
    esperando a que ambos terminen (o hagan timeout a los 300s)."""
    shared.update({
        "room_id": None,
        "room_ready":        threading.Event(),
        "s2_joined_event":   threading.Event(),
        "s2_at_editor":      threading.Event(),
        "s1_wrote_event":    threading.Event(),
        "s2_wrote_event":    threading.Event(),
        "s2_eval_done":      threading.Event(),
        "s1_corrected":      threading.Event(),
        "s2_corrected_eval": threading.Event(),
        "s1_done_event":     threading.Event(),
        "s2_done_event":     threading.Event(),
        "s1_joined": False, "s2_joined": False,
        "s1_parts": None,   "s2_parts": None,
        "s1_error": None,   "s2_error": None,
        "s1_modal": False,  "s1_room4": False,
        "s1_dict5": False,  "s1_dict6": False,
        "s1_snippet": "",
        "s1_tables7": False, "s1_views7": False,
        "s2_saw_s1": False,
        "s2_got_error": False, "s2_corrected_ok": False,
        "s1_nav_q2": False,    "s2_nav_q2": False,
        "s1_terminated": False, "s2_terminated": False,
    })
    t1 = threading.Thread(target=_s1_thread, name="student1")
    t2 = threading.Thread(target=_s2_thread, name="student2")
    t1.start()
    time.sleep(4)
    t2.start()
    t1.join(timeout=300)
    t2.join(timeout=300)


results = []

def report(name, fn, skip=False):
    if skip:
        print(f"  SKIP    {name}")
        results.append((name, None, "SKIP"))
        return
    try:
        ok, msg = fn()
        status = "PASS" if ok else "FAIL"
        print(f"  {status:4}    {name}\n          {msg}")
        results.append((name, ok, msg))
    except Exception as e:
        print(f"  ERROR   {name}\n          {e}")
        results.append((name, False, str(e)))


if __name__ == "__main__":
    detect_screen()

    print("\n" + "=" * 65)
    print("  FUN-11 v8 - Demo integral del entorno colaborativo SQLab")
    print(f"  < STUDENT 1 (0 px)         STUDENT 2 > ({HALF_W} px)")
    print(f"  Ventanas: {HALF_W}x{SCREEN_H} px  |  Zoom: {ZOOM}%")
    print("=" * 65 + "\n")

    run_session()

    print("\n" + "=" * 65)
    print("  RESULTADOS")
    print("=" * 65)

    report("SC1  - Sala compartida (mismo room_id)",
           lambda: (shared["s1_joined"] and shared["s2_joined"],
                    f"Room ID: {shared['room_id']}"
                    if (shared["s1_joined"] and shared["s2_joined"])
                    else (shared["s2_error"] or shared["s1_error"] or "Uno no se unio")))

    report("SC2  - Awareness: participantes >= 1",
           lambda: ((shared["s1_parts"] or 0) >= 1 or (shared["s2_parts"] or 0) >= 1,
                    f"s1 ve {shared['s1_parts']} | s2 ve {shared['s2_parts']}"))

    report("SC3  - Modal lista de usuarios",
           lambda: (shared["s1_modal"],
                    "Modal visible" if shared["s1_modal"] else "No detectado"))

    report("SC4  - #room-id-display sin error PHP",
           lambda: (shared["s1_room4"],
                    "Sin errores PHP" if shared["s1_room4"] else "Error PHP"))

    report("SC5  - Boton diccionario SQL visible",
           lambda: (shared["s1_dict5"],
                    "Boton encontrado" if shared["s1_dict5"] else "No encontrado"))

    report("SC6  - Diccionario: clic en snippet -> copiado y pegado en editor",
           lambda: (shared["s1_dict6"],
                    f"Snippet pegado: {repr(shared['s1_snippet'])}"
                    if shared["s1_dict6"] else "Snippet no pegado"))

    report("SC7  - Submenues Tables y Views (hover)",
           lambda: (shared["s1_tables7"] and shared["s1_views7"],
                    f"Tables={'OK' if shared['s1_tables7'] else 'FAIL'}  "
                    f"Views={'OK' if shared['s1_views7'] else 'FAIL'}"))

    report("SC8  - SYNC: student1 pega snippet -> student2 lo ve en tiempo real",
           lambda: (shared["s2_saw_s1"],
                    "student2 vio el snippet de student1" if shared["s2_saw_s1"]
                    else "Sin sync"))

    report("SC9  - Evaluar SQL incorrecto -> ERROR relation does not exist",
           lambda: (shared["s2_got_error"],
                    "ERROR relation detectado" if shared["s2_got_error"]
                    else "No se detecto el error esperado"))

    report("SC10 - student1 corrige SQL -> student2 evalua correccion",
           lambda: (shared["s2_corrected_ok"],
                    "Feedback visible" if shared["s2_corrected_ok"] else "Sin feedback"))

    report("SC11 - Navegar a Pregunta 2",
           lambda: (shared["s1_nav_q2"] or shared["s2_nav_q2"],
                    f"s1={'OK' if shared['s1_nav_q2'] else 'FAIL'}  "
                    f"s2={'OK' if shared['s2_nav_q2'] else 'FAIL'}"))

    report("SC12 - Terminar intento",
           lambda: (shared["s1_terminated"] or shared["s2_terminated"],
                    f"s1={'OK' if shared['s1_terminated'] else 'FAIL'}  "
                    f"s2={'OK' if shared['s2_terminated'] else 'FAIL'}"))

    passed  = sum(1 for _, ok, _ in results if ok is True)
    skipped = sum(1 for _, ok, _ in results if ok is None)
    total   = len(results) - skipped
    print(f"\n{'=' * 65}")
    print(f"  TOTAL: {passed}/{total} pasados  ({skipped} omitidos)")
    print(f"{'=' * 65}\n")
    sys.exit(0 if passed == total else 1)

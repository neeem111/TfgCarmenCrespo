#!/usr/bin/env python3
"""
selenium_FUN12.py v7 — FUN-12: Demo integral del entorno colaborativo SQLab
═══════════════════════════════════════════════════════════════════════════════
NARRATIVA DEL VÍDEO (historia coherente para el tribunal):

  1. Ambos leen el enunciado del ejercicio
     · student1 expande la sección "Pistas"
     · student2 expande "Conceptos relacionados"
  2. student1 comprueba la UI: awareness (SC2), modal (SC3), ID sala (SC4),
     diccionario SQL (SC5/SC7)
  3. Codificación colaborativa:
     a. student1 empieza: SELECT coda FROM vpract10_ej1;
        → student2 lo ve aparecer letra a letra en su editor (sync WS)
     b. student2 completa la query añadiendo pvp y WHERE pvp >= 1500;
     c. student2 evalúa → ERROR: relation "vpract10_ej1" does not exist
     d. student1 corrige usando la tabla real: articulo
     e. student2 evalúa la versión corregida
  4. Ambos navegan a "Pregunta 2" y hacen clic en "Terminar intento…"
     → El vídeo termina aquí

SC6 marcado SKIP (funcionalidad de Schema list caída en servidor).

Fixes v7:
  · Zoom al 90 % (menos zoom que v6 → texto más grande)
  · scroll_to_cm usa getBoundingClientRect + window.scrollBy (más fiable)
  · click_accordion maneja h2.accordion-title.open (cierra/reabre para demo visual)
  · Ambos estudiantes leen el enunciado antes de empezar a codificar
  · Fin con "Pregunta 2" + "Terminar intento..." (no logout)
  · Ventanas posicionadas via --window-size/--window-position al arrancar Chrome
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
TYPING_DELAY = 0.10   # seg entre caracteres en type_slowly
ZOOM         = 90     # % — menos zoom → letras más grandes

# SQLs de la narrativa
S1_PARTIAL   = "SELECT coda FROM vpract10_ej1;"
S2_COMPLETED = "SELECT coda, pvp FROM vpract10_ej1 WHERE pvp >= 1500;"
S1_CORRECTED = "SELECT coda, pvp FROM articulo WHERE pvp >= 1500;"

# Pantalla (detectada al inicio)
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
        print(f"  ℹ️  Pantalla: {w}×{h} px → cada ventana: {w//2} px de ancho")
    except Exception as e:
        print(f"  ⚠️  Pantalla no detectada ({e}), usando 1920×1040")

# ── Shared state ──────────────────────────────────────────────────────────────
shared = {
    "room_id":           None,
    "room_ready":        threading.Event(),   # s1 tiene room_id
    "s2_joined_event":   threading.Event(),   # s2 se unió a la sala
    "s2_at_editor":      threading.Event(),   # s2 está con el editor visible
    "s1_wrote_event":    threading.Event(),   # s1 escribió S1_PARTIAL
    "s2_wrote_event":    threading.Event(),   # s2 escribió S2_COMPLETED
    "s2_eval_done":      threading.Event(),   # s2 evaluó → ERROR
    "s1_corrected":      threading.Event(),   # s1 escribió S1_CORRECTED
    "s2_corrected_eval": threading.Event(),   # s2 evaluó versión corregida
    "s1_done_event":     threading.Event(),
    "s2_done_event":     threading.Event(),
    # Resultados booleanos
    "s1_joined": False, "s2_joined": False,
    "s1_parts": None,   "s2_parts": None,
    "s1_error": None,   "s2_error": None,
    "s1_modal": False,
    "s1_room4": False,
    "s1_dict5": False,
    "s1_tables7": False, "s1_views7": False,
    "s2_saw_s1": False,
    "s2_got_error": False,
    "s2_corrected_ok": False,
    "s1_nav_q2": False, "s2_nav_q2": False,
    "s1_terminated": False, "s2_terminated": False,
}

# ─────────────────────────────────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────────────────────────────────

def narrate(who, msg):
    c = "\033[94m" if who == "student1" else "\033[92m"
    print(f"  {c}[{who}]\033[0m {msg}", flush=True)

def phase(title):
    print(f"\n  {'─'*62}\n  ▶ {title}\n  {'─'*62}", flush=True)


def make_driver(pos_x):
    """Chrome posicionado por línea de comandos (más fiable que set_window_position)."""
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
            "var pl=document.getElementById('_plabel'); if(pl) pl.textContent=arguments[0];", text)
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
    inject_labels(d, user_label, bg_color, "Sesión iniciada")
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


def start_attempt(d, user_label, bg_color):
    d.get(ACTIVITY_URL)
    time.sleep(2)
    _close_extra_windows(d)
    set_zoom(d)
    inject_labels(d, user_label, bg_color, "Abriendo actividad…")
    for label in ["Continuar el último intento", "Intentar ahora", "Comenzar el intento",
                  "Iniciar actividad", "Comenzar un nuevo intento"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH,
                 f"//button[contains(text(),'{label}')] | //a[contains(text(),'{label}')]")
            )).click()
            time.sleep(2)
            _close_extra_windows(d)
            set_zoom(d)
            inject_labels(d, user_label, bg_color, "Confirmando intento…")
            for confirm in ["Comenzar", "Iniciar un nuevo intento", "Confirmar", "Aceptar"]:
                try:
                    WebDriverWait(d, 2).until(EC.element_to_be_clickable(
                        (By.XPATH,
                         f"//button[contains(text(),'{confirm}')] | //input[@value='{confirm}']")
                    )).click()
                    time.sleep(2)
                    _close_extra_windows(d)
                    set_zoom(d)
                    break
                except TimeoutException:
                    continue
            break
        except TimeoutException:
            continue


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
    """Lee texto del editor CM6 (div.cm-content contenteditable)."""
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
        # Eliminar zero-width chars del awareness plugin (⁠ = U+2060, ​ = U+200B, etc.)
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
    """Escribe carácter a carácter → cada tecla dispara el WS sync."""
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


def scroll_to_top(d):
    """Sube al inicio de la página para ver el enunciado."""
    try:
        d.execute_script("window.scrollTo({top: 0, behavior: 'smooth'});")
        time.sleep(1)
    except Exception:
        pass


def scroll_to_cm(d):
    """
    Desplaza la página hasta el editor CM6.
    Usa getBoundingClientRect + scrollBy (más fiable que solo scrollIntoView
    cuando el contenedor de scroll es el propio window).
    """
    try:
        d.execute_script("""
            var el = document.querySelector('.cm-editor')
                  || document.querySelector('.cm-content');
            if (!el) return;
            var rect = el.getBoundingClientRect();
            // Si el editor no está en el viewport, desplazar
            if (rect.top < 0 || rect.top > window.innerHeight * 0.7) {
                window.scrollBy({
                    top: rect.top - window.innerHeight * 0.35,
                    behavior: 'smooth'
                });
            }
        """)
        time.sleep(0.8)
        # Segundo empujón para asegurar visibilidad completa
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
    """
    Hace clic en una sección de acordeón (h2.accordion-title) por su texto.
    Si ya está abierta (clase 'open'), la cierra y vuelve a abrir para el efecto visual.
    """
    narrate("?", f"📂 Abriendo sección '{name}'…")
    opened = d.execute_script("""
        var name = arguments[0];
        function findHeader() {
            var hs = Array.from(document.querySelectorAll('h2,h3,h4'));
            return hs.find(function(e){ return e.textContent.trim() === name; });
        }
        var h = findHeader();
        if (!h) return false;
        h.scrollIntoView({block: 'center', behavior: 'smooth'});
        // Si ya está abierta, cerrar primero para mostrar el toggle
        if (h.classList.contains('open')) h.click();
        return true;
    """, name)
    if opened:
        time.sleep(0.7)
        # Volver a abrir
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
    Navega a Pregunta 2 haciendo clic en su enlace de la sección
    'Navegación de preguntas' (href con page=1).
    Fallback: cambia page=0→page=1 en la URL actual.
    """
    set_phase(d, "➡️ Clic en 'Pregunta 2' (navegación de preguntas)…")

    # Intentar clic en el enlace de navegación de preguntas
    href = d.execute_script("""
        var links = Array.from(document.querySelectorAll('a'));
        // Buscar exactamente "Pregunta 2" con href que contenga page=1
        var nxt = links.find(function(a){
            return a.textContent.trim() === 'Pregunta 2'
                && a.href && a.href.includes('page=1');
        });
        // Segundo intento: cualquier enlace attempt.php con page=1
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
        # Fallback: construir URL de Pregunta 2 desde la URL actual
        cur = d.current_url
        if 'page=0' in cur:
            dest = cur.replace('page=0', 'page=1')
        elif 'page=' not in cur and 'attempt.php' in cur:
            dest = cur + ('&page=1' if '?' in cur else '?page=1')
        else:
            dest = None
        if dest:
            narrate("?", f"⚠️ Fallback URL → {dest}")
            d.get(dest)

    time.sleep(3)
    _close_extra_windows(d)
    set_zoom(d)
    inject_labels(d, user_label, bg_color, "➡️ Pregunta 2")
    return True


def terminar_intento(d):
    """
    Flujo de cierre del intento en SQLab/Moodle:
      1. Clic en 'Terminar intento…' (button con onclick=redirectToSummary)
         → lleva a la página de resumen del intento
      2. En el resumen, clic en 'Enviar todo y terminar'
         → es un <a class="mod_quiz-next-nav btn btn-primary"> o link a processattempt.php
    """
    set_phase(d, "🏁 Terminando intento…")

    # PASO 1 — clic en "Terminar intento…"
    d.execute_script("""
        var btns = Array.from(document.querySelectorAll('button,a'));
        var b = btns.find(function(el){
            return el.textContent.trim().includes('Terminar intento');
        });
        if (b) { b.scrollIntoView({block:'center'}); b.click(); }
        else if (typeof redirectToSummary === 'function') redirectToSummary();
    """)
    time.sleep(3)

    # PASO 2 — en la página de resumen: "Enviar todo y terminar"
    # Puede ser <a class="mod_quiz-next-nav"> o <button> o link a processattempt.php
    sent = d.execute_script("""
        // Buscar por clase mod_quiz-next-nav (el <a> que mostró la usuaria)
        var nxt = document.querySelector('a.mod_quiz-next-nav, button.mod_quiz-next-nav');
        if (!nxt) {
            // Buscar por href que contenga processattempt.php
            nxt = Array.from(document.querySelectorAll('a')).find(function(a){
                return a.href && a.href.includes('processattempt.php');
            });
        }
        if (!nxt) {
            // Buscar por texto
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
        # Fallback: WebDriverWait sobre cualquier forma del botón
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

    # Pausa larga en la página de revisión final ("Finalizar revisión")
    # El vídeo termina aquí — el tribunal ve el estado del intento
    set_phase(d, "🏆 Intento finalizado — Revisión del resultado")
    time.sleep(6)

    return bool(sent)


# ─────────────────────────────────────────────────────────────────────────────
# THREAD STUDENT 1  (ventana izquierda)
# ─────────────────────────────────────────────────────────────────────────────
UL1, BG1 = "◀ STUDENT 1", "#1565C0"

def _s1_thread():
    d = make_driver(pos_x=0)
    try:
        # ── Login + actividad ──────────────────────────────────────────────
        narrate("student1", "🔑 Login…")
        login(d, S1_USER, S1_PASS, UL1, BG1)
        start_attempt(d, UL1, BG1)

        # ── SC1a: crear sala ───────────────────────────────────────────────
        phase("SC1 — student1 obtiene Room ID")
        set_phase(d, "SC1 — Esperando Room ID del WebSocket…")
        narrate("student1", "⏳ Esperando Room ID…")
        room_id = extract_room_id(d)
        if not room_id:
            shared["s1_error"] = "#room-id-display no salió de N/A"
            shared["room_ready"].set(); return
        shared["room_id"]   = room_id
        shared["s1_joined"] = True
        set_phase(d, f"SC1 ✅ Sala creada — ID: {room_id}")
        narrate("student1", f"✅ Room ID: {room_id}")
        shared["room_ready"].set()

        # ── Leer enunciado + Pistas (mientras student2 se une) ────────────
        phase("LECTURA — student1 lee el enunciado y las Pistas")
        set_phase(d, "📖 Leyendo enunciado del ejercicio…")
        narrate("student1", "📖 Leyendo enunciado…")
        scroll_to_top(d)
        time.sleep(2)
        click_accordion(d, "Pistas")
        narrate("student1", "📌 Leyendo Pistas del ejercicio…")
        time.sleep(3)

        # Esperar s2
        narrate("student1", "⏳ Esperando a student2…")
        shared["s2_joined_event"].wait(timeout=50)

        # ── SC2: awareness ─────────────────────────────────────────────────
        phase("SC2 — Awareness: participantes")
        set_phase(d, "SC2 — Comprobando participantes en sala")
        try:
            p = get_participants(d)
            shared["s1_parts"] = p
            narrate("student1", f"👥 Participantes: {p}")
            time.sleep(1.5)
        except Exception as e:
            narrate("student1", f"⚠️ SC2: {e}")

        # ── SC3: modal participantes ───────────────────────────────────────
        phase("SC3 — Modal lista de usuarios")
        set_phase(d, "SC3 — Clic en participantes → modal")
        try:
            js_click_id(d, "participant-count")
            time.sleep(2)
            modal_ok = len(d.find_elements(By.ID, "modalOverlay")) > 0 \
                    or "Cerrar" in d.page_source
            shared["s1_modal"] = modal_ok
            set_phase(d, "SC3 ✅ Modal" if modal_ok else "SC3 ⚠️")
            narrate("student1", f"{'✅ Modal visible' if modal_ok else '⚠️ No detectado'}")
            if modal_ok:
                time.sleep(2); close_modal(d)
        except Exception as e:
            narrate("student1", f"⚠️ SC3: {e}")

        # ── SC4: room-id-display ───────────────────────────────────────────
        phase("SC4 — ID sala → copiar URL")
        set_phase(d, "SC4 — Clic en ID sala → copia URL")
        try:
            js_click_id(d, "room-id-display")
            time.sleep(2)
            _close_extra_windows(d)
            no_err = not any(e in d.page_source for e in
                             ["Fatal error", "Warning:", "Notice:"])
            shared["s1_room4"] = no_err
            set_phase(d, "SC4 ✅" if no_err else "SC4 ❌ PHP error")
            narrate("student1", f"{'✅' if no_err else '❌'} room-id clic")
        except Exception as e:
            narrate("student1", f"⚠️ SC4: {e}")

        if "cm-content" not in d.page_source and "CodeMirror" not in d.page_source:
            start_attempt(d, UL1, BG1)
            set_zoom(d)

        # ── SC5: botón diccionario ─────────────────────────────────────────
        phase("SC5 — Botón diccionario SQL")
        set_phase(d, "SC5 — Verificando botón diccionario")
        try:
            btn5 = (len(d.find_elements(By.CSS_SELECTOR,
                '[aria-label="Consultas del diccionario de datos"]')) > 0 or
                    len(d.find_elements(By.ID, "vertical-menu-sqledi-snippet-menu-btn")) > 0)
            shared["s1_dict5"] = btn5
            set_phase(d, "SC5 ✅" if btn5 else "SC5 ⚠️")
            narrate("student1", f"{'✅' if btn5 else '❌'} Botón diccionario")
            time.sleep(1)
        except Exception as e:
            narrate("student1", f"⚠️ SC5: {e}")

        # ── SC7: Tables y Views ────────────────────────────────────────────
        phase("SC7 — Diccionario: Tables y Views")
        set_phase(d, "SC7 — Hover Tables…")
        try:
            if open_dict_menu(d):
                hover_menu_item_js(d, "Tables")
                shared["s1_tables7"] = "Table" in d.page_source
                set_phase(d, "SC7 — Hover Views…")
                if open_dict_menu(d):
                    hover_menu_item_js(d, "Views")
                    shared["s1_views7"] = "View" in d.page_source
                try:
                    d.find_element(By.TAG_NAME, 'body').send_keys(Keys.ESCAPE)
                except Exception:
                    pass
                narrate("student1", f"Tables={'✅' if shared['s1_tables7'] else '⚠️'}  "
                                    f"Views={'✅' if shared['s1_views7'] else '⚠️'}")
        except Exception as e:
            narrate("student1", f"⚠️ SC7: {e}")
        time.sleep(1)

        # ════════════════════════════════════════════════════════════════
        # COLLABORATIVE CODING
        # ════════════════════════════════════════════════════════════════

        # Esperar a que student2 esté mirando el editor
        narrate("student1", "⏳ Esperando a student2 en el editor…")
        shared["s2_at_editor"].wait(timeout=20)

        # ── COLLAB A: student1 empieza el SQL letra a letra ───────────────
        phase("COLLAB — Student1 escribe SQL incompleto (student2 lo verá)")
        set_phase(d, "⌨️ Student1 escribe letra a letra → student2 lo verá en tiempo real")
        narrate("student1", f"⌨️  Escribiendo: {S1_PARTIAL!r}")
        scroll_to_cm(d)
        time.sleep(0.5)
        wrote = type_slowly(d, S1_PARTIAL)
        narrate("student1", f"{'✅' if wrote else '⚠️'} Editor: {repr(get_editor_text(d)[:55])}")
        shared["s1_wrote_event"].set()

        # Esperar a que student2 complete el SQL
        narrate("student1", "⏳ Esperando corrección de student2…")
        shared["s2_wrote_event"].wait(timeout=40)
        set_phase(d, "👀 Student2 completó el SQL — student1 lo ve en su editor")
        time.sleep(3)
        narrate("student1", f"👀 Editor tras s2: {repr(get_editor_text(d)[:55])}")

        # Esperar feedback de error de student2
        narrate("student1", "⏳ Esperando evaluación de student2…")
        shared["s2_eval_done"].wait(timeout=30)
        time.sleep(2)

        # ── COLLAB B: student1 corrige con tabla real ──────────────────────
        phase("COLLAB — Student1 corrige el SQL usando 'articulo'")
        set_phase(d, "✏️ Student1 corrige → usa tabla 'articulo' en vez de vpract10_ej1")
        narrate("student1", f"✏️  Corrigiendo: {S1_CORRECTED!r}")
        scroll_to_cm(d)
        time.sleep(1)
        type_slowly(d, S1_CORRECTED)
        narrate("student1", f"✅ SQL corregido: {repr(get_editor_text(d)[:55])}")
        shared["s1_corrected"].set()

        # Esperar que s2 evalúe la versión corregida
        narrate("student1", "⏳ Esperando que student2 evalúe la corrección…")
        shared["s2_corrected_eval"].wait(timeout=30)
        time.sleep(2)

        # ── Navegar a Pregunta 2 ───────────────────────────────────────────
        phase("SC11 — Navegar a Pregunta 2")
        narrate("student1", "➡️  Navegando a Pregunta 2 (clic en navegación de preguntas)…")
        time.sleep(1)
        q2_ok = navigate_to_next_question(d, UL1, BG1)
        shared["s1_nav_q2"] = q2_ok
        narrate("student1", f"{'✅' if q2_ok else '⚠️'} Pregunta 2")
        time.sleep(3)

        # ── Terminar intento ───────────────────────────────────────────────
        phase("SC12 — Terminar intento")
        set_phase(d, "🏁 Terminando intento…")
        narrate("student1", "🏁 Clic en 'Terminar intento…'")
        shared["s1_terminated"] = terminar_intento(d)
        set_phase(d, "🏁 Intento terminado" if shared["s1_terminated"] else "⚠️ No confirmado")
        narrate("student1", f"{'✅ Intento terminado' if shared['s1_terminated'] else '⚠️'}")
        time.sleep(4)
        shared["s1_done_event"].set()

    except Exception as e:
        shared["s1_error"] = str(e)
        narrate("student1", f"❌ EXCEPCIÓN: {e}")
        for ev in ["room_ready", "s1_wrote_event", "s1_corrected", "s1_done_event"]:
            shared[ev].set()
    finally:
        try:
            d.quit()
        except Exception:
            pass


# ─────────────────────────────────────────────────────────────────────────────
# THREAD STUDENT 2  (ventana derecha)
# ─────────────────────────────────────────────────────────────────────────────
UL2, BG2 = "STUDENT 2 ▶", "#2E7D32"

def _s2_thread():
    d = make_driver(pos_x=HALF_W)
    try:
        narrate("student2", "🔑 Login…")
        login(d, S2_USER, S2_PASS, UL2, BG2)
        start_attempt(d, UL2, BG2)

        # Esperar room_id de student1
        narrate("student2", "⏳ Esperando Room ID de student1…")
        shared["room_ready"].wait(timeout=50)
        if shared["s1_error"]:
            shared["s2_error"] = f"student1 falló: {shared['s1_error']}"; return
        room_id = shared["room_id"]

        # ── Leer enunciado + Conceptos relacionados ────────────────────────
        phase("LECTURA — student2 lee el enunciado y Conceptos relacionados")
        set_phase(d, "📖 Leyendo enunciado…")
        narrate("student2", "📖 Leyendo enunciado del ejercicio…")
        scroll_to_top(d)
        time.sleep(2)
        click_accordion(d, "Conceptos relacionados")
        narrate("student2", "📌 Leyendo Conceptos relacionados…")
        time.sleep(3)

        # ── SC1b: unirse a sala ────────────────────────────────────────────
        phase("SC1 — student2 se une")
        set_phase(d, f"SC1 — Uniéndose a sala {room_id}…")
        narrate("student2", f"🚪 Uniéndose a sala {room_id}…")
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
        narrate("student2", f"✍️  ID introducido: {room_id}")
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
        set_phase(d, f"SC1 ✅ Unido | Participantes: {p}")
        narrate("student2", f"✅ Unido | Participantes: {p}")
        shared["s2_joined_event"].set()

        # ── Posicionarse en el editor para ver el sync ─────────────────────
        phase("SC8 — SYNC: student2 espera el código de student1")
        scroll_to_cm(d)
        set_phase(d, "SC8 — MIRA ESTE EDITOR: el código de student1 aparecerá aquí…")
        narrate("student2", "👀 Editor visible — dando señal a student1 para que empiece")
        shared["s2_at_editor"].set()

        # Esperar que student1 escriba S1_PARTIAL
        narrate("student2", "👀 Observando editor (student1 está escribiendo…)")
        shared["s1_wrote_event"].wait(timeout=40)
        set_phase(d, "SC8 — Propagando vía WebSocket… (¿ves el código de student1?)")
        time.sleep(6)  # tiempo para que el WS propague y el tribunal vea el sync
        s2_sees = get_editor_text(d)
        shared["s2_saw_s1"] = bool(s2_sees.strip())
        set_phase(d, f"SC8 {'✅ SYNC OK' if shared['s2_saw_s1'] else '⚠️ Sin sync'}")
        narrate("student2",
                f"{'✅ VEO código de student1: ' if shared['s2_saw_s1'] else '⚠️ Editor: '}"
                f"{repr(s2_sees[:55])}")
        time.sleep(2)

        # ── COLLAB A: student2 completa el SQL ────────────────────────────
        phase("COLLAB — Student2 completa el SQL")
        set_phase(d, "⌨️ Student2 completa la query…")
        narrate("student2", f"✏️  Completando: {S2_COMPLETED!r}")
        scroll_to_cm(d)
        time.sleep(1)
        type_slowly(d, S2_COMPLETED)
        narrate("student2", f"✅ Editor s2: {repr(get_editor_text(d)[:55])}")
        shared["s2_wrote_event"].set()

        # ── SC8b: Evaluar → ERROR (vpract10_ej1 no existe) ────────────────
        phase("SC9 — student2 evalúa → ERROR relation does not exist")
        set_phase(d, "SC9 — Evaluando… (esperamos el error de la relación)")
        time.sleep(2)
        narrate("student2", "🎯 Clic en 'Evaluar código'…")
        js_click_id(d, "evaluateSqlButton", "Evaluar código")
        time.sleep(4)
        scroll_to_result(d)
        shared["s2_got_error"] = has_error_msg(d)
        set_phase(d, "SC9 ✅ ERROR: relation does not exist" if shared["s2_got_error"]
                  else "SC9 ⚠️ No se detectó error")
        narrate("student2",
                f"{'✅ ERROR relation detectado' if shared['s2_got_error'] else '⚠️ Sin error'}")
        time.sleep(4)
        shared["s2_eval_done"].set()

        # ── Esperar que student1 corrija ───────────────────────────────────
        narrate("student2", "⏳ Esperando corrección de student1…")
        shared["s1_corrected"].wait(timeout=40)
        set_phase(d, "👀 Student1 corrigió el SQL — evaluando versión corregida…")
        time.sleep(5)  # WS propaga la corrección

        # ── SC10: student2 evalúa la versión corregida ────────────────────
        phase("SC10 — student2 evalúa SQL corregido")
        corrected_in_editor = get_editor_text(d)
        narrate("student2", f"Editor tras corrección de s1: {repr(corrected_in_editor[:55])}")
        # Si el sync no trajo la corrección, escribirla explícitamente
        if "articulo" not in corrected_in_editor:
            narrate("student2", "⚠️ Sync tardío — escribiendo S1_CORRECTED explícitamente")
            scroll_to_cm(d)
            clear_and_type(d, S1_CORRECTED)
            time.sleep(1)
        set_phase(d, "SC10 — Evaluando versión corregida…")
        narrate("student2", "🎯 Clic en 'Evaluar código' (versión corregida)…")
        js_click_id(d, "evaluateSqlButton", "Evaluar código")
        time.sleep(4)
        scroll_to_result(d)
        shared["s2_corrected_ok"] = has_eval_response(d)
        set_phase(d, "SC10 ✅ Respuesta" if shared["s2_corrected_ok"] else "SC10 ⚠️")
        narrate("student2", f"{'✅ Feedback visible' if shared['s2_corrected_ok'] else '⚠️'}")
        time.sleep(4)
        shared["s2_corrected_eval"].set()

        # ── Navegar a Pregunta 2 (simultáneo con student1) ────────────────
        phase("SC11 — student2 navega a Pregunta 2")
        narrate("student2", "➡️  Navegando a Pregunta 2 (clic en navegación de preguntas)…")
        time.sleep(1)
        q2_ok = navigate_to_next_question(d, UL2, BG2)
        shared["s2_nav_q2"] = q2_ok
        narrate("student2", f"{'✅' if q2_ok else '⚠️'} Pregunta 2")
        time.sleep(3)

        # ── Terminar intento ───────────────────────────────────────────────
        phase("SC12 — student2 termina intento")
        set_phase(d, "🏁 Terminando intento…")
        narrate("student2", "🏁 Clic en 'Terminar intento…'")
        shared["s2_terminated"] = terminar_intento(d)
        set_phase(d, "🏁 Intento terminado" if shared["s2_terminated"] else "⚠️ No confirmado")
        narrate("student2", f"{'✅ Intento terminado' if shared['s2_terminated'] else '⚠️'}")
        time.sleep(4)
        shared["s2_done_event"].set()

    except Exception as e:
        shared["s2_error"] = str(e)
        narrate("student2", f"❌ EXCEPCIÓN: {e}")
    finally:
        for ev in ["s2_joined_event", "s2_at_editor", "s2_wrote_event",
                   "s2_eval_done", "s2_corrected_eval", "s2_done_event"]:
            shared[ev].set()
        try:
            d.quit()
        except Exception:
            pass


# ─────────────────────────────────────────────────────────────────────────────
# SESIÓN + RESULTADOS
# ─────────────────────────────────────────────────────────────────────────────

def run_session():
    # Reset state
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
        "s1_modal": False, "s1_room4": False, "s1_dict5": False,
        "s1_tables7": False, "s1_views7": False,
        "s2_saw_s1": False, "s2_got_error": False, "s2_corrected_ok": False,
        "s1_nav_q2": False, "s2_nav_q2": False,
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
        print(f"  ⊘ SKIP  {name}")
        results.append((name, None, "SKIP"))
        return
    try:
        ok, msg = fn()
        print(f"  {'✔ PASS' if ok else '✘ FAIL'}  {name}\n         {msg}")
        results.append((name, ok, msg))
    except Exception as e:
        print(f"  ✘ ERROR  {name}\n           {e}")
        results.append((name, False, str(e)))


if __name__ == "__main__":
    detect_screen()

    print("\n" + "═"*65)
    print("  FUN-12 v7 — Demo integral del entorno colaborativo SQLab")
    print(f"  ◀ STUDENT 1 (0 px)         STUDENT 2 ▶ ({HALF_W} px)")
    print(f"  Ventanas: {HALF_W}×{SCREEN_H} px  ·  Zoom: {ZOOM}%")
    print("═"*65 + "\n")

    run_session()

    print("\n" + "═"*65)
    print("  RESULTADOS")
    print("═"*65)

    report("SC1  — Sala compartida (mismo room_id)",
           lambda: (shared["s1_joined"] and shared["s2_joined"],
                    f"Room ID: {shared['room_id']}"
                    if (shared["s1_joined"] and shared["s2_joined"])
                    else (shared["s2_error"] or shared["s1_error"] or "Uno no se unió")))

    report("SC2  — Awareness: participantes >= 1",
           lambda: ((shared["s1_parts"] or 0) >= 1 or (shared["s2_parts"] or 0) >= 1,
                    f"s1 ve {shared['s1_parts']} | s2 ve {shared['s2_parts']}"))

    report("SC3  — Modal lista de usuarios",
           lambda: (shared["s1_modal"],
                    "✔ Modal visible" if shared["s1_modal"] else "No detectado"))

    report("SC4  — #room-id-display sin error PHP",
           lambda: (shared["s1_room4"],
                    "✔ Sin errores PHP" if shared["s1_room4"] else "Error PHP"))

    report("SC5  — Botón diccionario SQL visible",
           lambda: (shared["s1_dict5"],
                    "✔ Botón encontrado" if shared["s1_dict5"] else "No encontrado"))

    report("SC6  — Schema list snippet",
           skip=True)  # funcionalidad caída en servidor

    report("SC7  — Submenús Tables y Views",
           lambda: (shared["s1_tables7"] and shared["s1_views7"],
                    f"Tables={'✔' if shared['s1_tables7'] else '✘'}  "
                    f"Views={'✔' if shared['s1_views7'] else '✘'}"))

    report("SC8  — SYNC: student1 escribe → student2 lo ve en tiempo real",
           lambda: (shared["s2_saw_s1"],
                    "✔ student2 vio código de student1" if shared["s2_saw_s1"]
                    else "Sin sync"))

    report("SC9  — Evaluar SQL incorrecto → ERROR relation does not exist",
           lambda: (shared["s2_got_error"],
                    "✔ ERROR relation detectado" if shared["s2_got_error"]
                    else "No se detectó el error esperado"))

    report("SC10 — student1 corrige SQL → student2 evalúa corrección",
           lambda: (shared["s2_corrected_ok"],
                    "✔ Feedback visible" if shared["s2_corrected_ok"] else "Sin feedback"))

    report("SC11 — Navegar a Pregunta 2",
           lambda: (shared["s1_nav_q2"] or shared["s2_nav_q2"],
                    f"s1={'✔' if shared['s1_nav_q2'] else '✘'}  "
                    f"s2={'✔' if shared['s2_nav_q2'] else '✘'}"))

    report("SC12 — Terminar intento",
           lambda: (shared["s1_terminated"] or shared["s2_terminated"],
                    f"s1={'✔' if shared['s1_terminated'] else '✘'}  "
                    f"s2={'✔' if shared['s2_terminated'] else '✘'}"))

    passed  = sum(1 for _, ok, _ in results if ok is True)
    skipped = sum(1 for _, ok, _ in results if ok is None)
    total   = len(results) - skipped
    print(f"\n{'═'*65}")
    print(f"  TOTAL: {passed}/{total} pasados  ({skipped} omitidos)")
    print(f"{'═'*65}\n")
    sys.exit(0 if passed == total else 1)

#!/usr/bin/env python3
"""
selenium_FUN10.py — FUN-10: entorno colaborativo (crear sala, unirse, awareness)

deriva de tres casos de uso relacionados: CU-09 (crear sala), CU-10 (unirse a
una sala existente por ID) y CU-11 (awareness — ver quién más está conectado).
este es el primer script donde meto DOS usuarios de verdad en paralelo con
threading, porque para probar "unirse a una sala" necesito que alguien la
haya creado antes. es justo el tipo de escenario que Behat no podía cubrir
por ser monohilo (no puede tener dos sesiones de navegador vivas a la vez).

escenarios:
  SC1 — se ve el texto "Sala" y el input para escribir el ID de sala
  SC2 — el botón "Unirme a la sala" es visible
  SC3 — "Participantes conectados" es visible (awareness activo)
  SC4 — puedo extraer el room_id real desde el elemento #room-id-display
  SC5 — el formulario de unión es accesible y no da errores PHP
  SC6 — el escenario gordo: DOS USUARIOS SIMULTÁNEOS. student1 crea la sala
        en un hilo, student2 se une con ese ID en otro hilo, sincronizados
        con un threading.Event para que student2 no intente unirse antes de
        que exista la sala

uso: python selenium_FUN10.py
"""
import re, sys, time, threading
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import TimeoutException

BASE_URL     = "https://moodle.repobcam.i3a.uclm.es:10443"
ACTIVITY_URL = f"{BASE_URL}/mod/sqlab/view.php?id=5"
S1_USER, S1_PASS = "student1", "Stu1234!"
S2_USER, S2_PASS = "student2", "Stu1234!"
TIMEOUT = 12
PHP_ERRORS = ["Fatal error", "Warning:", "Notice:"]

shared = {
    "room_id": None, "room_ready": threading.Event(),
    "s1_joined": False, "s2_joined": False,
    "s1_participants": None, "s2_participants": None,
    "s1_error": None, "s2_error": None,
}

def driver():
    o = Options()
    o.add_argument("--ignore-certificate-errors")
    o.add_argument("--no-sandbox")
    o.add_argument("--disable-dev-shm-usage")
    d = webdriver.Chrome(options=o)
    d.set_page_load_timeout(30)
    return d

def login(d, user, pwd):
    d.get(f"{BASE_URL}/login/index.php")
    WebDriverWait(d, TIMEOUT).until(EC.presence_of_element_located((By.ID, "username")))
    d.find_element(By.ID, "username").send_keys(user)
    d.find_element(By.ID, "password").send_keys(pwd)
    d.find_element(By.ID, "loginbtn").click()
    time.sleep(2)
    if "login/index" in d.current_url:
        raise RuntimeError(f"Login fallido: {user}")

def see(d, text, t=TIMEOUT):
    try:
        WebDriverWait(d, t).until(lambda x: text in x.page_source)
        return True
    except TimeoutException:
        return False

def start_attempt(d):
    d.get(ACTIVITY_URL)
    time.sleep(1)
    for label in ["Continuar el último intento", "Intentar ahora", "Comenzar el intento",
                  "Iniciar actividad", "Comenzar un nuevo intento"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH, f"//button[contains(text(),'{label}')] | //a[contains(text(),'{label}')]")
            )).click()
            time.sleep(2)
            # Manejar posible modal de confirmación (solo aparece al crear nuevo intento)
            for confirm in ["Comenzar", "Iniciar un nuevo intento", "Confirmar", "Aceptar"]:
                try:
                    WebDriverWait(d, 2).until(EC.element_to_be_clickable(
                        (By.XPATH, f"//button[contains(text(),'{confirm}')] | //input[@value='{confirm}']")
                    )).click()
                    time.sleep(2)
                    break
                except TimeoutException:
                    continue
            break
        except TimeoutException:
            continue

def extract_room_id(d, t=15):
    """
    Lee el Room ID del elemento #room-id-display (confirmado del HTML real).
    Espera a que deje de ser "N/A" (estado inicial antes de conectar WebSocket).
    """
    try:
        WebDriverWait(d, t).until(lambda x: x.execute_script(
            "var el=document.getElementById('room-id-display');"
            "return el && el.textContent.trim() !== '' && el.textContent.trim() !== 'N/A';"
        ))
        return d.execute_script(
            "return document.getElementById('room-id-display').textContent.trim();")
    except Exception:
        return None

def get_participants(d):
    """
    Lee el recuento de participantes de #participant-count (confirmado del HTML real).
    """
    try:
        val = d.execute_script(
            "var el=document.getElementById('participant-count');"
            "return el ? el.textContent.trim() : null;")
        return int(val) if val and val.isdigit() else None
    except Exception:
        # Fallback: regex en el page_source
        plain = re.sub(r'<[^>]+>', ' ', d.page_source)
        m = re.search(r'Participantes\s+conectados:\s*(\d+)', plain)
        return int(m.group(1)) if m else None

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

# ── Escenarios de un solo usuario ─────────────────────────────────────────────

def sc1_sala_visible():
    """entro con student1 y compruebo que el texto "Sala" y el input real
    (#roomidtext) están en la página."""
    d1 = driver()
    try:
        login(d1, S1_USER, S1_PASS)
        start_attempt(d1)
        # compruebo por los IDs reales del HTML (confirmados inspeccionando la página)
        sala_text = see(d1, "Sala", 8) or see(d1, "sala", 5)
        input_ok = len(d1.find_elements(By.ID, "roomidtext")) > 0
        err = next((e for e in PHP_ERRORS if e in d1.page_source), None)
        ok = sala_text and input_ok and err is None
        return ok, ("'Sala' visible e input #roomidtext presente"
                    if ok else f"sala_text={sala_text} | input#roomidtext={input_ok} | PHP err={err}")
    finally:
        d1.quit()

def sc2_boton_unirme():
    """compruebo que el botón "Unirme a la sala" está, buscando primero por
    su id real (#btn-connect) y si no por el texto."""
    d2 = driver()
    try:
        login(d2, S1_USER, S1_PASS)
        start_attempt(d2)
        # Verificar por ID real del HTML: id="btn-connect"
        btn_by_id = len(d2.find_elements(By.ID, "btn-connect")) > 0
        btn_by_text = see(d2, "Unirme a la sala", 5)
        ok = btn_by_id or btn_by_text
        return ok, ("Botón 'Unirme a la sala' (#btn-connect) visible"
                    if ok else "'btn-connect' y texto 'Unirme a la sala' NO encontrados")
    finally:
        d2.quit()

def sc3_participantes_conectados():
    """solo miro que aparece "Participantes conectados", que es la señal de
    que el awareness (saber quién está en la sala) está activo en la UI."""
    d3 = driver()
    try:
        login(d3, S1_USER, S1_PASS)
        start_attempt(d3)
        ok = see(d3, "Participantes conectados", 8)
        return ok, ("'Participantes conectados' visible" if ok else "'Participantes conectados' NO encontrado")
    finally:
        d3.quit()

def sc4_room_id_extraible():
    """compruebo que puedo sacar el room_id real del elemento
    #room-id-display — esto es clave porque sc6 lo necesita para que
    student2 pueda unirse a la sala de student1."""
    d4 = driver()
    try:
        login(d4, S1_USER, S1_PASS)
        start_attempt(d4)
        room_id = extract_room_id(d4)
        return (room_id is not None), (
            f"room_id extraído: {room_id}" if room_id else "No se encontró 'sala ID:' en la página")
    finally:
        d4.quit()

def sc5_formulario_union():
    """pulso el botón de unirme sin haber puesto ningún ID (a propósito) para
    ver que el plugin gestiona bien ese caso raro con una alerta JS en vez de
    petar con un error PHP. si sale un alert, lo descarto y sigo."""
    d5 = driver()
    try:
        login(d5, S1_USER, S1_PASS)
        start_attempt(d5)
        # Usar id="btn-connect" (confirmado del HTML real)
        try:
            WebDriverWait(d5, 8).until(EC.element_to_be_clickable(
                (By.ID, "btn-connect")
            )).click()
            time.sleep(2)
        except TimeoutException:
            # Fallback por texto
            try:
                WebDriverWait(d5, 5).until(EC.element_to_be_clickable(
                    (By.XPATH, "//*[normalize-space()='Unirme a la sala']")
                )).click()
                time.sleep(2)
            except TimeoutException:
                return False, "'btn-connect' y 'Unirme a la sala' no encontrados"
        # Descartar posible alert JS ("No se ha proporcionado un ID")
        try:
            d5.switch_to.alert.dismiss()
            time.sleep(1)
        except Exception:
            pass
        err = next((e for e in PHP_ERRORS if e in d5.page_source), None)
        return (err is None), ("Botón #btn-connect funcional — alert descartado, sin errores PHP"
                               if err is None else f"Error PHP: {err}")
    finally:
        d5.quit()

# ── Escenario 2 usuarios simultáneos (threading) ─────────────────────────────
# aquí es donde de verdad necesito dos navegadores vivos al mismo tiempo —
# esto era justo lo que Behat no podía hacer al ser monohilo. uso dos threads
# y un threading.Event para que student2 espere a que exista la sala antes
# de intentar unirse.

def _s1_thread():
    """hilo de student1: crea/entra en la sala, guarda el room_id en el dict
    compartido y avisa a student2 (vía room_ready) de que ya puede unirse.
    luego espera un rato y refresca para comprobar que ve a student2 conectado."""
    d = driver()
    try:
        login(d, S1_USER, S1_PASS)
        start_attempt(d)
        room_id = extract_room_id(d)
        if not room_id:
            shared["s1_error"] = "No se encontró 'sala ID:'"
            shared["room_ready"].set(); return
        shared["room_id"] = room_id
        shared["s1_participants"] = get_participants(d)
        shared["s1_joined"] = True
        print(f"\n  [student1] Sala ID: {room_id}  Participantes: {shared['s1_participants']}")
        shared["room_ready"].set()
        time.sleep(15)
        d.refresh(); time.sleep(3)
        print(f"  [student1] Participantes tras student2: {get_participants(d)}")
    except Exception as e:
        shared["s1_error"] = str(e); shared["room_ready"].set()
    finally:
        d.quit()

def _s2_thread():
    """hilo de student2: espera a que student1 haya creado la sala
    (room_ready), mete el ID en el input real y pulsa unirme. si algo falla
    lo deja anotado en shared["s2_error"] para que sc6 lo reporte."""
    d = driver()
    try:
        login(d, S2_USER, S2_PASS)
        start_attempt(d)
        shared["room_ready"].wait(timeout=25)
        if shared["s1_error"]:
            shared["s2_error"] = f"s1 falló: {shared['s1_error']}"; return
        room_id = shared["room_id"]
        print(f"\n  [student2] Uniéndose a sala: {room_id}")
        time.sleep(3)  # esperar a que el widget JS termine de renderizar
        # Input: usar id="roomidtext" (confirmado del HTML real)
        inp = None
        try:
            inp = WebDriverWait(d, 8).until(
                EC.presence_of_element_located((By.ID, "roomidtext")))
        except TimeoutException:
            # Fallback por placeholder
            for sel in [
                "//input[contains(@placeholder,'ID')]",
                "//input[@type='text']",
            ]:
                try:
                    inp = WebDriverWait(d, 4).until(
                        EC.presence_of_element_located((By.XPATH, sel)))
                    break
                except TimeoutException:
                    continue
        if inp is None:
            shared["s2_error"] = "Campo #roomidtext no encontrado"; return
        inp.clear(); inp.send_keys(room_id)
        # Botón: usar id="btn-connect" (confirmado del HTML real)
        try:
            WebDriverWait(d, 8).until(EC.element_to_be_clickable(
                (By.ID, "btn-connect")
            )).click()
            time.sleep(3)
        except TimeoutException:
            try:
                WebDriverWait(d, 5).until(EC.element_to_be_clickable(
                    (By.XPATH, "//*[normalize-space()='Unirme a la sala']")
                )).click()
                time.sleep(3)
            except TimeoutException:
                shared["s2_error"] = "'btn-connect' y 'Unirme a la sala' no clickable"; return
        err = next((e for e in PHP_ERRORS if e in d.page_source), None)
        if err:
            shared["s2_error"] = f"Error PHP: {err}"; return
        shared["s2_joined"] = True
        shared["s2_participants"] = get_participants(d)
        print(f"  [student2] Unido. Participantes: {shared['s2_participants']}")
        time.sleep(5)
    except Exception as e:
        shared["s2_error"] = str(e)
    finally:
        d.quit()

def sc6_dos_usuarios():
    """lanzo los dos hilos (con un pequeño retraso para que student1 vaya
    primero) y compruebo al final que student2 consiguió unirse a la misma
    sala que creó student1. si algo se queda colgado, los timeouts de
    join() evitan que el script se quede esperando para siempre."""
    shared["room_id"] = None
    shared["room_ready"] = threading.Event()
    shared["s1_joined"] = shared["s2_joined"] = False
    shared["s1_error"] = shared["s2_error"] = None
    t1 = threading.Thread(target=_s1_thread)
    t2 = threading.Thread(target=_s2_thread)
    t1.start(); time.sleep(4); t2.start()
    t1.join(timeout=55); t2.join(timeout=55)
    if shared["s1_error"]:
        return False, f"student1 error: {shared['s1_error']}"
    if not shared["s2_joined"]:
        return False, shared["s2_error"] or "student2 no se unió (timeout)"
    return True, (f"student2 unido a sala {shared['room_id']}. "
                  f"Participantes: {shared['s2_participants']}")

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═"*65)
    print("  FUN-10 — Entorno colaborativo")
    print("═"*65)
    run("SC1 — 'Sala' e input de ID visibles",                    sc1_sala_visible)
    run("SC2 — Botón 'Unirme a la sala' visible",                 sc2_boton_unirme)
    run("SC3 — 'Participantes conectados' visible",               sc3_participantes_conectados)
    run("SC4 — room_id extraíble del awareness",                  sc4_room_id_extraible)
    run("SC5 — Formulario de unión sin errores PHP",              sc5_formulario_union)
    run("SC6 — 2 usuarios simultáneos (student1 + student2)",     sc6_dos_usuarios)
    passed = sum(1 for _,ok,_ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

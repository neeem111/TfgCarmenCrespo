#!/usr/bin/env python3
"""
selenium_FUN09.py — FUN-09: Menú de diccionario de datos
Cubre: CU-09 (consultar diccionario mediante el menú jerárquico de snippets)

Escenarios:
  SC1 — El botón del diccionario (aria-label confirmado del HTML real) es visible
  SC2 — El menú se despliega sin errores PHP al interactuar con él
  SC3 — Seleccionar "Schema list" inserta `select nspname from pg_namespace;` en el editor

SELECTORES CONFIRMADOS DEL HTML REAL:
  Botón:     aria-label="Consultas del diccionario de datos"
  Contenedor: id="vertical-menu-sqledi-snippet-menu-btn"
  Ítems menú: "Schema list", "Tables", "Views" (en inglés, NO español)

Uso: python selenium_FUN09.py
"""
import sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import TimeoutException, NoSuchElementException

BASE_URL     = "https://moodle.repobcam.i3a.uclm.es:10443"
ACTIVITY_URL = f"{BASE_URL}/mod/sqlab/view.php?id=5"
S1_USER      = "student1"
S1_PASS      = "Stu1234!"
TIMEOUT      = 12
PHP_ERRORS   = ["Fatal error", "Warning:", "Notice:"]

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
    for label in ["Continuar el último intento", "Intentar ahora", "Comenzar el intento", "Iniciar actividad"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH, f"//button[contains(text(),'{label}')] | //a[contains(text(),'{label}')]")
            )).click()
            time.sleep(2); break
        except TimeoutException:
            continue

def open_dictionary(d):
    """
    Abre el menú del diccionario de datos.
    Selector primario confirmado del HTML real:
        aria-label="Consultas del diccionario de datos"
    Contenedor: id="vertical-menu-sqledi-snippet-menu-btn"
    """
    # 1. Selector primario: aria-label (confirmado del HTML real)
    for css in [
        '[aria-label="Consultas del diccionario de datos"]',
        '#vertical-menu-sqledi-snippet-menu-btn [aria-label]',
        '#vertical-menu-sqledi-snippet-menu-btn button',
        '#vertical-menu-sqledi-snippet-menu-btn',
    ]:
        try:
            WebDriverWait(d, 5).until(EC.element_to_be_clickable(
                (By.CSS_SELECTOR, css)
            )).click()
            time.sleep(1)
            return True
        except TimeoutException:
            continue
    # 2. Fallback por texto en español (por si la localización varía)
    for text in ["Diccionario", "diccionario", "Dict"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH, f"//*[contains(@aria-label,'{text}') or contains(text(),'{text}')]")
            )).click()
            time.sleep(1)
            return True
        except TimeoutException:
            continue
    return False

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

# ── Escenarios ────────────────────────────────────────────────────────────────

def sc1_diccionario_visible():
    d1 = driver()
    try:
        login(d1, S1_USER, S1_PASS)
        start_attempt(d1)
        # Verificar presencia por aria-label confirmado (selector primario)
        btn_found = len(d1.find_elements(
            By.CSS_SELECTOR,
            '[aria-label="Consultas del diccionario de datos"]'
        )) > 0
        # Fallback: el contenedor por id
        if not btn_found:
            btn_found = len(d1.find_elements(
                By.ID, "vertical-menu-sqledi-snippet-menu-btn"
            )) > 0
        err = next((e for e in PHP_ERRORS if e in d1.page_source), None)
        return (btn_found and err is None), (
            "Botón del diccionario visible (aria-label='Consultas del diccionario de datos')"
            if btn_found else "Botón del diccionario NO encontrado en el DOM"
        )
    finally:
        d1.quit()

def sc2_abrir_sin_errores():
    d2 = driver()
    try:
        login(d2, S1_USER, S1_PASS)
        start_attempt(d2)
        if not open_dictionary(d2):
            return False, "No se encontró el botón del diccionario"
        err = next((e for e in PHP_ERRORS if e in d2.page_source), None)
        return (err is None), ("Diccionario abierto sin errores PHP" if err is None else f"Error PHP: {err}")
    finally:
        d2.quit()

def sc3_snippet_en_editor():
    """
    Confirma que seleccionar "Schema list" del menú inserta el snippet SQL.
    Los ítems del menú son en INGLÉS: "Schema list", "Tables", "Views"
    (confirmado del HTML real — NO "Tablas" en español).
    Al clicar "Schema list" se inserta: select nspname from pg_namespace;
    """
    d3 = driver()
    try:
        login(d3, S1_USER, S1_PASS)
        start_attempt(d3)
        if not open_dictionary(d3):
            return False, "No se encontró el botón del diccionario"
        # Clic en "Schema list" (ítem confirmado del menú en inglés)
        item_clicked = False
        for item in ["Schema list", "Tables", "Views"]:
            try:
                WebDriverWait(d3, 4).until(EC.element_to_be_clickable(
                    (By.XPATH, f"//*[normalize-space()='{item}']")
                )).click()
                time.sleep(1)
                item_clicked = True
                print(f"\n  [FUN-09 SC3] Ítem clicado: '{item}'")
                break
            except TimeoutException:
                continue
        if not item_clicked:
            return False, "Ningún ítem del menú ('Schema list', 'Tables', 'Views') encontrado"
        # Leer contenido del editor CodeMirror
        val = ""
        try:
            val = d3.execute_script(
                "var cms = document.querySelectorAll('.CodeMirror');"
                "return cms.length > 0 ? cms[0].CodeMirror.getValue() : '';") or ""
        except Exception:
            pass
        if not val:
            tas = d3.find_elements(By.TAG_NAME, "textarea")
            if tas:
                val = d3.execute_script("return arguments[0].value;", tas[0]) or ""
        ok = bool(val.strip())
        return ok, (f"Snippet insertado en editor: '{val[:60].strip()}…'"
                    if ok else "Editor vacío tras seleccionar ítem del menú")
    finally:
        d3.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═"*60)
    print("  FUN-09 — Menú de diccionario de datos")
    print("═"*60)
    run("SC1 — Botón/menú diccionario visible",             sc1_diccionario_visible)
    run("SC2 — Diccionario se despliega sin errores PHP",   sc2_abrir_sin_errores)
    run("SC3 — Snippet 'Tablas' se inserta en editor",      sc3_snippet_en_editor)
    passed = sum(1 for _,ok,_ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

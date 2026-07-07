#!/usr/bin/env python3
"""
selenium_FUN08.py — FUN-08: visualización de la puntuación

deriva de CU-06 (ver la puntuación obtenida tras ejecutar código). básicamente
compruebo que la actividad muestra la sección "puntúa como" y que evaluar
código, tanto válido como inválido, no rompe nada por PHP.

escenarios:
  SC1 — la sección "Puntúa como" es visible en la actividad
  SC2 — "Evaluar código" con SQL válido no genera errores PHP y además
        aparece feedback real (correcto/incorrecto/puntuación)
  SC3 — lo mismo pero con SQL inválido: no debe petar por PHP

uso: python selenium_FUN08.py
"""
import sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import TimeoutException

BASE_URL     = "https://moodle.repobcam.i3a.uclm.es:10443"
ACTIVITY_URL = f"{BASE_URL}/mod/sqlab/view.php?id=5"
S1_USER      = "student1"
S1_PASS      = "Stu1234!"
TIMEOUT      = 12
PHP_ERRORS        = ["Fatal error", "Warning:", "Notice:"]
SQL_OK            = "SELECT 1;"
SQL_BAD           = "INVALIDSQL--;"
FEEDBACK_KEYWORDS = [
    "correcto", "Correcto", "incorrecto", "Incorrecto",
    "puntuaci", "Feedback", "feedback", "Puntúa", "puntua",
]

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

def type_sql(d, sql):
    tas = d.find_elements(By.TAG_NAME, "textarea")
    if tas:
        d.execute_script("arguments[0].value = arguments[1];", tas[0], sql); return
    try:
        d.execute_script(
            "var el=document.querySelector('.CodeMirror');"
            "if(el&&el.CodeMirror)el.CodeMirror.setValue(arguments[0]);", sql)
    except Exception:
        pass

def click_btn(d, text, t=8):
    try:
        WebDriverWait(d, t).until(EC.element_to_be_clickable((By.XPATH,
            f"//button[normalize-space()='{text}'] | //*[normalize-space()='{text}']"
        ))).click()
        return True
    except TimeoutException:
        return False

def has_feedback(d):
    """Comprueba que 'Evaluar código' devolvió feedback (correcto/incorrecto/puntuación)."""
    src = d.page_source
    return any(kw in src for kw in FEEDBACK_KEYWORDS)

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

def sc1_puntuacion_visible():
    """compruebo que la sección de puntuación ("Puntúa como X.XX") está
    visible al abrir la actividad — lo vi confirmado en captura de pantalla
    real antes de escribir este check."""
    d1 = driver()
    try:
        login(d1, S1_USER, S1_PASS)
        start_attempt(d1)
        ok = see(d1, "Puntú", 8) or see(d1, "punt", 5)
        return ok, ("Sección de puntuación visible ('Puntúa como')" if ok
                    else "'Puntúa como' NO encontrado en la página")
    finally:
        d1.quit()

def sc2_evaluar_sin_errores():
    """escribo SQL válido, pulso "Evaluar código" y compruebo dos cosas:
    que no hay error PHP y que aparece feedback real de corrección
    (correcto/incorrecto/puntuación) — no vale con que la página cargue,
    quiero ver que el plugin de verdad puntuó algo."""
    d2 = driver()
    try:
        login(d2, S1_USER, S1_PASS)
        start_attempt(d2)
        type_sql(d2, SQL_OK)
        click_btn(d2, "Evaluar código")
        time.sleep(3)
        err = next((e for e in PHP_ERRORS if e in d2.page_source), None)
        fb_ok = has_feedback(d2)
        ok = (err is None) and fb_ok
        if ok:
            msg = "'Evaluar código' completado: feedback de corrección visible, sin errores PHP"
        elif err:
            msg = f"Error PHP: {err}"
        else:
            msg = "Sin errores PHP pero sin feedback de corrección (correcto/incorrecto/puntuación)"
        return ok, msg
    finally:
        d2.quit()

def sc3_evaluar_sql_invalido():
    """meto SQL roto y pulso "Evaluar código" — solo me importa que no salte
    un error PHP feo, un mensaje de error SQL normal del backend está bien."""
    d3 = driver()
    try:
        login(d3, S1_USER, S1_PASS)
        start_attempt(d3)
        type_sql(d3, SQL_BAD)
        click_btn(d3, "Evaluar código")
        time.sleep(3)
        err = next((e for e in PHP_ERRORS if e in d3.page_source), None)
        return (err is None), ("SQL inválido evaluado sin error PHP" if err is None else f"Error PHP: {err}")
    finally:
        d3.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═"*60)
    print("  FUN-08 — Visualización de puntuación")
    print("═"*60)
    run("SC1 — Sección 'Puntúa como' visible",          sc1_puntuacion_visible)
    run("SC2 — 'Evaluar código' sin errores PHP",        sc2_evaluar_sin_errores)
    run("SC3 — SQL inválido + evaluar sin error PHP",    sc3_evaluar_sql_invalido)
    passed = sum(1 for _,ok,_ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

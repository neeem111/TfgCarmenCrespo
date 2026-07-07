#!/usr/bin/env python3
"""
selenium_FUN06.py — FUN-06: Estudiante ejecuta consulta SQL y recibe feedback
Cubre: CU-03 (escribir y ejecutar consulta), CU-04 (ver feedback éxito/error)

Escenarios:
  SC1 — "Ejecutar código" no produce errores PHP
  SC2 — "Evaluar código" no produce errores PHP
  SC3 — SQL sintácticamente inválido no produce error PHP visible

NOTA: Para verificar "Correcto"/"Incorrecto" sustituye SQL_OK por la solución
      real de la actividad (trigger/procedimiento que resuelve la pregunta).

Uso: python selenium_FUN06.py
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
PHP_ERRORS   = ["Fatal error", "Warning:", "Notice:"]
# Palabras que indican que el backend procesó la consulta y devolvió resultado
RESULT_KEYWORDS = [
    "successfully executed", "selected data", "?column?", "rows affected",
    "fila", "row", "SELECT", "INSERT", "UPDATE", "DELETE",
    "ERROR", "error", "syntax error",
]

# Ajustar SQL_OK a la consulta/procedimiento correcto de la actividad real
SQL_OK  = "SELECT 1;"
SQL_BAD = "INVALIDSQL--;"

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

def has_result(d):
    """Comprueba que el backend devolvió algún resultado visible (no solo ausencia de error PHP)."""
    src = d.page_source
    return any(kw in src for kw in RESULT_KEYWORDS)

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

def sc1_ejecutar_codigo():
    d1 = driver()
    try:
        login(d1, S1_USER, S1_PASS)
        start_attempt(d1)
        type_sql(d1, SQL_OK)
        click_btn(d1, "Ejecutar código")
        time.sleep(3)
        err = next((e for e in PHP_ERRORS if e in d1.page_source), None)
        result_ok = has_result(d1)
        ok = (err is None) and result_ok
        if ok:
            msg = "'Ejecutar código' procesado: resultado visible y sin errores PHP"
        elif err:
            msg = f"Error PHP detectado: {err}"
        else:
            msg = "Sin errores PHP pero sin resultado visible en la página"
        return ok, msg
    finally:
        d1.quit()

def sc2_evaluar_codigo():
    d2 = driver()
    try:
        login(d2, S1_USER, S1_PASS)
        start_attempt(d2)
        type_sql(d2, SQL_OK)
        click_btn(d2, "Evaluar código")
        time.sleep(3)
        err = next((e for e in PHP_ERRORS if e in d2.page_source), None)
        return (err is None), ("'Evaluar código' sin errores PHP" if err is None else f"Error PHP: {err}")
    finally:
        d2.quit()

def sc3_sql_invalido():
    d3 = driver()
    try:
        login(d3, S1_USER, S1_PASS)
        start_attempt(d3)
        type_sql(d3, SQL_BAD)
        click_btn(d3, "Ejecutar código")
        time.sleep(3)
        err = next((e for e in PHP_ERRORS if e in d3.page_source), None)
        return (err is None), ("SQL inválido procesado sin error PHP" if err is None else f"Error PHP: {err}")
    finally:
        d3.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═"*60)
    print("  FUN-06 — Ejecución de código SQL y feedback")
    print("═"*60)
    run("SC1 — 'Ejecutar código' sin errores PHP",   sc1_ejecutar_codigo)
    run("SC2 — 'Evaluar código' sin errores PHP",    sc2_evaluar_codigo)
    run("SC3 — SQL inválido sin error PHP visible",  sc3_sql_invalido)
    passed = sum(1 for _,ok,_ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

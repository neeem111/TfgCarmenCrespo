#!/usr/bin/env python3
"""
selenium_FUN04.py — FUN-04: Enunciado y resultados esperados
Cubre: CU-02 (ver enunciado, resultados esperados, editor SQL)

Escenarios:
  SC1 — El enunciado (Pregunta 1) es visible al abrir la actividad
  SC2 — La sección "Resultados esperados" es visible
  SC3 — El editor SQL está presente en la página

Uso: python selenium_FUN04.py
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

def sc1_enunciado():
    d1 = driver()
    try:
        login(d1, S1_USER, S1_PASS)
        start_attempt(d1)
        ok = see(d1, "Pregunta 1", 8)
        return ok, ("'Pregunta 1' visible (enunciado cargado)" if ok else "'Pregunta 1' NO visible")
    finally:
        d1.quit()

def sc2_resultados_esperados():
    d2 = driver()
    try:
        login(d2, S1_USER, S1_PASS)
        start_attempt(d2)
        ok = see(d2, "Resultados esperados", 8)
        return ok, ("'Resultados esperados' visible" if ok else "'Resultados esperados' NO encontrado")
    finally:
        d2.quit()

def sc3_editor_sql():
    d3 = driver()
    try:
        login(d3, S1_USER, S1_PASS)
        start_attempt(d3)
        has_ta  = len(d3.find_elements(By.TAG_NAME, "textarea")) > 0
        has_cm  = len(d3.find_elements(By.CLASS_NAME, "CodeMirror")) > 0
        has_btn = see(d3, "Ejecutar código", 5)
        found   = has_ta or has_cm or has_btn
        tipos   = [t for t, v in [("textarea",has_ta),("CodeMirror",has_cm),("botón",has_btn)] if v]
        return found, (f"Editor presente ({', '.join(tipos)})" if found else "Editor SQL NO encontrado")
    finally:
        d3.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═"*60)
    print("  FUN-04 — Enunciado y resultados esperados")
    print("═"*60)
    run("SC1 — Pregunta 1 (enunciado) visible",       sc1_enunciado)
    run("SC2 — 'Resultados esperados' visible",        sc2_resultados_esperados)
    run("SC3 — Editor SQL presente",                   sc3_editor_sql)
    passed = sum(1 for _,ok,_ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

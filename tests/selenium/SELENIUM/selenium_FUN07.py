#!/usr/bin/env python3
"""
selenium_FUN07.py — FUN-07: Estudiante navega entre preguntas
Cubre: CU-05 (navegar entre preguntas de la actividad)

Escenarios:
  SC1 — El sidebar muestra "Pregunta 1" y "Pregunta 2"
  SC2 — Estudiante avanza a Pregunta 2 sin generar errores PHP
  SC3 — Estudiante puede volver a Pregunta 1 sin errores PHP

Uso: python selenium_FUN07.py
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

def go_to_page(d, page_num):
    """Navega a una pregunta por link de texto o por parámetro href."""
    text = f"Pregunta {page_num}"
    try:
        WebDriverWait(d, 6).until(EC.element_to_be_clickable(
            (By.XPATH, f"//*[normalize-space()='{text}'] | //a[contains(@href,'page={page_num-1}')]")
        )).click()
        time.sleep(2)
        return True
    except TimeoutException:
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

def sc1_navegacion_visible():
    d1 = driver()
    try:
        login(d1, S1_USER, S1_PASS)
        start_attempt(d1)
        ok1 = see(d1, "Pregunta 1", 8)
        ok2 = see(d1, "Pregunta 2", 6)
        err = next((e for e in PHP_ERRORS if e in d1.page_source), None)
        return (ok1 and ok2 and err is None), (
            "Sidebar: 'Pregunta 1' y 'Pregunta 2' visibles" if (ok1 and ok2)
            else f"P1:{ok1} P2:{ok2}")
    finally:
        d1.quit()

def sc2_avanzar_pregunta2():
    d2 = driver()
    try:
        login(d2, S1_USER, S1_PASS)
        start_attempt(d2)
        if not go_to_page(d2, 2):
            return False, "No se encontró link a 'Pregunta 2'"
        err = next((e for e in PHP_ERRORS if e in d2.page_source), None)
        return (err is None), ("Avance a Pregunta 2 sin errores PHP" if err is None else f"Error PHP: {err}")
    finally:
        d2.quit()

def sc3_volver_pregunta1():
    d3 = driver()
    try:
        login(d3, S1_USER, S1_PASS)
        start_attempt(d3)
        go_to_page(d3, 2)
        if not go_to_page(d3, 1):
            return False, "No se encontró link a 'Pregunta 1' para volver"
        err = next((e for e in PHP_ERRORS if e in d3.page_source), None)
        return (err is None), ("Vuelta a Pregunta 1 sin errores PHP" if err is None else f"Error PHP: {err}")
    finally:
        d3.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═"*60)
    print("  FUN-07 — Navegación entre preguntas")
    print("═"*60)
    run("SC1 — Sidebar con Pregunta 1 y Pregunta 2",    sc1_navegacion_visible)
    run("SC2 — Avanzar a Pregunta 2 sin errores PHP",   sc2_avanzar_pregunta2)
    run("SC3 — Volver a Pregunta 1 sin errores PHP",    sc3_volver_pregunta1)
    passed = sum(1 for _,ok,_ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

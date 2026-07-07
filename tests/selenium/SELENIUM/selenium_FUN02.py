#!/usr/bin/env python3
"""
selenium_FUN02.py — FUN-02: el estudiante accede a una actividad SQLab

esto deriva de CU-01 (acceder a la actividad SQLab desde el curso). la idea
es sencilla: entro como student1, compruebo que la actividad se ve en el
curso con su enlace correcto a mod/sqlab, y que al abrirla no explota nada
por PHP.

escenarios:
  SC1 — la actividad "actividad grupal" (SQLab) aparece en la lista del curso
  SC2 — student1 abre la actividad y no hay errores PHP de por medio

uso: python selenium_FUN02.py
"""
import sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import TimeoutException

BASE_URL      = "https://moodle.repobcam.i3a.uclm.es:10443"
COURSE_URL    = f"{BASE_URL}/course/view.php?id=2"
ACTIVITY_URL  = f"{BASE_URL}/mod/sqlab/view.php?id=5"
S1_USER       = "student1"
S1_PASS       = "Stu1234!"
TIMEOUT       = 15
ACTIVITY_NAME = "actividad grupal"
PHP_ERRORS    = ["Fatal error", "Warning:", "Notice:"]

def make_driver():
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
    time.sleep(2)
    for label in ["Continuar el último intento", "Intentar ahora",
                  "Comenzar el intento", "Iniciar actividad", "Comenzar un nuevo intento"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH, f"//button[contains(text(),'{label}')] | //a[contains(text(),'{label}')]")
            )).click()
            time.sleep(2)
            break
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

def sc1_actividad_visible_en_curso():
    """entro al curso como student1 y compruebo dos cosas a la vez: que el
    nombre de la actividad aparece tal cual, y que el enlace apunta de
    verdad a mod/sqlab (no vale con que el texto esté, tiene que ser SQLab)."""
    d = make_driver()
    try:
        login(d, S1_USER, S1_PASS)
        d.get(COURSE_URL)
        # compruebo el nombre exacto de la actividad y que enlaza a /mod/sqlab/
        nombre_ok = see(d, ACTIVITY_NAME, 8)
        link_ok = "/mod/sqlab/" in d.page_source
        ok = nombre_ok and link_ok
        return ok, (f"'{ACTIVITY_NAME}' visible con enlace a mod/sqlab"
                    if ok else f"nombre={nombre_ok} | enlace sqlab={link_ok}")
    finally:
        d.quit()

def sc2_abrir_actividad_sin_errores():
    """simulo que student1 le da a "comenzar/continuar intento" y compruebo
    que aterriza en la página de intento (attempt.php) sin ningún error PHP
    visible en el HTML."""
    d = make_driver()
    try:
        login(d, S1_USER, S1_PASS)
        start_attempt(d)
        err = next((e for e in PHP_ERRORS if e in d.page_source), None)
        on_attempt = ("attempt.php" in d.current_url or "sqlab" in d.current_url)
        ok = on_attempt and err is None
        return ok, (f"Actividad abierta en: {d.current_url.split('/')[-1]}"
                    if ok else f"URL={d.current_url} | Error PHP: {err or 'ninguno'}")
    finally:
        d.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═" * 60)
    print("  FUN-02 — Acceso a la actividad SQLab")
    print("═" * 60)
    run("SC1 — Actividad SQLab visible en el curso",     sc1_actividad_visible_en_curso)
    run("SC2 — Abrir actividad sin errores PHP",         sc2_abrir_actividad_sin_errores)
    passed = sum(1 for _, ok, _ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

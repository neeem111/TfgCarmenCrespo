#!/usr/bin/env python3
"""
selenium_FUN01.py — FUN-01: Acceso al curso y verificación de roles
Cubre: CU-00 (estudiante accede al curso), CU-07 (profesor activa edición)

Escenarios:
  SC1 — Estudiante matriculado puede acceder al curso y ve la actividad SQLab
  SC2 — Profesor (carmenprof) puede activar el modo edición
  SC3 — Estudiante NO puede activar el modo edición

Uso: python selenium_FUN01.py
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
PROF_USER     = "carmenprof"
PROF_PASS     = "Stu1234!"
S1_USER       = "student1"
S1_PASS       = "Stu1234!"
TIMEOUT       = 15
ACTIVITY_NAME = "actividad grupal"

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

def sc1_estudiante_accede():
    d = make_driver()
    try:
        login(d, S1_USER, S1_PASS)
        d.get(COURSE_URL)
        # Verifica que el estudiante ve el curso y la actividad SQLab
        curso_ok = see(d, "BBDD", 8)
        actividad_ok = see(d, ACTIVITY_NAME, 8)
        php_err = any(e in d.page_source for e in ["Fatal error", "Warning:", "Notice:"])
        ok = curso_ok and actividad_ok and not php_err
        msg = f"Curso visible: {curso_ok} | Actividad '{ACTIVITY_NAME}': {actividad_ok}"
        return ok, msg
    finally:
        d.quit()

def sc2_profesor_activa_edicion():
    d = make_driver()
    try:
        login(d, PROF_USER, PROF_PASS)
        d.get(COURSE_URL)
        time.sleep(2)
        # Activar modo edición
        activated = False
        for label in ["Turn editing on", "Activar edición", "Edit mode"]:
            try:
                WebDriverWait(d, 4).until(EC.element_to_be_clickable(
                    (By.XPATH, f"//*[contains(text(),'{label}')]")
                )).click()
                time.sleep(2)
                activated = True
                break
            except TimeoutException:
                continue
        # También puede ser un toggle switch
        if not activated:
            try:
                WebDriverWait(d, 4).until(EC.element_to_be_clickable(
                    (By.XPATH, "//input[@type='checkbox' and contains(@id,'editmode')]")
                )).click()
                time.sleep(2)
                activated = True
            except TimeoutException:
                pass
        # Verificar que el modo edición está activo
        ok = (see(d, "Añadir", 6) or see(d, "Add an activity", 4)
              or see(d, "editing", 4) or "editing=1" in d.current_url)
        return ok, ("Modo edición activo para carmenprof" if ok
                    else "Modo edición NO activado — verificar rol del profesor")
    finally:
        d.quit()

def sc3_estudiante_no_puede_editar():
    d = make_driver()
    try:
        login(d, S1_USER, S1_PASS)
        d.get(COURSE_URL)
        time.sleep(2)
        src = d.page_source
        no_edit = ("Turn editing on" not in src
                   and "Activar edición" not in src
                   and "editing=1" not in d.current_url)
        return no_edit, ("student1 no tiene opción de edición (correcto)"
                         if no_edit else "student1 SÍ ve botón de edición — FALLO de permisos")
    finally:
        d.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═" * 60)
    print("  FUN-01 — Acceso al curso y verificación de roles")
    print("═" * 60)
    run("SC1 — Estudiante accede al curso y ve actividad SQLab", sc1_estudiante_accede)
    run("SC2 — Profesor activa modo edición",                    sc2_profesor_activa_edicion)
    run("SC3 — Estudiante no tiene acceso a modo edición",       sc3_estudiante_no_puede_editar)
    passed = sum(1 for _, ok, _ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

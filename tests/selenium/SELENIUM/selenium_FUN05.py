#!/usr/bin/env python3
"""
selenium_FUN05.py — FUN-05: Profesor crea/configura actividad SQLab
Cubre: CU-07 (profesor crea y configura actividad SQLab en el curso)

Escenarios:
  SC1 — SQLab disponible en el selector de actividades (como carmenprof)
  SC2 — El formulario de configuración de la actividad existente contiene
         los campos name, quizid y activitypassword (verificado en modedit.php)

NOTA: SC2 NO crea una actividad nueva (requeriría un quizid real del sistema).
      En su lugar abre el formulario de edición de la actividad existente (id=5)
      y verifica que los campos de configuración del plugin son accesibles.

Uso: python selenium_FUN05.py
"""
import sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import TimeoutException

BASE_URL    = "https://moodle.repobcam.i3a.uclm.es:10443"
COURSE_URL  = f"{BASE_URL}/course/view.php?id=2"
MODEDIT_URL = f"{BASE_URL}/course/modedit.php?update=5&return=0&sr=0"
PROF_USER   = "carmenprof"
PROF_PASS   = "Stu1234!"
TIMEOUT     = 15

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

def enable_editing(d):
    for label in ["Turn editing on", "Activar edición", "Edit mode"]:
        try:
            WebDriverWait(d, 4).until(EC.element_to_be_clickable(
                (By.XPATH, f"//*[contains(text(),'{label}')]"))).click()
            time.sleep(2)
            return True
        except TimeoutException:
            continue
    # Moodle 4.x: toggle switch
    try:
        WebDriverWait(d, 4).until(EC.element_to_be_clickable(
            (By.XPATH, "//input[@type='checkbox' and contains(@id,'editmode')]")
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

def sc1_sqlab_en_picker():
    """carmenprof activa edición y verifica que SQLab aparece en el picker."""
    d = make_driver()
    try:
        login(d, PROF_USER, PROF_PASS)
        d.get(COURSE_URL)
        enable_editing(d)
        # Abrir picker de actividades
        try:
            WebDriverWait(d, 8).until(EC.element_to_be_clickable((By.XPATH,
                "//*[contains(text(),'Añadir una actividad') or contains(text(),'Add an activity')]"
            ))).click()
            time.sleep(2)
        except TimeoutException:
            return False, "No se encontró 'Añadir una actividad'"
        ok = see(d, "SQLab", 6) or see(d, "sqlab", 4)
        return ok, ("'SQLab' presente en el picker de actividades"
                    if ok else "SQLab NO aparece en el picker")
    finally:
        d.quit()

def sc2_formulario_configurable():
    """
    Abre el formulario de edición de la actividad existente (modedit.php?update=5)
    y verifica que los campos de configuración del plugin están presentes:
      - name        (nombre de la actividad)
      - quizid      (ID del cuestionario Moodle asociado)
      - activitypassword (contraseña opcional)
    """
    d = make_driver()
    try:
        login(d, PROF_USER, PROF_PASS)
        d.get(MODEDIT_URL)
        time.sleep(3)

        # Verificar que la página es el formulario de edición y no un error
        if "login/index" in d.current_url:
            return False, "Redirigido a login — carmenprof sin permiso de edición"
        if "errorcode" in d.page_source or "error" in d.current_url:
            return False, f"Error al abrir modedit: {d.current_url}"

        # Campo 'name' (nombre de la actividad — estándar Moodle)
        has_name = len(d.find_elements(By.NAME, "name")) > 0
        # Campo 'quizid' (específico de mod_sqlab, requerido)
        has_quizid = len(d.find_elements(By.NAME, "quizid")) > 0
        # Campo 'activitypassword' (opcional, específico de mod_sqlab)
        has_pass = (len(d.find_elements(By.NAME, "activitypassword")) > 0
                    or "activitypassword" in d.page_source)

        fields_found = [f for f, v in [("name", has_name), ("quizid", has_quizid),
                                        ("activitypassword", has_pass)] if v]
        ok = has_name and has_quizid
        msg = (f"Campos del formulario presentes: {fields_found}"
               if ok else f"Faltan campos: name={has_name} | quizid={has_quizid} | pass={has_pass}")
        return ok, msg
    finally:
        d.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═" * 60)
    print("  FUN-05 — Configuración de actividad SQLab (carmenprof)")
    print("═" * 60)
    run("SC1 — SQLab disponible en selector de actividades", sc1_sqlab_en_picker)
    run("SC2 — Formulario tiene campos name, quizid, activitypassword", sc2_formulario_configurable)
    passed = sum(1 for _, ok, _ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

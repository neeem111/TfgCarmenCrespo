#!/usr/bin/env python3
"""
selenium_FUN03.py — FUN-03: Plugin mod_sqlab correctamente instalado
Cubre: instalación correcta, sin errores PHP, plugin habilitado

Escenarios:
  SC1 — Plugin "SQLab" aparece en Manage activities (texto confirmado del HTML real)
  SC2 — Página de admin sin errores PHP
  SC3 — Plugin no aparece como no instalado/faltante

NOTA: Este test requiere credenciales de admin (verificación de infraestructura).
      Confirmado por Behat: "sqlab" en minúsculas NO aparece → el texto es "SQLab".

Uso: python selenium_FUN03.py
"""
import sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import TimeoutException

BASE_URL      = "https://moodle.repobcam.i3a.uclm.es:10443"
ADMIN_MODULES = f"{BASE_URL}/admin/modules.php"
ADMIN_USER    = "admin"
ADMIN_PASS    = "Admin1234!"
TIMEOUT       = 15
PHP_ERRORS    = ["Fatal error", "Warning:", "Notice:", "Strict Standards:"]

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

def sc1_plugin_en_lista():
    d = make_driver()
    try:
        login(d, ADMIN_USER, ADMIN_PASS)
        d.get(ADMIN_MODULES)
        time.sleep(2)
        # CORRECCIÓN: el texto en la UI es "SQLab" (capitalizado), NO "sqlab"
        # Confirmado por el output de Behat: step "I should see 'sqlab'" FAILED
        ok = see(d, "SQLab", 8)
        return ok, ("Plugin 'SQLab' presente en Manage Activities"
                    if ok else "FALLO: 'SQLab' NO encontrado en la lista de módulos")
    finally:
        d.quit()

def sc2_sin_errores_php():
    d = make_driver()
    try:
        login(d, ADMIN_USER, ADMIN_PASS)
        d.get(ADMIN_MODULES)
        time.sleep(2)
        err = next((e for e in PHP_ERRORS if e in d.page_source), None)
        return (err is None), ("Sin errores PHP en página de administración"
                               if err is None else f"Error PHP detectado: '{err}'")
    finally:
        d.quit()

def sc3_plugin_habilitado():
    d = make_driver()
    try:
        login(d, ADMIN_USER, ADMIN_PASS)
        d.get(ADMIN_MODULES)
        time.sleep(2)
        ok = ("Plugin not installed" not in d.page_source
              and "Missing from disk" not in d.page_source
              and "Plugin not found" not in d.page_source)
        return ok, ("Plugin habilitado y sin avisos de instalación incorrecta"
                    if ok else "Plugin aparece como faltante o no instalado")
    finally:
        d.quit()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("═" * 60)
    print("  FUN-03 — Instalación de mod_sqlab (verificación admin)")
    print("═" * 60)
    run("SC1 — Plugin 'SQLab' en lista de módulos de actividad", sc1_plugin_en_lista)
    run("SC2 — Página admin sin errores PHP",                    sc2_sin_errores_php)
    run("SC3 — Plugin habilitado sin avisos de error",           sc3_plugin_habilitado)
    passed = sum(1 for _, ok, _ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    sys.exit(0 if passed == len(results) else 1)

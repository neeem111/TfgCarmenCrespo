#!/usr/bin/env python3
"""
selenium_FUN03.py — FUN-03: plugin mod_sqlab correctamente instalado (panel admin)

esto deriva del requisito de instalación: quería comprobar desde el panel de
administración de moodle que el plugin aparece bien instalado y sin errores.

ojo, importante: este script lo diseñé pero AL FINAL NUNCA LO LLEGUÉ A EJECUTAR.
toda la funcionalidad de administrador quedó fuera del alcance real del
proyecto (no tenía credenciales de admin de verdad en el entorno de la UCLM,
solo unas de prueba que puse aquí como placeholder). la instalación del
plugin la acabé verificando por otra vía: el test PHPUnit UNI-02a. así que
este fichero se queda como diseño/borrador, no como evidencia de ejecución.

escenarios (pensados, no verificados en remoto):
  SC1 — el texto "SQLab" aparece en "Manage activities" (ojo: es "SQLab" con
        mayúscula, "sqlab" en minúsculas NO aparece — esto lo descubrí en su
        día gracias a que Behat fallaba buscando el texto en minúsculas)
  SC2 — la página de admin no tiene errores PHP
  SC3 — el plugin no aparece marcado como "no instalado" o "falta en disco"

uso: python selenium_FUN03.py  (recuerda: necesita credenciales de admin reales,
     que no tuve disponibles — por eso nunca se corrió contra el servidor)
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
    """aquí simularía entrar como admin a "Manage activities" y comprobar que
    "SQLab" aparece en la lista de módulos instalados. NUNCA SE EJECUTÓ contra
    el servidor real por falta de credenciales de admin — se queda en diseño."""
    d = make_driver()
    try:
        login(d, ADMIN_USER, ADMIN_PASS)
        d.get(ADMIN_MODULES)
        time.sleep(2)
        # ojo: el texto en la UI es "SQLab" (con mayúscula), NO "sqlab" en minúsculas
        # esto lo até gracias a que un step de Behat que buscaba "sqlab" fallaba
        ok = see(d, "SQLab", 8)
        return ok, ("Plugin 'SQLab' presente en Manage Activities"
                    if ok else "FALLO: 'SQLab' NO encontrado en la lista de módulos")
    finally:
        d.quit()

def sc2_sin_errores_php():
    """comprobaría que la página de administración de módulos no muestra
    ningún error/warning PHP. como el resto de FUN-03, diseñado pero no
    ejecutado (sin acceso admin real)."""
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
    """comprobaría que el plugin no aparece marcado como "not installed" o
    "missing from disk". igual que sc1/sc2: quedó en diseño, la instalación
    real se validó al final con el PHPUnit UNI-02a."""
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

# ── Main ─────────────────────────────────────────────────────────────────────
# recordatorio: este script quedó como diseño, nunca se ejecutó contra el
# servidor real por falta de credenciales de admin (ver docstring de cabecera).
# la verificación de instalación real se hizo con el PHPUnit UNI-02a.

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

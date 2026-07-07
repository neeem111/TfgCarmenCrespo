#!/usr/bin/env python3
"""
selenium_FUN07.py — FUN-07: Estudiante navega entre preguntas
Cubre: CU-05 (navegar entre preguntas de la actividad)

El plugin ofrece dos vias de navegacion entre preguntas:
  (a) el panel lateral "Navegacion de preguntas" (enlaces "Pregunta 1/2"...)
  (b) los botones de paginacion "Siguiente pagina" / "Pagina anterior".
Este script contrasta ambas: la via (a) funciona; la via (b) rompe la
navegacion mas alla de la segunda pregunta, perdiendo el cmid y redirigiendo
al "Area personal" con el mensaje «No se proporciono un ID de modulo de curso».

Escenarios:
  SC1 — El panel lateral muestra "Pregunta 1" y "Pregunta 2"
  SC2 — Navegacion por el PANEL LATERAL funciona (PASS esperado)
  SC3 — Botones "Siguiente pagina" por TODAS las preguntas (FAIL esperado:
        detecta el punto y el tipo de ruptura)
  SC4 — Boton "Pagina anterior"
En cada paso se comprueba: que no se sale de la actividad, que no aparece el
mensaje de navegacion rota, y que no hay errores PHP (requiere depuracion
DEBUG_DEVELOPER activada en el servidor).

Uso: python selenium_FUN07.py
"""
import sys, time, hashlib
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
MAX_PAGINAS  = 15   # tope de seguridad

PHP_ERRORS = ["Fatal error", "Warning:", "Notice:", "Deprecated:",
              "Debug info", "Coding error detected", "Exception", "Stack trace"]

NAV_BROKEN = ["No se proporciono un ID de modulo de curso",
              "No se proporcionó un ID de módulo de curso",
              "No course module ID was provided"]

BTN_SIGUIENTE = "Siguiente página"
BTN_ANTERIOR  = "Página anterior"

# Selector del enunciado, usado para saber si la pregunta cambio.
ENUNCIADO_XPATH = "//*[contains(@class,'enunciado') or contains(@class,'questiontext')]"


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


def php_error(d):
    return next((e for e in PHP_ERRORS if e in d.page_source), None)


def nav_broken(d):
    return next((m for m in NAV_BROKEN if m in d.page_source), None)


def fuera_de_actividad(d):
    return "/mod/sqlab/" not in d.current_url


def start_attempt(d):
    d.get(ACTIVITY_URL)
    time.sleep(1)
    for label in ["Continuar el ultimo intento", "Continuar el último intento",
                  "Intentar ahora", "Comenzar el intento", "Iniciar actividad"]:
        try:
            WebDriverWait(d, 3).until(EC.element_to_be_clickable(
                (By.XPATH, f"//button[contains(text(),'{label}')] | //a[contains(text(),'{label}')]")
            )).click()
            time.sleep(2)
            break
        except TimeoutException:
            continue


def marcador(d):
    url = d.current_url
    try:
        enun = d.find_element(By.XPATH, ENUNCIADO_XPATH).text.strip()
    except Exception:
        cuerpo = "".join(c for c in d.find_element(By.TAG_NAME, "body").text if not c.isdigit())
        enun = hashlib.md5(cuerpo.encode()).hexdigest()
    return f"{url}||{enun[:300]}"


def click_boton(d, texto):
    """Pulsa un boton/enlace de paginacion por su texto. True si lo encontro."""
    try:
        el = WebDriverWait(d, 6).until(EC.element_to_be_clickable((
            By.XPATH,
            f"//button[contains(normalize-space(),'{texto}')] | "
            f"//a[contains(normalize-space(),'{texto}')] | "
            f"//input[@type='submit' and contains(@value,'{texto}')]"
        )))
        el.click()
        time.sleep(2)
        return True
    except TimeoutException:
        return False


def go_sidebar(d, n):
    """Navega a la pregunta n mediante el panel lateral 'Navegacion de preguntas'."""
    try:
        WebDriverWait(d, 6).until(EC.element_to_be_clickable((
            By.XPATH, f"//a[normalize-space()='Pregunta {n}'] | "
                      f"//*[normalize-space()='Pregunta {n}']"
        ))).click()
        time.sleep(2)
        return True
    except TimeoutException:
        return False


results = []


def run(name, fn):
    print(f"  > {name}...", end=" ", flush=True)
    try:
        ok, msg = fn()
        print(f"{'PASS' if ok else 'FAIL'}  {msg}")
        results.append((name, ok, msg))
    except Exception as e:
        print(f"ERROR  {e}")
        results.append((name, False, str(e)))


# ── Escenarios ────────────────────────────────────────────────────────────────

def sc1_panel_visible():
    d = driver()
    try:
        login(d, S1_USER, S1_PASS)
        start_attempt(d)
        ok1 = see(d, "Pregunta 1", 8)
        ok2 = see(d, "Pregunta 2", 6)
        if php_error(d):
            return False, f"Error PHP en carga inicial: {php_error(d)}"
        ok = ok1 and ok2
        return ok, ("Panel 'Navegacion de preguntas': Pregunta 1 y 2 visibles"
                    if ok else f"P1:{ok1} P2:{ok2}")
    finally:
        d.quit()


def sc2_sidebar_funciona():
    """La via que SI funciona: panel lateral 'Navegacion de preguntas'."""
    d = driver()
    try:
        login(d, S1_USER, S1_PASS)
        start_attempt(d)
        marca_prev = marcador(d)
        if not go_sidebar(d, 2):
            return False, "No se encontro el enlace 'Pregunta 2' en el panel lateral"
        if nav_broken(d) or fuera_de_actividad(d):
            return False, "El panel lateral tambien rompe la navegacion (inesperado)"
        if marcador(d) == marca_prev:
            return False, "El panel lateral no cambio de pregunta"
        return (php_error(d) is None), ("Panel lateral: navegacion a 'Pregunta 2' correcta"
                                        if php_error(d) is None else f"Error PHP: {php_error(d)}")
    finally:
        d.quit()


def sc3_botones_siguiente():
    """La via ROTA: botones 'Siguiente pagina'."""
    d = driver()
    try:
        login(d, S1_USER, S1_PASS)
        start_attempt(d)
        pagina = 1
        marca_prev = marcador(d)
        for _ in range(MAX_PAGINAS):
            if not click_boton(d, BTN_SIGUIENTE):
                return (pagina >= 2), (f"Recorridas {pagina} pregunta(s); "
                                       f"fin de paginacion (no hay mas '{BTN_SIGUIENTE}')")
            msg = nav_broken(d)
            if msg or fuera_de_actividad(d):
                detalle = f"«{msg}»" if msg else f"redirige fuera de la actividad ({d.current_url})"
                return False, (f"'{BTN_SIGUIENTE}' ROMPE la navegacion al pasar de la pregunta "
                               f"{pagina} a la {pagina+1}: {detalle}")
            if php_error(d):
                return False, (f"'{BTN_SIGUIENTE}' genera error PHP de la pregunta "
                               f"{pagina} a la {pagina+1}: {php_error(d)}")
            marca_now = marcador(d)
            if marca_now == marca_prev:
                return False, f"'{BTN_SIGUIENTE}' NO avanza mas alla de la pregunta {pagina}"
            pagina += 1
            marca_prev = marca_now
        return True, f"Avance correcto por {pagina} preguntas"
    finally:
        d.quit()


def sc4_boton_anterior():
    """La via ROTA: boton 'Pagina anterior'."""
    d = driver()
    try:
        login(d, S1_USER, S1_PASS)
        start_attempt(d)
        # Posicionarse en la 2 mediante el panel lateral (via fiable)
        if not go_sidebar(d, 2):
            return False, "No se pudo situar en 'Pregunta 2' para probar el retroceso"
        marca_prev = marcador(d)
        if not click_boton(d, BTN_ANTERIOR):
            return False, f"El boton '{BTN_ANTERIOR}' no esta disponible"
        if nav_broken(d) or fuera_de_actividad(d):
            return False, f"'{BTN_ANTERIOR}' rompe la navegacion: «{nav_broken(d)}»"
        if marcador(d) == marca_prev:
            return False, f"'{BTN_ANTERIOR}' NO retrocede (la pagina no cambia)"
        return (php_error(d) is None), ("Retroceso correcto sin errores PHP"
                                        if php_error(d) is None else f"Error PHP: {php_error(d)}")
    finally:
        d.quit()


# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("=" * 64)
    print("  FUN-07 — Navegacion entre preguntas (panel lateral vs. botones)")
    print("=" * 64)
    run("SC1 — Panel lateral muestra Pregunta 1 y 2",        sc1_panel_visible)
    run("SC2 — Navegacion por panel lateral (debe PASAR)",   sc2_sidebar_funciona)
    run("SC3 — Botones 'Siguiente pagina' (detecta ruptura)", sc3_botones_siguiente)
    run("SC4 — Boton 'Pagina anterior'",                     sc4_boton_anterior)
    passed = sum(1 for _, ok, _ in results if ok)
    print(f"\n  TOTAL: {passed}/{len(results)} pasados")
    print("  Nota: SC2 documenta la via que funciona; SC3/SC4 documentan la rota.")
    sys.exit(0 if passed == len(results) else 1)

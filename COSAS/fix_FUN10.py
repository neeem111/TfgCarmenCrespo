#!/usr/bin/env python3
"""
fix_FUN10.py
Aplica 3 correcciones a selenium_FUN10.py y selenium_FUN10_colaborativo.py:

  1. extract_room_id: regex que salta etiquetas HTML (el ID estaba dentro de <a>)
  2. SC4: comprueba bool(room_id) en lugar de room_id is not None
  3. SC5: captura y descarta el alert JS "No se ha proporcionado un ID de sala"
  4. (SC6 se corrige sólo al arreglar extract_room_id)

Ejecución (desde la carpeta donde están los scripts):
    python fix_FUN10.py
"""
import os

FIXES = [
    # ── Fix 1: regex que extrae el número aunque esté dentro de un <a> ──────
    (
        "r'[Ss]ala\\s+ID:\\s*(\\S+)'",
        "r'[Ss]ala\\s+ID[^0-9]*([0-9]{4,})'"
    ),
    # ── Fix 1b (variante sin raw string en algunos ficheros) ─────────────────
    (
        "r'[Ss]ala\\\\s+ID:\\\\s*(\\\\S+)'",
        "r'[Ss]ala\\\\s+ID[^0-9]*([0-9]{4,})'"
    ),
    # ── Fix 2: SC4 — bool(room_id) en lugar de room_id is not None ───────────
    (
        "return (room_id is not None), (\n            f\"room_id extraído: {room_id}\" if room_id\n            else \"No se encontró 'sala ID:' en la página\")",
        "return bool(room_id), (\n            f\"room_id extraído: {room_id}\" if room_id\n            else \"'sala ID:' no encontrado (el ID viene dentro de un <a>, revisa extract_room_id)\")"
    ),
    # ── Fix 3: SC5 — capturar el alert JS antes de comprobar errores PHP ─────
    (
        "        err = next((e for e in PHP_ERRORS if e in d5.page_source), None)\n        return (err is None), (\"Formulario de unión sin errores PHP\" if err is None else f\"Error PHP: {err}\")",
        "        # Descartar posible alert JS (\"No se ha proporcionado un ID\")\n        try:\n            d5.switch_to.alert.dismiss()\n            time.sleep(1)\n        except Exception:\n            pass\n        err = next((e for e in PHP_ERRORS if e in d5.page_source), None)\n        return (err is None), (\"Formulario de unión: alert JS descartado, sin errores PHP\" if err is None else f\"Error PHP: {err}\")"
    ),
]

targets = ["selenium_FUN10.py", "selenium_FUN10_colaborativo.py"]

for fname in targets:
    if not os.path.exists(fname):
        print(f"  - No encontrado: {fname}")
        continue
    with open(fname, "r", encoding="utf-8") as f:
        content = f.read()
    original = content
    for old, new in FIXES:
        content = content.replace(old, new)
    if content != original:
        with open(fname, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"  ✔ Corregido: {fname}")
    else:
        print(f"  ~ Sin cambios (puede que ya esté corregido): {fname}")

print("\nListo. Prueba de nuevo: python selenium_FUN10.py")

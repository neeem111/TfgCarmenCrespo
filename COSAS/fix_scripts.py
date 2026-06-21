#!/usr/bin/env python3
"""
fix_scripts.py
Añade "Continuar el último intento" a la función start_attempt()
en todos los selenium_FUN*.py de esta carpeta.

Ejecutar desde la carpeta donde están los scripts:
    python fix_scripts.py
"""
import glob, os

OLD = '["Intentar ahora", "Comenzar el intento", "Iniciar actividad"]'
NEW = '["Continuar el último intento", "Intentar ahora", "Comenzar el intento", "Iniciar actividad"]'

archivos = sorted(glob.glob("selenium_FUN*.py"))
if not archivos:
    print("No se encontraron archivos selenium_FUN*.py en esta carpeta.")
    print("Ejecuta este script desde la carpeta donde están los selenium_FUN*.py")
else:
    for f in archivos:
        with open(f, "r", encoding="utf-8") as fp:
            content = fp.read()
        if OLD in content:
            content = content.replace(OLD, NEW)
            with open(f, "w", encoding="utf-8") as fp:
                fp.write(content)
            print(f"  ✔ Corregido: {f}")
        else:
            print(f"  - Ya correcto o no aplica: {f}")
    print("\nListo. Vuelve a ejecutar los scripts.")

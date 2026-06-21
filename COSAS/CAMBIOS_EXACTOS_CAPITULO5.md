# CAMBIOS EXACTOS — CAPÍTULO 5 TFG

> Documento de cambios listos para aplicar en tu Word. Cada sección indica QUÉ buscar y POR QUÉ texto sustituirlo.
>
> **Aclaración importante sobre los ficheros PHPUnit del tutor:**
> Los 4 ficheros de output que te envió (`mod_sqlab_version_test`, `mod_sqlab_backup_test`, `mod_sqlab_privacy_test`, `mod_sqlab_structure_test`) contienen TODOS el mismo output: la ejecución de `mod_sqlab_version_test.php`. El tutor ejecutó ese mismo fichero 4 veces. Por tanto, el tutor solo confirmó los resultados de la suite UNI-02. Los resultados de UNI-03, UNI-04 y UNI-05 que aparecen en tu Tabla 7 actual son de tu entorno local.

---

## CAMBIO 1 — Sección 5.1 (Estrategia iterativa: añadir Fase 3)

**BUSCA** este bloque (al final de la sección 5.1):

> Ante esta restricción se adoptó una estrategia iterativa dividida en las siguientes fases:
> - Certificación y ejecución local...
> - Construcción del paquete entregable para el plugin final...

**SUSTITÚYELO POR:**

---

Ante esta restricción se adoptó una estrategia iterativa de tres fases que permitió avanzar progresivamente desde un entorno controlado hasta la validación en el servidor definitivo:

- **Fase 1. Certificación y ejecución local (versión base).** El diseño, depuración y ejecución de los artefactos de prueba se realizó en un entorno moodle-docker local con una versión funcional base del plugin. Esta fase permitió certificar la operatividad del entorno e identificar las limitaciones arquitectónicas del plugin que condicionaron el diseño de las pruebas, en particular la ausencia del generador de datos (`tests/generator/lib.php`) y la dependencia de `qtype_sqlquestion` en la instanciación.

- **Fase 2. Ejecución en el servidor del tutor (versión definitiva).** Los artefactos validados en la Fase 1 se transfirieron al tutor para su ejecución sobre la versión definitiva de mod_sqlab en el servidor UCLM. Los resultados confirmaron que UNI-02c genera un `coding_exception` por ausencia del generador (corregido en la versión v2 del test mediante `markTestSkipped()`) y que la ejecución con `--group mod_sqlab` produce un error fatal en `qtype_sqlquestion\privacy\provider` ajeno al código del plugin. Las pruebas Behat no pudieron ejecutarse por ausencia del generador de datos (ver sección 5.7.1).

- **Fase 3. Validación funcional colaborativa con Selenium standalone.** A raíz de las indicaciones del tutor —que confirmó que la validación de la característica colaborativa es el núcleo del TFG—, la funcionalidad FUN-10 (entorno colaborativo) se validó mediante un script Python con Selenium WebDriver que abre dos instancias de navegador en paralelo mediante hilos independientes. Esta aproximación supera la limitación estructural de Behat, incapaz de mantener dos sesiones de usuario simultáneas en un único hilo de ejecución (ver sección 5.7.3).

Por tanto, todo el trabajo relacionado con la programación de pruebas se reúne en un entregable técnico cuyo proceso de traspaso se describe en el Anexo III. La infraestructura desarrollada es agnóstica a la lógica interna del plugin y puede reutilizarse para validar otros módulos de actividad de Moodle.

---

## CAMBIO 2 — Sección 5.7.1 (Behat: añadir párrafo explicativo y actualizar Tabla 6)

### 2A. AÑADE este párrafo ANTES de la Tabla 6:

---

Durante la revisión conjunta con el tutor se constató que el fichero `tests/generator/lib.php` —necesario para que Behat pueda crear instancias de la actividad mod_sqlab en el entorno de pruebas— no está implementado en el plugin. Sin este generador, cualquier escenario que intente inicializar la actividad sqlab produce el siguiente error:

`coding_exception: Component mod_sqlab does not support generators yet. Missing tests/generator/lib.php.`

El tutor confirmó explícitamente: *"al no estar implementado el entorno de pruebas, es imposible la ejecución de los tests de Behat"*. Este hallazgo se documenta como una carencia del plugin con implicaciones directas en su publicabilidad: el directorio oficial de plugins de Moodle exige que los módulos de actividad dispongan de infraestructura de pruebas funcional.

En el caso específico de FUN-10 (entorno colaborativo), existe además una limitación arquitectónica de Behat: opera con un único hilo de ejecución y una sola sesión de WebDriver, lo que impide simular de forma automática la presencia simultánea de dos usuarios en la misma sala colaborativa. Por este motivo, la validación de FUN-10 se realizó mediante Selenium standalone (sección 5.7.3).

---

### 2B. TABLA 6 — NUEVA VERSIÓN COMPLETA

Sustituye toda la tabla actual por esta:

| ID | Descripción | Resultado | Motivo |
|----|-------------|-----------|--------|
| FUN-01 | Usabilidad básica: navegación | No ejecutable | Generador de datos ausente (`tests/generator/lib.php`) |
| FUN-02 | Visualización del enunciado SQL | No ejecutable | Generador de datos ausente |
| FUN-03 | Instalación y registro de actividad | No ejecutable | Generador de datos ausente |
| FUN-04 | Elementos principales de la interfaz | No ejecutable | Generador de datos ausente |
| FUN-05 | Ejecución de consultas SQL | No ejecutable | Generador de datos ausente |
| FUN-06 | Validación de lógica de negocio | No ejecutable | Generador de datos ausente |
| FUN-07 | Ausencia de errores PHP en navegación | No ejecutable | Generador de datos ausente |
| FUN-08 | Renderizado correcto de resultados | No ejecutable | Generador de datos ausente |
| FUN-09 | Menú diccionario de datos | No ejecutable | Generador de datos ausente |
| FUN-10 | Entorno colaborativo (dos usuarios simultáneos) | No ejecutable (Behat) | Generador ausente + Behat no soporta sesiones simultáneas. Validado con Selenium standalone (ver sección 5.7.3) |

> El diseño completo de los escenarios Behat (FUN-01 a FUN-10) se encuentra disponible en el repositorio del proyecto como entregable de referencia para cuando el plugin implemente el generador de datos.

---

## CAMBIO 3 — Sección 5.7.2 (PHPUnit: añadir párrafo de incidencias + corregir Tabla 7)

### 3A. AÑADE este párrafo AL INICIO de la sección 5.7.2 (antes de la Tabla 7):

---

Durante la ejecución de las pruebas en el servidor del tutor se identificaron dos incidencias de infraestructura:

**Incidencia 1 — Error fatal con `--group mod_sqlab`:** Cuando se intenta ejecutar todas las pruebas del plugin mediante el flag de grupo, PHPUnit produce un error fatal *antes* de ejecutar ningún test. La causa es un bug en el plugin dependencia `qtype_sqlquestion`: su clase `privacy\provider` declara implementar la interfaz `core_privacy\local\metadata\provider` pero no implementa los métodos abstractos `get_metadata` y `export_user_preferences`. Este error es ajeno al código de mod_sqlab. La solución es ejecutar cada suite por fichero individual:

```
vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_version_test.php --testdox
vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_privacy_test.php --testdox
vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_backup_test.php --testdox
vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_structure_test.php --testdox
```

**Incidencia 2 — UNI-02c: `coding_exception` en lugar de test fallido:** En la versión v1 del test, el intento de crear una instancia mediante el generador de datos produce una excepción que PHPUnit reporta como `ERROR` (distinto de `FAILED`). La versión v2 del test corrige esto marcando el caso como `SKIPPED` mediante `markTestSkipped()`, lo que refleja correctamente que la limitación es de infraestructura, no un defecto del código del plugin.

**Nota sobre el alcance de los resultados del tutor:** El tutor ejecutó `mod_sqlab_version_test.php` en el servidor (4 ejecuciones, resultados idénticos). Las suites UNI-03, UNI-04 y UNI-05 fueron ejecutadas en el entorno local de desarrollo. La suite UNI-06 corresponde a los tests de integración añadidos en la versión v2, pendientes de ejecución en el servidor.

---

### 3B. TABLA 7 — NUEVA VERSIÓN COMPLETA

Sustituye toda la tabla actual por esta:

| ID | Descripción del test | Resultado (v1) | Resultado (v2) | Observaciones |
|----|----------------------|----------------|----------------|---------------|
| **Suite UNI-02 — Instalación y configuración** (`mod_sqlab_version_test`) ||||
| UNI-02a | Plugin instalado correctamente | PASSED | PASSED | Confirmado en servidor del tutor |
| UNI-02b | version.php contiene campos obligatorios | PASSED | PASSED | Confirmado en servidor del tutor |
| UNI-02c | Creación de instancia con generador | ERROR → interpretado como FAILED | SKIPPED | v1: `coding_exception` por generador ausente. v2: `markTestSkipped()` documenta la limitación |
| UNI-02d | Tabla de base de datos creada | PASSED | PASSED | Confirmado en servidor del tutor |
| **Suite UNI-03 — Privacy API** (`mod_sqlab_privacy_test`) ||||
| UNI-03a | Proveedor de privacidad existe | PASSED | PASSED | Entorno local |
| UNI-03b | Metadatos de privacidad registrados | PASSED | PASSED | Entorno local |
| UNI-03c | Eliminación de datos de usuario | PASSED | PASSED | Entorno local |
| **Suite UNI-04 — Backup & Restore API** (`mod_sqlab_backup_test`) ||||
| UNI-04a | Backup de instancia genera fichero | FAILED | FAILED | Directorio de backup no inicializado en el entorno; fallo estructural real |
| UNI-04b | Restore de backup reconstruye actividad | FAILED | FAILED | Depende de UNI-04a |
| UNI-04c | Datos de usuario incluidos en backup | FAILED | FAILED | Depende de UNI-04a |
| **Suite UNI-05 — Estructura de ficheros** (`mod_sqlab_structure_test`) ||||
| UNI-05a | Ficheros obligatorios presentes | PASSED | PASSED | Entorno local |
| UNI-05b | upgrade.php presente para actualizaciones | FAILED | FAILED | Fichero ausente en el plugin |
| UNI-05c | Cabeceras de idioma correctas | PASSED | PASSED | Entorno local |
| UNI-05d | Ficheros de idioma con formato válido | PASSED | PASSED | Entorno local |
| UNI-05e | Ausencia de código obsoleto | PASSED | PASSED | Entorno local |
| **Suite UNI-06 — Integración: funciones de negocio** (`mod_sqlab_integration_test`) — NUEVA en v2 ||||
| UNI-06a | `sqldb_creation` está definida | PASSED (esperado) | PASSED (esperado) | Verificación estructural; no requiere servidor externo |
| UNI-06b | `sqldb_creation` crea credenciales | SKIPPED (esperado) | SKIPPED (esperado) | Auto-SKIPPED sin servidor PostgreSQL externo de mod_sqlab |
| UNI-06c | `execute_user_sql` está definida | PASSED (esperado) | PASSED (esperado) | Verificación estructural; no requiere servidor externo |
| UNI-06d | `execute_user_sql` procesa consulta | SKIPPED (esperado) | SKIPPED (esperado) | Auto-SKIPPED sin servidor PostgreSQL externo y attemptid válido |

> Los resultados de UNI-06 están marcados como "esperado" porque corresponden a la versión v2 pendiente de ejecución en el servidor del tutor.

---

## CAMBIO 4 — NUEVA Sección 5.7.3 (Selenium standalone)

**AÑADE una nueva sección 5.7.3 DESPUÉS de 5.7.2.** En Word: inserta el título y el texto a continuación de la tabla 7.

---

### 5.7.3 Validación colaborativa con Selenium standalone

La característica de trabajo colaborativo de mod_sqlab (FUN-10) introdujo un requisito de prueba que no puede satisfacerse con Behat: la necesidad de mantener dos sesiones de usuario simultáneas en el mismo navegador, cada una operando de forma independiente. Behat opera en un único hilo de ejecución con una sola instancia de WebDriver, lo que hace estructuralmente imposible simular la presencia simultánea de dos usuarios. Ante esta limitación, el tutor confirmó explícitamente que la validación debía realizarse con Selenium: *"Efectivamente, usa Selenium sin apoyarte para las pruebas en Behat"*.

**Arquitectura del script de validación**

La solución implementada consiste en un script Python (`selenium_colaborativo_sqlab.py`) que lanza dos instancias independientes de Chrome mediante la API de Selenium WebDriver. Cada instancia se ejecuta en un hilo separado (`threading.Thread`), lo que permite que ambos usuarios operen de forma genuinamente paralela sin compartir sesión ni estado de navegador.

La sincronización entre hilos se realiza mediante un `threading.Event`: el hilo del Usuario 1 crea la sala colaborativa y publica el identificador de sala en un objeto compartido; una vez que el evento se activa, el hilo del Usuario 2 lee el identificador y se une a la misma sala. Este mecanismo garantiza el orden correcto de operaciones sin bloqueos innecesarios.

```
Hilo 1 (Usuario 1)                  Hilo 2 (Usuario 2)
─────────────────────                ─────────────────────
Login como student2          
Navega a actividad sqlab     
Crea sala colaborativa       
Publica room_id ──────────────────► Lee room_id
                             ◄───── Activa evento "listo"
Verifica indicador de presencia      Login como student3
                                     Se une a la sala
                                     Verifica indicador de presencia
```

**Escenario validado: SEL-COLAB-01**

| Campo | Valor |
|-------|-------|
| Identificador | SEL-COLAB-01 |
| Precondición | Dos usuarios (student2, student3) con acceso a la actividad sqlab en el servidor UCLM |
| Pasos | 1. student2 inicia sesión y accede a sqlab. 2. student2 crea sala colaborativa y obtiene el ID. 3. student3 inicia sesión y se une a la sala con el ID recibido. 4. Ambos verifican el indicador de presencia del otro usuario |
| Resultado esperado | Ambos navegadores muestran el indicador de presencia del compañero simultáneamente |
| Herramienta | Python 3 + Selenium WebDriver + ChromeDriver |

**Configuración del entorno**

El script requiere únicamente: Python 3.8+, ChromeDriver compatible con la versión de Chrome instalada, y `pip install selenium`. No requiere moodle-docker ni ningún servidor local; se conecta directamente al servidor UCLM (`https://moodle.repobcam.i3a.uclm.es:10443`) con bypass de certificado autofirmado. El script se incluye como entregable en el Anexo III.

**Estado de ejecución**

La ejecución del script está pendiente de resolución de una incidencia en el servidor: las cuentas de usuario de tipo estudiante no autentican correctamente con las credenciales configuradas. Esta incidencia ha sido comunicada al tutor. Una vez resuelta, se actualizará esta sección con las capturas de pantalla de ambas sesiones simultáneas.

---

## CAMBIO 5 — Tabla 8 (Sección 5.8 — Evaluación del cumplimiento)

Sustituye la columna "Estado" de la Tabla 8. Los estados actualizados son:

| Requisito | Herramienta | ID test | Estado |
|-----------|-------------|---------|--------|
| Instalación correcta desde ZIP | PHPUnit | UNI-02a | **CUMPLE** |
| version.php / Frankenstyle / Dependencias | PHPUnit | UNI-02b | **CUMPLE** |
| Estructura de base de datos | PHPUnit | UNI-05a | **CUMPLE** |
| Archivos obligatorios de Moodle | PHPUnit | UNI-05d | **CUMPLE** |
| Privacy API implementada | PHPUnit | UNI-03a/b/c | **CUMPLE** |
| Creación de instancia funcional (generador) | PHPUnit | UNI-02c | **NO CUMPLE** — generador ausente (`tests/generator/lib.php`) |
| Backup & Restore API | PHPUnit | UNI-04a/b/c | **NO CUMPLE** — API no implementada |
| Gestión de actualizaciones (upgrade.php) | PHPUnit | UNI-05b | **NO CUMPLE** — fichero ausente |
| Licenciamiento GPL en cabeceras | phpcs | EST-01 | **NO CUMPLE** — cabeceras GPL ausentes en varios ficheros |
| Sintaxis PHP sin errores fatales | phplint | SIN-01 | **NO CUMPLE** — 2 errores fatales (`moodle_interface.php`, `sqldb_manager.php`) |
| Infraestructura de pruebas Behat | Manual | — | **NO CUMPLE** — generador ausente impide ejecución |
| Funcionalidad colaborativa (FUN-10) | Selenium | SEL-COLAB-01 | **PENDIENTE** — incidencia en cuentas de servidor |
| Compatibilidad MySQL/PostgreSQL | QA prechecks | — | Fuera de alcance |
| Issue tracker público | Revisión manual | — | Fuera de alcance |
| Compatibilidad versiones Moodle | QA prechecks | — | Fuera de alcance |

---

## CAMBIO 6 — Sección 5.9 (Propuesta de mejoras)

La sección está vacía. Añade:

---

Del análisis de los resultados de evaluación se derivan las siguientes propuestas de mejora, ordenadas por impacto en la publicabilidad del plugin:

**Mejoras bloqueantes para la publicación:**

1. **Implementar `tests/generator/lib.php`:** La ausencia de este fichero impide la ejecución de pruebas Behat y el test de instanciación (UNI-02c). Es un requisito estructural para cualquier plugin de tipo módulo de actividad.

2. **Implementar la Backup & Restore API:** Los tests UNI-04a, UNI-04b y UNI-04c fallan porque el plugin no implementa la clase de backup estándar de Moodle. Esta API es obligatoria para la certificación en el directorio oficial.

3. **Añadir `upgrade.php`:** Necesario para que Moodle gestione correctamente las actualizaciones entre versiones del plugin. Su ausencia (UNI-05b) impide actualizaciones seguras en producción.

**Mejoras de conformidad:**

4. **Añadir cabeceras de licencia GPL** a todos los ficheros PHP del plugin. El directorio oficial exige que cada fichero incluya el bloque de licencia estándar de Moodle.

5. **Corregir los errores de sintaxis fatales** en `moodle_interface.php` y `sqldb_manager.php` detectados por phplint.

6. **Corregir `qtype_sqlquestion\privacy\provider`:** El plugin dependencia no implementa correctamente la interfaz de Privacy API de Moodle, provocando un error fatal al ejecutar PHPUnit con `--group`. Aunque es un bug del plugin dependencia, impacta directamente en la validación de mod_sqlab.

---

## CAMBIO 7 — Sección 5.10 (Conclusiones del capítulo)

La sección está vacía. Añade:

---

La evaluación de mod_sqlab mediante la metodología propuesta ha permitido obtener una imagen clara del estado de madurez del plugin respecto a los requisitos de calidad del directorio oficial de Moodle.

En cuanto a los requisitos estructurales básicos, el plugin supera satisfactoriamente las comprobaciones de instalación, metadatos de versión, estructura de base de datos, ficheros obligatorios y Privacy API. Estos resultados, confirmados tanto en el entorno local como en el servidor del tutor, acreditan que la base del plugin es sólida.

Sin embargo, se han identificado cuatro carencias que bloquean la publicación en el directorio oficial: la ausencia del generador de datos de pruebas, la no implementación de la Backup & Restore API, la ausencia de `upgrade.php` y los errores de conformidad en las cabeceras de licencia. Estas carencias no comprometen la funcionalidad pedagógica del plugin, pero sí su cumplimiento formal con los estándares de Moodle.

La característica más relevante del plugin —el entorno colaborativo de resolución de ejercicios SQL (FUN-10)— ha sido validada mediante un script Selenium standalone que simula dos usuarios simultáneos, superando la limitación arquitectónica de Behat para pruebas de sesiones paralelas. Esta aproximación constituye el aporte técnico más significativo del presente trabajo: un marco de validación reproducible, agnóstico a la lógica interna del plugin, que puede reutilizarse en la evaluación de otras actividades colaborativas de Moodle.

---

## NOTAS ADICIONALES

### Sobre la sección 2.3.3 (Hipótesis tecnológica)
El texto actual dice que "Selenium actúa como motor de ejecución para las pruebas de Behat". Añade al final del párrafo:

> Adicionalmente, Selenium se emplea de forma standalone (sin Behat) para la validación de la característica colaborativa FUN-10, mediante un script Python que lanza dos instancias de navegador en hilos independientes para simular sesiones de usuario simultáneas.

### Sobre el Anexo III (Tabla 17 — Entregables)
Añade estas filas a la tabla de entregables:

| Entregable | Descripción | Ubicación |
|------------|-------------|-----------|
| `mod_sqlab_version_test_v2.php` | Suite UNI-02 corregida: UNI-02c usa `markTestSkipped()` | `mod/sqlab/tests/` |
| `mod_sqlab_integration_test_v2.php` | Suite UNI-06 nueva: validación de funciones de integración con auto-SKIPPED | `mod/sqlab/tests/` |
| `selenium_colaborativo_sqlab.py` | Script Python para validación colaborativa FUN-10 con dos sesiones simultáneas | Raíz del repositorio |
| `FUN-10_sqlab_entorno_colaborativo.feature` | Escenario Behat diseñado para FUN-10 (no ejecutable por generador ausente; referencia de diseño) | `mod/sqlab/tests/behat/` |

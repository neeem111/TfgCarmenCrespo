# Resultados de ejecución — mod_sqlab [VERSIÓN DEFINITIVA]

Fecha de ejecución: ____
Versión del plugin: ____
Moodle: 4.3.12 | PHP: 8.1 | PostgreSQL: 16

## PHPUnit

Comando ejecutado:
`vendor/bin/phpunit --group mod_sqlab --testdox`

Salida completa (pegar aquí o adjuntar phpunit_output.txt):

[pegar salida]

Resumen:
- Tests OK: __
- Tests FAILED: __
- Tests ERROR: __

Fallos específicos (copiar mensaje de error de cada FAILED):
| ID | Método | Mensaje de error |
|----|--------|-----------------|
|    |        |                 |

## phpcs

Comando ejecutado:
`phpcs --standard=moodle --extensions=php /var/www/html/mod/sqlab/`

Total errores: __
Total warnings: __

(adjuntar phpcs_output.txt)

## Behat (features ejecutados localmente, sin @javascript)

Comando: `vendor/bin/behat --tags="@mod_sqlab" --config=...`

| Feature | Escenarios | Passed | Failed | Skipped |
|---------|-----------|--------|--------|---------|
| FUN-01  |           |        |        |         |
| FUN-02  |           |        |        |         |
| FUN-03  |           |        |        |         |

## Behat (features con @javascript, requieren Selenium + PostgreSQL)

| Feature | Estado | Motivo si falla |
|---------|--------|----------------|
| FUN-04  |        |                |
| FUN-05  |        |                |
| FUN-06  |        |                |
| FUN-07  |        |                |
| FUN-08  |        |                |

## Adaptaciones realizadas en features

Indicar qué selectores/textos tuviste que ajustar:
- FUN-06: "Editor SQL" → ajustado a "____"
- FUN-06: "Ejecutar" → ajustado a "____"
- FUN-08: "puntos" → ajustado a "____"
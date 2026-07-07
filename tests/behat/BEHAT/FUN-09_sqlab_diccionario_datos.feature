@mod @mod_sqlab @javascript
Feature: FUN-09 Estudiante accede al menú de diccionario de datos en mod_sqlab
  # esto cubre CU-08: que el estudiante pueda consultar el diccionario de datos
  # a través del menú jerárquico de snippets del plugin.
  #
  # cómo funciona esta parte (para que quede claro por qué escribo estos pasos):
  #   el plugin trae un menú con snippets de código que hacen de equivalente a
  #   comandos psql, para que el alumno consulte el diccionario de datos de su
  #   propia BD sin tener que memorizar sintaxis de catálogo.
  #
  # entorno necesario:
  #   - mod_sqlab instalado y activo.
  #   - PostgreSQL externo accesible.
  #   - actividad "Consultas básicas" con al menos una pregunta.
  #   - student1 matriculado.
  #
  # lleva @javascript porque el menú del diccionario es dinámico.
  #
  # mismo problema de siempre: el Background depende del generador de datos que
  # mod_sqlab no tiene, así que esto no se ha ejecutado nunca de verdad. los
  # literales "Diccionario", "Tablas" etc. son mi mejor suposición de cómo se
  # llamarían los elementos del menú, habría que confirmarlos contra la interfaz real.

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | category |
      | Base de Datos | BBDD      | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | BBDD   | student |
    And the following "activities" exist:
      | activity | course | name              | intro                      | section |
      | sqlab    | BBDD   | Consultas básicas | Practica tus consultas SQL | 1       |

  # aquí compruebo simplemente que el menú del diccionario está visible al entrar
  Scenario: El estudiante ve el menú de diccionario de datos al acceder a la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Diccionario"
    And I should not see "Fatal error"
    And I should not see "Warning:"

  # aquí ya interactúo con el menú (lo despliego) y compruebo que no salta ningún error PHP
  Scenario: El estudiante puede desplegar el menú jerárquico del diccionario sin errores
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    And I follow "Diccionario"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # y aquí compruebo la parte más útil de la función: que al pinchar en un
  # snippet concreto (p. ej. "Tablas"), su código se inserta solo en el editor SQL
  Scenario: El código del snippet seleccionado del diccionario se inserta en el editor SQL
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    And I follow "Diccionario"
    And I follow "Tablas"
    Then the field "Editor SQL" should not be empty
    And I should not see "Fatal error"

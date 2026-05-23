@mod @mod_sqlab @javascript
Feature: FUN-08 Estudiante visualiza la puntuación obtenida tras ejecutar una consulta SQL
  # Cubre:
  # CU-06 — Estudiante: Ver la puntuación obtenida tras la ejecución
  #
  # REQUISITO DE ENTORNO (servidor del tutor):
  #   - Plugin mod_sqlab instalado y activo.
  #   - Servidor PostgreSQL externo configurado, accesible y con la BD de la actividad cargada.
  #   - Actividad SQLab "Consultas básicas" en el curso BBDD con al menos una pregunta
  #     configurada con puntuación y criterio de evaluación definido.
  #   - El estudiante student1 matriculado en el curso.
  #
  # Requiere @javascript: el editor SQL y los resultados de puntuación son dinámicos.
  #
  # NOTA PARA EL TUTOR:
  #   - Ajustar "SELECT * FROM empleados;" a una consulta correcta real para la actividad.
  #   - Ajustar los textos "puntos", "Puntuación" al texto real de la interfaz del plugin.
  #     Si el plugin muestra la nota en formato numérico (p. ej. "10/10", "100%"),
  #     adaptar el Then I should see al formato correspondiente.
  #   - Este escenario depende de CU-03 y CU-04: si la ejecución de SQL falla,
  #     la puntuación no se mostrará. Ejecutar FUN-06 antes para verificar el prerequisito.

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
      | activity | course | name              | intro                       | section |
      | sqlab    | BBDD   | Consultas básicas | Practica tus consultas SQL  | 1       |

  # CU-06a — La puntuación es visible tras ejecutar una consulta correcta
  Scenario: El estudiante ve la puntuación obtenida tras ejecutar una consulta correcta
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I set the field "Editor SQL" to "SELECT * FROM empleados;"
    And I press "Ejecutar"
    Then I should see "puntos"
    And I should not see "Fatal error"

  # CU-06b — La puntuación no genera errores PHP al mostrarse
  Scenario: La visualización de la puntuación no genera errores PHP
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I set the field "Editor SQL" to "SELECT * FROM empleados;"
    And I press "Ejecutar"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # CU-06c — La puntuación de una consulta incorrecta es 0 o inferior a la máxima
  Scenario: El estudiante ve que una consulta incorrecta obtiene puntuación baja o cero
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I set the field "Editor SQL" to "SELEC * FROM empleados;"
    And I press "Ejecutar"
    Then I should not see "Fatal error"
    And I should not see "Warning:"

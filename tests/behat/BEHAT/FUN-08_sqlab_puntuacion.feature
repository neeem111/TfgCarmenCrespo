@mod @mod_sqlab @javascript
Feature: FUN-08 Estudiante visualiza la puntuación obtenida tras ejecutar una consulta SQL
  # esto cubre CU-06: que el estudiante vea la puntuación que ha sacado después
  # de ejecutar su consulta SQL.
  #
  # entorno necesario:
  #   - mod_sqlab instalado y activo.
  #   - PostgreSQL externo accesible y con la BD de la actividad cargada.
  #   - la actividad "Consultas básicas" con al menos una pregunta que tenga
  #     puntuación y criterio de evaluación ya definidos.
  #   - student1 matriculado.
  #
  # lleva @javascript porque el editor y la puntuación se muestran de forma dinámica.
  #
  # ojo, este depende lógicamente de CU-03/CU-04 (FUN-06): si la ejecución de SQL
  # no funciona, la puntuación tampoco se va a mostrar. y como FUN-06 tampoco se
  # ha podido ejecutar (falta el generador de mod_sqlab), este tampoco. queda
  # como diseño: los textos "puntos"/"Puntuación" son un placeholder, habría que
  # adaptarlos al formato real (por ejemplo "10/10" o "100%") cuando se pueda probar.

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

  # aquí compruebo el caso normal: ejecuto una consulta correcta y espero ver
  # la puntuación reflejada en pantalla
  Scenario: El estudiante ve la puntuación obtenida tras ejecutar una consulta correcta
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I set the field "Editor SQL" to "SELECT * FROM empleados;"
    And I press "Ejecutar"
    Then I should see "puntos"
    And I should not see "Fatal error"

  # aquí me centro solo en la robustez: que mostrar la puntuación no rompa
  # la página con errores PHP
  Scenario: La visualización de la puntuación no genera errores PHP
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I set the field "Editor SQL" to "SELECT * FROM empleados;"
    And I press "Ejecutar"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # y aquí pruebo el caso contrario, consulta con error de sintaxis, para
  # comprobar al menos que no rompe nada (la comprobación de que la nota sea
  # baja/cero de verdad la dejo pendiente de poder ver la interfaz real)
  Scenario: El estudiante ve que una consulta incorrecta obtiene puntuación baja o cero
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I set the field "Editor SQL" to "SELEC * FROM empleados;"
    And I press "Ejecutar"
    Then I should not see "Fatal error"
    And I should not see "Warning:"

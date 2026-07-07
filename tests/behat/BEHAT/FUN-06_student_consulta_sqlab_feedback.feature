@mod @mod_sqlab @javascript
Feature: FUN-06 Estudiante escribe y ejecuta una consulta SQL y recibe feedback
  # este es de los importantes: cubre CU-03 (escribir y ejecutar una consulta SQL)
  # y CU-04 (ver el feedback de éxito/error), o sea el corazón de la lógica de
  # negocio del plugin.
  #
  # para que esto funcione necesito:
  #   - mod_sqlab instalado y activo.
  #   - un PostgreSQL externo accesible y con la BD de la actividad ya cargada.
  #   - la actividad "Consultas básicas" con al menos una pregunta que tenga una
  #     consulta correcta conocida y su respuesta esperada definida.
  #   - student1 matriculado.
  #
  # lleva @javascript porque el editor SQL y el botón de ejecutar son dinámicos.
  #
  # como en los anteriores, el Background monta la actividad con el paso de
  # "activities exist" tipo sqlab, así que depende del generador de datos que
  # el plugin no tiene todavía — esto nunca se llegó a ejecutar de verdad.
  # queda como especificación de lo que debería probarse: la consulta de ejemplo
  # ("SELECT * FROM empleados;") y los textos "Correcto"/"Error" son provisionales,
  # habría que ajustarlos al literal real de la interfaz cuando se pueda ejecutar.

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
    And I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"

  # aquí pruebo el camino feliz: consulta correcta, y quiero ver que el plugin
  # me da feedback positivo
  Scenario: El estudiante ejecuta una consulta SQL correcta y recibe feedback positivo
    When I set the field "Editor SQL" to "SELECT * FROM empleados;"
    And I press "Ejecutar"
    Then I should see "Correcto"

  # y aquí el caso contrario: consulta con un error de sintaxis a propósito
  # (SELEC en vez de SELECT), para comprobar que el feedback de error aparece
  # y que no me enseña "Correcto" por error
  Scenario: El estudiante ejecuta una consulta SQL incorrecta y recibe feedback de error
    When I set the field "Editor SQL" to "SELEC * FROM empleados;"
    And I press "Ejecutar"
    Then I should see "Error"
    And I should not see "Correcto"

  # este es más de "robustez": quiero asegurarme de que ejecutar una consulta
  # (aunque sea sencilla, un SELECT 1) no rompe la página con un error PHP visible
  Scenario: La ejecución de una consulta no genera errores PHP en la página
    When I set the field "Editor SQL" to "SELECT 1;"
    And I press "Ejecutar"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

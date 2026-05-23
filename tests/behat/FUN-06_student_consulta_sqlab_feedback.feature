@mod @mod_sqlab @javascript
Feature: FUN-06 Estudiante escribe y ejecuta una consulta SQL y recibe feedback
  # Cubre:
  # CU-03 — Estudiante: Escribir y ejecutar una consulta SQL en el editor
  # CU-04 — Estudiante: Ver el feedback de ejecución (éxito/error)
  #
  # REQUISITO DE ENTORNO (servidor del tutor):
  #   - Plugin mod_sqlab instalado y activo.
  #   - Servidor PostgreSQL externo configurado, accesible y con la BD de la actividad cargada.
  #   - Actividad SQLab "Consultas básicas" en el curso BBDD con al menos una pregunta que tenga:
  #       · Una consulta correcta conocida (para el escenario de éxito).
  #       · La respuesta correcta definida en el plugin (para comparar resultados).
  #   - El estudiante student1 matriculado en el curso.
  #
  # Requiere @javascript: el editor SQL y el botón de ejecución son componentes dinámicos.
  #
  # NOTA PARA EL TUTOR:
  #   - Ajustar "SELECT * FROM empleados;" a una consulta real válida para la primera
  #     pregunta de la actividad configurada en el servidor.
  #   - Ajustar los textos "Correcto", "Error" al texto real que muestra la interfaz
  #     del plugin ante cada tipo de resultado.
  #   - El selector "Editor SQL" puede necesitar ajuste si el campo tiene un id o
  #     placeholder diferente en la implementación final del plugin.

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

  # CU-03 + CU-04a — Consulta correcta → feedback de éxito
  Scenario: El estudiante ejecuta una consulta SQL correcta y recibe feedback positivo
    When I set the field "Editor SQL" to "SELECT * FROM empleados;"
    And I press "Ejecutar"
    Then I should see "Correcto"

  # CU-04b — Consulta incorrecta → feedback de error
  Scenario: El estudiante ejecuta una consulta SQL incorrecta y recibe feedback de error
    When I set the field "Editor SQL" to "SELEC * FROM empleados;"
    And I press "Ejecutar"
    Then I should see "Error"
    And I should not see "Correcto"

  # CU-04c — La ejecución de la consulta no genera un error PHP visible
  Scenario: La ejecución de una consulta no genera errores PHP en la página
    When I set the field "Editor SQL" to "SELECT 1;"
    And I press "Ejecutar"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

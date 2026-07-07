@mod @mod_sqlab @javascript
Feature: FUN-04 Estudiante visualiza el enunciado y los resultados esperados de una pregunta SQLab
  # esto cubre CU-02: que el estudiante vea el enunciado y los resultados esperados
  # de la pregunta al entrar en la actividad.
  #
  # para que esto funcione de verdad hace falta:
  #   - mod_sqlab instalado y activo.
  #   - un servidor PostgreSQL externo accesible desde Moodle.
  #   - una actividad SQLab en el curso BBDD con al menos una pregunta ya configurada
  #     (enunciado + resultado esperado definidos).
  #   - student1 matriculado en el curso.
  #
  # lleva @javascript porque la página de la actividad usa componentes dinámicos
  # para pintar el enunciado y el esquema de la BD.
  #
  # ojo: igual que en FUN-02, el Background usa "the following activities exist"
  # con type sqlab, así que depende del generador de datos del plugin, que no
  # existe todavía. este escenario es diseño de referencia, no algo que haya
  # podido ejecutar con éxito — habría que ajustar los textos de "I should see"
  # al literal real de la interfaz una vez el generador esté disponible.

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

  # aquí compruebo que el enunciado de la primera pregunta se ve nada más abrir la actividad
  Scenario: El estudiante ve el enunciado de la primera pregunta al abrir la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Enunciado"

  # aquí reviso que también se muestre el resultado esperado (o el esquema), no solo el enunciado
  Scenario: El estudiante puede ver el esquema o resultado esperado de la pregunta
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Resultado esperado"

  # y aquí compruebo que el editor SQL está en la página, listo para que el estudiante escriba su consulta
  Scenario: El estudiante ve el editor SQL al acceder a la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Editor"
    And the "textarea" "css_element" should exist

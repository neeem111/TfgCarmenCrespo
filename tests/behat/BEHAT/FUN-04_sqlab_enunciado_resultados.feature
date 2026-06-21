@mod @mod_sqlab @javascript
Feature: FUN-04 Estudiante visualiza el enunciado y los resultados esperados de una pregunta SQLab
  # Cubre:
  # CU-02 — Estudiante: Ver el enunciado y los resultados esperados
  #
  # REQUISITO DE ENTORNO (servidor del tutor):
  #   - Plugin mod_sqlab instalado y activo.
  #   - Servidor PostgreSQL externo configurado y accesible desde Moodle.
  #   - Actividad SQLab en el curso BBDD con al menos una pregunta configurada
  #     (enunciado + resultado esperado definidos).
  #   - El estudiante student1 debe estar matriculado en el curso.
  #
  # Requiere @javascript: la página de la actividad SQLab usa componentes dinámicos
  # para mostrar el enunciado y el esquema de la base de datos.
  #
  # NOTA PARA EL TUTOR:
  #   Ajustar los valores de "Then I should see" al texto real que aparece en la
  #   interfaz del plugin (enunciado de la primera pregunta, cabecera de la tabla
  #   de resultados esperados, etc.).

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

  # CU-02a — El enunciado de la pregunta es visible
  Scenario: El estudiante ve el enunciado de la primera pregunta al abrir la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Enunciado"

  # CU-02b — El resultado esperado o esquema es visible
  Scenario: El estudiante puede ver el esquema o resultado esperado de la pregunta
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Resultado esperado"

  # CU-02c — El editor SQL está presente en la página
  Scenario: El estudiante ve el editor SQL al acceder a la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Editor"
    And the "textarea" "css_element" should exist

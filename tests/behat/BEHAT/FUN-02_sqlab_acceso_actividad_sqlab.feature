@mod @mod_sqlab
Feature: FUN-02 Estudiante accede a una actividad SQLab desde el curso
  # Cubre:
  # CU-01 — Estudiante: Acceder a una actividad SQLab desde el curso
  #
  # REQUISITO DE ENTORNO (servidor del tutor):
  #   - El plugin mod_sqlab debe estar instalado y activo.
  #   - Debe existir al menos una actividad SQLab creada en el curso BBDD,
  #     o el generador de datos del plugin (tests/generator/lib.php) debe estar disponible.
  #   - No se requiere servidor PostgreSQL para este escenario (solo acceso a la página).
  #
  # Sin etiqueta @javascript: la navegación básica no requiere interacción dinámica.
  #
  # NOTA PARA EL TUTOR:
  #   Si el plugin tiene generador de datos (tests/generator/lib.php), el Background
  #   puede usar el paso "And the following "activities" exist" con type "sqlab".
  #   Si NO tiene generador, crear la actividad manualmente en el curso BBDD antes
  #   de ejecutar este test y ajustar el nombre "Consultas básicas" al nombre real.

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

  # CU-01
  Scenario: El estudiante ve la actividad SQLab en el curso
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should see "Consultas básicas"

  Scenario: El estudiante puede abrir la actividad SQLab y ver su descripción
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Practica tus consultas SQL"

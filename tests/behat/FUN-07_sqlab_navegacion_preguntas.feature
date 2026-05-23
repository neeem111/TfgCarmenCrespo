@mod @mod_sqlab @javascript
Feature: FUN-07 Estudiante navega entre preguntas de la actividad SQLab
  # Cubre:
  # CU-05 — Estudiante: Navegar entre preguntas de la misma actividad
  #
  # REQUISITO DE ENTORNO (servidor del tutor):
  #   - Plugin mod_sqlab instalado y activo.
  #   - Servidor PostgreSQL externo configurado y accesible.
  #   - Actividad SQLab "Consultas básicas" en el curso BBDD con AL MENOS DOS preguntas
  #     configuradas y visibles para el estudiante.
  #   - El estudiante student1 matriculado en el curso.
  #
  # Requiere @javascript: los controles de navegación entre preguntas son dinámicos.
  #
  # NOTA PARA EL TUTOR:
  #   - Ajustar los textos "Siguiente" y "Anterior" al texto real de los botones de
  #     navegación en la interfaz del plugin definitivo.
  #   - Si la navegación se implementa mediante una lista numerada de preguntas (en lugar
  #     de botones), sustituir I press "Siguiente" por el paso equivalente (p. ej.,
  #     I follow "Pregunta 2").
  #   - Verificar que la actividad tiene efectivamente más de una pregunta antes de ejecutar.

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

  # CU-05a — El control de navegación hacia la pregunta siguiente está presente
  Scenario: El estudiante ve los controles de navegación entre preguntas
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Siguiente"
    And I should not see "Fatal error"
    And I should not see "Warning:"

  # CU-05b — El estudiante puede navegar a la pregunta siguiente sin error
  Scenario: El estudiante puede avanzar a la siguiente pregunta sin generar errores
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I press "Siguiente"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # CU-05c — El estudiante puede volver a la pregunta anterior sin error
  Scenario: El estudiante puede volver a la pregunta anterior desde la segunda pregunta
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    And I press "Siguiente"
    When I press "Anterior"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

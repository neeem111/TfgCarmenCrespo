@mod @mod_sqlab @javascript
Feature: FUN-07 Estudiante navega entre preguntas de la actividad SQLab
  # esto cubre CU-05: que el estudiante pueda ir y venir entre preguntas de la
  # misma actividad sin que la interfaz se rompa ni suelte errores PHP.
  #
  # para ejecutarlo de verdad necesitaría:
  #   - mod_sqlab instalado y activo.
  #   - PostgreSQL externo accesible.
  #   - la actividad "Consultas básicas" con AL MENOS DOS preguntas configuradas
  #     y visibles (con solo una pregunta no tiene sentido probar "siguiente").
  #   - student1 matriculado.
  #
  # lleva @javascript porque los botones de navegación son dinámicos.
  #
  # otra vez el mismo problema de fondo: el Background depende de crear la
  # actividad vía generador, que no existe en mod_sqlab, así que esto tampoco
  # se ha podido ejecutar. lo dejo como diseño: los textos "Siguiente"/"Anterior"
  # son un supuesto razonable, pero habría que confirmarlos contra la interfaz
  # real (o cambiar el paso si la navegación fuera con una lista numerada tipo
  # "Pregunta 2" en vez de botones).

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

  # aquí solo compruebo que el botón/control de "siguiente" está ahí, visible,
  # antes de intentar usarlo
  Scenario: El estudiante ve los controles de navegación entre preguntas
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Siguiente"
    And I should not see "Fatal error"
    And I should not see "Warning:"

  # aquí ya pulso "siguiente" de verdad y compruebo que no salta ningún error PHP
  Scenario: El estudiante puede avanzar a la siguiente pregunta sin generar errores
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I press "Siguiente"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # y el camino de vuelta: avanzo y luego retrocedo, para comprobar que también
  # funciona en el sentido contrario sin romperse
  Scenario: El estudiante puede volver a la pregunta anterior desde la segunda pregunta
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    And I press "Siguiente"
    When I press "Anterior"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

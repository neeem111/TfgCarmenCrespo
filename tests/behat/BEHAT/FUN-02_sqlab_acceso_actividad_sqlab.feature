@mod @mod_sqlab
Feature: FUN-02 Estudiante accede a una actividad SQLab desde el curso
  # este escenario cubre CU-01 (que el estudiante pueda acceder a una actividad
  # SQLab desde el curso). la idea es sencilla: crear la actividad con el paso
  # "the following activities exist" tipo sqlab, y comprobar que se ve en el curso.
  #
  # el problema: para que el paso "activities exist" funcione, mod_sqlab necesita
  # tener implementado tests/generator/lib.php, y en el momento de escribir esto
  # el plugin NO lo tiene. o sea que este Background depende de una pieza que
  # el propio código del plugin no proporciona.
  #
  # no lleva @javascript porque en teoría la navegación básica no necesita interacción
  # dinámica, pero da igual: el test ni llega a montar el escenario.
  #
  # AVISO IMPORTANTE (para mí y para quien lo lea después):
  #   este escenario NUNCA llegó a ejecutarse con éxito. el Background falla al
  #   intentar crear la actividad "sqlab" porque no existe el generador de datos
  #   del plugin. queda aquí como diseño de referencia / especificación de lo que
  #   DEBERÍA probarse cuando el plugin tenga generador, no como prueba superada.
  #   ver FUN-02_CADENA para el intento de rodear este problema construyendo la
  #   cadena de dependencias a mano (que tampoco funcionó).

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

  # aquí simplemente compruebo que la actividad aparece listada en la página del curso
  # (pero como el Background no puede crearla, esto no llega ni a arrancar)
  Scenario: El estudiante ve la actividad SQLab en el curso
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should see "Consultas básicas"

  # y aquí compruebo que al entrar en la actividad se ve la descripción/intro
  # que le puse (mismo problema: depende del Background que falla)
  Scenario: El estudiante puede abrir la actividad SQLab y ver su descripción
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Practica tus consultas SQL"

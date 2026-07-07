@mod @mod_sqlab
Feature: FUN-01 Acceso al curso y verificación de roles en mod_sqlab
  # esto no cubre ningún CU en concreto de la tabla, es más una comprobación de base:
  # que Moodle reconoce bien los roles y permisos antes de meterme con SQLab.
  # lo que quiero comprobar aquí:
  #   - que el estudiante matriculado entra al curso y ve la actividad sin problema.
  #   - que el profesor (editingteacher) puede activar el modo edición.
  #   - que el estudiante NO puede activar el modo edición (control negativo).
  #
  # no lleva @javascript porque la navegación básica y el modo edición no necesitan JS
  # en Moodle 4.x, así que no hace falta arrastrar el selenium para esto.
  #
  # nota importante: este escenario NO depende de que exista una actividad SQLab ni
  # del generador de datos del plugin (mod_sqlab no aparece por ningún lado aquí),
  # solo del sistema de permisos estándar de Moodle. por eso es de los pocos que
  # sí llegué a ejecutar completo y en verde (junto con FUN-03 y FUN-05).

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | category |
      | Base de Datos | BBDD      | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | BBDD   | student        |
      | teacher1 | BBDD   | editingteacher |

  # aquí simplemente compruebo que el curso se ha creado bien y que el estudiante
  # matriculado puede entrar sin que Moodle le bloquee el acceso
  Scenario: El curso BBDD existe y el estudiante matriculado puede acceder a él
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should see "Base de Datos"
    And I should not see "You cannot enrol yourself in this course"

  # aquí lo que quiero comprobar es que las capabilities del rol de profesor
  # permiten editar el curso y que aparece el catálogo para añadir actividades
  # (esto lo necesito confirmado antes de dar por bueno cualquier test de FUN-05)
  Scenario: El profesor puede activar el modo edición y ver la opción de añadir actividades
    Given I log in as "teacher1"
    And I am on "BBDD" course homepage
    When I turn editing mode on
    Then I should see "Add an activity or resource"
    And I should see "Base de Datos"

  # este es el control negativo: quiero asegurarme de que el estudiante NO tiene
  # la capability para activar el modo edición, o sea que los permisos están bien puestos
  Scenario: El estudiante no puede activar el modo edición del curso
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should not see "Turn editing on"

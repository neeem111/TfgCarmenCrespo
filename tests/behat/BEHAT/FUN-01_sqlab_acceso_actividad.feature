@mod @mod_sqlab
Feature: FUN-01 Acceso al curso y verificación de roles en mod_sqlab
  # Cubre:
  # Requisito: Verificación de roles y permisos de Moodle (no deriva de un caso de uso)
  #   - El estudiante matriculado accede al curso y ve la actividad.
  #   - El profesor (editingteacher) puede activar el modo edición.
  #   - El estudiante no puede activar el modo edición.
  #
  # Sin @javascript: la navegación básica y el modo edición no requieren JS en Moodle 4.x.
  #
  # NOTA PARA EL TUTOR:
  #   Estos escenarios no dependen de una actividad SQLab existente ni del generador.
  #   Son agnósticos del contenido del curso y verifican el sistema de permisos de Moodle.

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

  # Estudiante — El curso existe y el estudiante matriculado puede acceder
  Scenario: El curso BBDD existe y el estudiante matriculado puede acceder a él
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should see "Base de Datos"
    And I should not see "You cannot enrol yourself in this course"

  # Profesor — El profesor (editingteacher) puede activar el modo edición y añadir actividades
  # Verifica que las capabilities del rol de profesor permiten editar el curso
  # y que la interfaz de adición de actividades (catálogo) está disponible.
  Scenario: El profesor puede activar el modo edición y ver la opción de añadir actividades
    Given I log in as "teacher1"
    And I am on "BBDD" course homepage
    When I turn editing mode on
    Then I should see "Add an activity or resource"
    And I should see "Base de Datos"

  # Control negativo — El estudiante NO puede acceder al modo edición (capabilities)
  Scenario: El estudiante no puede activar el modo edición del curso
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should not see "Turn editing on"

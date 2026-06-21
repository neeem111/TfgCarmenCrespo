@mod @mod_sqlab
Feature: FUN-01 Acceso al curso y verificación de roles en mod_sqlab
  # Cubre:
  # CU-00 — Sistema: El curso BBDD existe y el estudiante puede acceder
  # CU-07 — Administrador: Admin puede ver el catálogo de actividades del curso
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
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | BBDD   | student |

  # CU-00 — El curso existe y el estudiante con rol correcto puede acceder
  Scenario: El curso BBDD existe y el estudiante matriculado puede acceder a él
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should see "Base de Datos"
    And I should not see "You cannot enrol yourself in this course"

  # CU-07 — El administrador puede activar el modo edición y ver la interfaz de gestión
  # Verifica que las capabilities del rol admin permiten editar el curso
  # y que la interfaz de adición de actividades (catálogo) está disponible.
  Scenario: Admin puede activar el modo edición y ver la opción de añadir actividades
    Given I log in as "admin"
    And I am on "BBDD" course homepage
    When I turn editing mode on
    Then I should see "Add an activity or resource"
    And I should see "Base de Datos"

  # CU-07b — El estudiante NO puede acceder al modo edición (control negativo de capabilities)
  Scenario: El estudiante no puede activar el modo edición del curso
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should not see "Turn editing on"

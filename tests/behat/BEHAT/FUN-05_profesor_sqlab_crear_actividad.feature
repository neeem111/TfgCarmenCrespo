@mod @mod_sqlab @javascript
Feature: FUN-05 El profesor crea y configura una actividad SQLab
  # Cubre:
  # CU-07 — Profesor: Crear y configurar una actividad SQLab en un curso

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | category |
      | Base de Datos | BBDD      | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | BBDD   | editingteacher |

  Scenario: El profesor añade una nueva actividad SQLab al curso BBDD
    Given I log in as "teacher1"
    And I am on "BBDD" course homepage
    # Activamos la edición para poder añadir actividades
    And I turn editing mode on
    # Moodle Behat tiene un paso específico para añadir actividades y rellenar campos
    When I add a "sqlab" to section "1" and I fill the form with:
      | Name        | Práctica Evaluable SQL |
      | Description | Resuelve las siguientes consultas |
    And I click on "Save and return to course" "button"
    Then I should see "Práctica Evaluable SQL"
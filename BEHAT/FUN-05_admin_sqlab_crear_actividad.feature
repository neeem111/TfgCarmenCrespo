@mod @mod_sqlab @javascript
Feature: FUN-05 Administrador crea y configura una actividad SQLab
  # Cubre:
  # CU-08 — Administrador: Crear y configurar una actividad SQLab en un curso

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | category |
      | Base de Datos | BBDD      | 0        |

  Scenario: Admin añade una nueva actividad SQLab al curso BBDD
    Given I log in as "admin"
    And I am on "BBDD" course homepage
    # Activamos la edición para poder añadir actividades
    And I turn editing mode on
    # Moodle Behat tiene un paso específico para añadir actividades y rellenar campos
    When I add a "sqlab" to section "1" and I fill the form with:
      | Name        | Práctica Evaluable SQL |
      | Description | Resuelve las siguientes consultas |
    And I click on "Save and return to course" "button"
    Then I should see "Práctica Evaluable SQL"
@mod @mod_sqlab @javascript
Feature: FUN-05 El profesor crea y configura una actividad SQLab
  # esto cubre CU-07: que el profesor pueda crear y configurar una actividad
  # SQLab en un curso desde cero, usando el formulario estándar de Moodle para
  # añadir actividades (no uso el generador de datos aquí, precisamente porque
  # quiero probar el flujo real del formulario, así que este escenario no
  # depende de tests/generator/lib.php).
  #
  # lo bueno de hacerlo así es que no necesito el generador del plugin, así que
  # este es uno de los tres escenarios (junto con FUN-01 y FUN-03) que sí llegué
  # a ejecutar completo y en verde sobre Moodle real.

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

  # aquí lo que compruebo es el flujo completo: activar edición, añadir la
  # actividad sqlab desde el catálogo, rellenar el formulario y guardar. si esto
  # funciona, confirma que el formulario de configuración del plugin persiste
  # bien los datos en la BD de Moodle (relacionado con el requisito de FUN-05
  # de la memoria: "formulario de configuración y persistencia BD")
  Scenario: El profesor añade una nueva actividad SQLab al curso BBDD
    Given I log in as "teacher1"
    And I am on "BBDD" course homepage
    # activo la edición para poder añadir actividades
    And I turn editing mode on
    # Moodle Behat tiene un paso específico para añadir actividades y rellenar campos
    When I add a "sqlab" to section "1" and I fill the form with:
      | Name        | Práctica Evaluable SQL |
      | Description | Resuelve las siguientes consultas |
    And I click on "Save and return to course" "button"
    Then I should see "Práctica Evaluable SQL"
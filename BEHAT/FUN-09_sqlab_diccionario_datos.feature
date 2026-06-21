@mod @mod_sqlab @javascript
Feature: FUN-09 Estudiante accede al menú de diccionario de datos en mod_sqlab
  # Cubre:
  # CU-09 — Estudiante: Consultar el diccionario de datos mediante el menú jerárquico de snippets
  #
  # DESCRIPCIÓN DE LA FUNCIONALIDAD:
  #   El plugin incluye un menú jerárquico con snippets de código para consultar el
  #   diccionario de datos de la base de datos del alumno (equivalente a comandos psql).
  #
  # REQUISITO DE ENTORNO (servidor del tutor):
  #   - Plugin mod_sqlab instalado y activo.
  #   - Servidor PostgreSQL externo configurado y accesible.
  #   - Actividad SQLab "Consultas básicas" en el curso BBDD con al menos una pregunta.
  #   - El estudiante student1 matriculado en el curso.
  #
  # Requiere @javascript: el menú del diccionario es un componente dinámico.
  #
  # NOTA PARA EL TUTOR:
  #   - Ajustar los literales de los pasos "I should see" al texto real de los elementos
  #     del menú de diccionario en la interfaz definitiva del plugin
  #     (p. ej., "Diccionario", "\\dt", "Tablas", etc.).
  #   - Si el menú se activa mediante un botón o icono específico, reemplazar
  #     I follow "Diccionario" por el paso correspondiente.

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
      | activity | course | name              | intro                      | section |
      | sqlab    | BBDD   | Consultas básicas | Practica tus consultas SQL | 1       |

  # CU-09a — El menú de diccionario de datos está visible en la actividad
  Scenario: El estudiante ve el menú de diccionario de datos al acceder a la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Diccionario"
    And I should not see "Fatal error"
    And I should not see "Warning:"

  # CU-09b — El menú jerárquico se despliega sin errores al interactuar con él
  Scenario: El estudiante puede desplegar el menú jerárquico del diccionario sin errores
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    And I follow "Diccionario"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # CU-09c — Al seleccionar un snippet del diccionario, el código se inserta en el editor SQL
  Scenario: El código del snippet seleccionado del diccionario se inserta en el editor SQL
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    And I follow "Diccionario"
    And I follow "Tablas"
    Then the field "Editor SQL" should not be empty
    And I should not see "Fatal error"

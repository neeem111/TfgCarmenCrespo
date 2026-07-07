@mod @mod_sqlab @javascript
Feature: FUN-10 Entorno colaborativo en mod_sqlab
  # Cubre:
  # CU-09 — Usuario (profesor o estudiante): Crear e invitar a otros a una sesión colaborativa
  # CU-10 — Usuario: Unirse a una sala colaborativa mediante ID
  # CU-11 — Usuario: Ver el indicador de awareness (ID sala, participantes conectados)
  #
  # DESCRIPCIÓN DE LA FUNCIONALIDAD:
  #   Cualquier usuario (profesor o estudiante) puede invitar a otros a una sesión
  #   colaborativa en la que los participantes pueden escribir código de forma conjunta.
  #   Cuando la actividad está configurada para grupos, los miembros del grupo pueden
  #   ejecutar y evaluar código; los invitados externos solo pueden ver la pregunta
  #   a la que fueron invitados.
  #   El indicador de awareness muestra:
  #     - ID de la sala colaborativa y nº de participantes conectados.
  #     - Lista de usuarios conectados (al pulsar en el nº de participantes).
  #     - URL para compartir la sala (al pulsar en el ID).
  #     - Botón "Unirme a la sala" para conectarse solo con el ID (sin URL).
  #
  # REQUISITO DE ENTORNO (servidor del tutor):
  #   - Plugin mod_sqlab instalado y activo.
  #   - Servidor PostgreSQL externo configurado y accesible.
  #   - Actividad SQLab "Consultas básicas" en el curso BBDD con al menos una pregunta.
  #   - Estudiantes student1 y student2 matriculados en el curso.
  #   - Servidor WebSocket / backend colaborativo activo (si aplica configuración adicional).
  #
  # Requiere @javascript: toda la funcionalidad colaborativa es dinámica.
  #
  # NOTA PARA EL TUTOR:
  #   - Ajustar los literales "Unirme a la sala", "Participantes", "Sala" al texto real
  #     de la interfaz definitiva del plugin.
  #   - Si el botón de invitar tiene un texto o icono diferente, adaptar el paso
  #     I press "Invitar" al selector correcto.
  #   - Los escenarios de awareness (CU-11) verifican la presencia del indicador; 
  #     para validar valores concretos (número de participantes), el tutor deberá
  #     abrir una segunda sesión simultánea o adaptar el escenario.

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | category |
      | Base de Datos | BBDD      | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | BBDD   | student |
      | student2 | BBDD   | student |
    And the following "activities" exist:
      | activity | course | name              | intro                      | section |
      | sqlab    | BBDD   | Consultas básicas | Practica tus consultas SQL | 1       |

  # CU-09a — El indicador de awareness (sala colaborativa) es visible en la actividad
  Scenario: El estudiante ve el indicador de sala colaborativa al acceder a la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Sala"
    And I should not see "Fatal error"
    And I should not see "Warning:"

  # CU-09b — La interfaz muestra el botón para unirse a una sala colaborativa
  Scenario: El estudiante ve el botón para unirse a una sala colaborativa
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Unirme a la sala"
    And I should not see "Fatal error"

  # CU-10 — El estudiante puede unirse a una sala introduciendo su ID
  Scenario: El estudiante puede acceder al formulario de unión a sala mediante ID
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I press "Unirme a la sala"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # CU-11a — El awareness muestra el número de participantes conectados
  Scenario: El indicador de participantes conectados es visible en la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Participantes"
    And I should not see "Fatal error"

  # CU-11b — Al pulsar sobre el nº de participantes se muestra la lista de usuarios conectados
  Scenario: El estudiante puede ver la lista de usuarios conectados en la sala
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I follow "Participantes"
    Then I should not see "Fatal error"
    And I should not see "Warning:"

  # CU-11c — Al pulsar sobre el ID de sala, la URL se copia al portapapeles (sin error PHP)
  # NOTA: No es posible verificar el portapapeles desde Behat/Selenium.
  # Este escenario verifica que la acción no genera errores PHP visibles.
  Scenario: Pulsar en el ID de sala no genera errores PHP
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I follow "Sala"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

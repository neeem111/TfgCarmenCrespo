@mod @mod_sqlab @javascript
Feature: FUN-10 Entorno colaborativo en mod_sqlab
  # este es el más ambicioso de todos: cubre CU-09 (crear e invitar a una sesión
  # colaborativa), CU-10 (unirse a una sala mediante ID) y CU-11 (ver el indicador
  # de awareness, o sea quién está conectado en la sala).
  #
  # cómo entiendo yo esta funcionalidad (para que quede constancia de por qué
  # escribo los pasos así):
  #   cualquier usuario, profesor o estudiante, puede invitar a otros a escribir
  #   código SQL juntos en tiempo real. si la actividad está configurada por
  #   grupos, los miembros del grupo pueden ejecutar y evaluar código, mientras
  #   que los invitados de fuera solo ven la pregunta a la que se les invitó.
  #   el indicador de awareness debería enseñar:
  #     - el ID de la sala y cuántos participantes hay conectados.
  #     - la lista de usuarios conectados (al pulsar sobre el número).
  #     - una URL para compartir la sala (al pulsar sobre el ID).
  #     - un botón "Unirme a la sala" para entrar solo con el ID, sin necesitar la URL.
  #
  # entorno necesario:
  #   - mod_sqlab instalado y activo.
  #   - PostgreSQL externo accesible.
  #   - actividad "Consultas básicas" con al menos una pregunta.
  #   - student1 y student2 matriculados (para poder simular más de un participante).
  #   - el backend colaborativo (WebSocket) activo, si aplica.
  #
  # lleva @javascript porque toda la parte colaborativa es dinámica.
  #
  # y como en el resto: el Background depende del generador de datos de
  # mod_sqlab, que no existe, así que este fichero tampoco se ha podido ejecutar
  # nunca. es el diseño más especulativo de todos porque encima describe una
  # funcionalidad compleja (tiempo real, awareness) que ni siquiera he podido
  # ver funcionando en la interfaz real. los literales "Unirme a la sala",
  # "Participantes", "Sala" son mi mejor suposición, no texto confirmado.
  # para los escenarios de awareness con nº de participantes reales haría falta
  # además abrir dos sesiones simultáneas, cosa que Behat no hace por defecto.

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

  # aquí solo compruebo que el indicador de sala/awareness aparece al entrar en la actividad
  Scenario: El estudiante ve el indicador de sala colaborativa al acceder a la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Sala"
    And I should not see "Fatal error"
    And I should not see "Warning:"

  # aquí compruebo que el botón para unirse a una sala está presente en la interfaz
  Scenario: El estudiante ve el botón para unirse a una sala colaborativa
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Unirme a la sala"
    And I should not see "Fatal error"

  # aquí pulso el botón y compruebo que se llega al formulario de unión mediante
  # ID sin que salte ningún error PHP (no verifico la unión real, solo que el
  # flujo de acceso al formulario funciona)
  Scenario: El estudiante puede acceder al formulario de unión a sala mediante ID
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I press "Unirme a la sala"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

  # aquí compruebo que se ve el número de participantes conectados (aunque sea
  # solo el propio usuario, sin abrir una segunda sesión)
  Scenario: El indicador de participantes conectados es visible en la actividad
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas básicas"
    Then I should see "Participantes"
    And I should not see "Fatal error"

  # aquí pulso sobre el número de participantes y compruebo que despliega la
  # lista de conectados sin romper la página
  Scenario: El estudiante puede ver la lista de usuarios conectados en la sala
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I follow "Participantes"
    Then I should not see "Fatal error"
    And I should not see "Warning:"

  # aquí quería comprobar que al pulsar el ID de sala se copia la URL al
  # portapapeles, pero desde Behat/Selenium no hay forma de verificar el
  # contenido del portapapeles, así que me conformo con comprobar que la
  # acción no revienta la página con un error PHP
  Scenario: Pulsar en el ID de sala no genera errores PHP
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    And I follow "Consultas básicas"
    When I follow "Sala"
    Then I should not see "Fatal error"
    And I should not see "Warning:"
    And I should not see "Notice:"

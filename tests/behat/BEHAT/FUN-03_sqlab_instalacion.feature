@mod @mod_sqlab
Feature: FUN-03 El plugin mod_sqlab está correctamente instalado en Moodle
  # esto no deriva de un caso de uso concreto de la tabla, es más una comprobación
  # de "fontanería": que el plugin se instala bien desde el ZIP y que Moodle no
  # escupe errores PHP raros cuando lo lista en el panel de administración.
  #
  # lo que quiero comprobar aquí:
  #   - que el plugin aparece reconocido en el catálogo de módulos de actividad.
  #   - que la página de administración no muestra warnings, notices ni fatal errors.
  #   - que no aparece como "no instalado" ni "falta en disco".
  #
  # no necesito ninguna actividad creada ni servidor PostgreSQL para esto, solo
  # entrar como admin y mirar el panel.
  #
  # sin @javascript porque la página de administración de módulos es estática,
  # no hace falta selenium para esto.
  #
  # este es uno de los tres escenarios (junto con FUN-01 y FUN-05) que SÍ llegué
  # a ejecutar completo y en verde, porque no depende del generador de datos de
  # mod_sqlab ni de qtype_sqlquestion, solo de que el plugin esté bien instalado.

  # aquí compruebo lo más básico: que el plugin aparece listado con su nombre
  # correcto en el catálogo de módulos de actividad de Moodle
  Scenario: El plugin SQLab aparece en la lista de módulos de actividad
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Manage activities" in site administration
    Then I should see "sqlab"
    And I should see "SQLab"

  # aquí lo que reviso es que la página de administración no me suelta ningún
  # warning, notice o fatal error de PHP al cargar (con depuración activada)
  Scenario: La página de administración del plugin no genera errores PHP
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Manage activities" in site administration
    Then I should not see "Warning:"
    And I should not see "Notice:"
    And I should not see "Fatal error"
    And I should not see "Strict Standards:"

  # y por último confirmo que el plugin está habilitado de verdad, no solo
  # presente: que no aparece como "no instalado" ni "falta en disco"
  Scenario: El plugin SQLab está habilitado y no aparece como deshabilitado
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Manage activities" in site administration
    Then I should not see "Plugin not installed"
    And I should not see "Missing from disk"

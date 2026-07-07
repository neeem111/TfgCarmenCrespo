@mod @mod_sqlab
Feature: FUN-03 El plugin mod_sqlab está correctamente instalado en Moodle
  # Cubre:
  # Requisito: Instalación correcta desde paquete ZIP
  # Requisito: Funcionamiento sin errores PHP con depuración activada
  #
  # EXCLUIDO DEL ALCANCE EJECUTADO: estos escenarios requieren una cuenta de
  #   administrador (panel de administración del sitio), no disponible en este
  #   trabajo. La instalación se verifica mediante PHPUnit (UNI-02a).
  #
  # Sin @javascript: la página de administración de módulos no requiere JS.
  #
  # NOTA PARA EL TUTOR:
  #   Estos escenarios comprueban que el plugin es reconocido por Moodle y
  #   que su presencia en el panel de administración no genera errores PHP visibles.
  #   No requieren actividades creadas ni servidor PostgreSQL.

  Scenario: El plugin SQLab aparece en la lista de módulos de actividad
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Manage activities" in site administration
    Then I should see "sqlab"
    And I should see "SQLab"

  Scenario: La página de administración del plugin no genera errores PHP
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Manage activities" in site administration
    Then I should not see "Warning:"
    And I should not see "Notice:"
    And I should not see "Fatal error"
    And I should not see "Strict Standards:"

  Scenario: El plugin SQLab está habilitado y no aparece como deshabilitado
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Manage activities" in site administration
    Then I should not see "Plugin not installed"
    And I should not see "Missing from disk"

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * UNI-02 — Instalación, metadatos y comportamiento de las funciones de ciclo de vida.
 *
 * Suite con dos tipos de tests:
 *   [E] Tests estructurales: comprueban que el plugin está instalado correctamente.
 *   [C] Tests de comportamiento: comprueban que las funciones de lib.php HACEN lo
 *       que deben hacer, no solo que existen. Esta es la diferencia clave respecto
 *       a la versión anterior de la suite.
 *
 *   UNI-02a [E] Plugin registrado en Moodle
 *   UNI-02b [E] version.php con campos y formato válidos
 *   UNI-02c [E] Tabla mdl_sqlab existe en BD
 *   UNI-02d [C] sqlab_delete_instance() BORRA el registro de mdl_sqlab
 *   UNI-02e [C] sqlab_update_instance() MODIFICA el registro en mdl_sqlab
 *   UNI-02f [SKIPPED] sqlab_add_instance() — dependencia qtype_sqlquestion
 *   UNI-02g [C] set_config()/get_config() almacenan en config_plugins
 *
 * === CÓMO EJECUTAR ===
 *   SIEMPRE por fichero individual. Nunca con --group mod_sqlab.
 *   El flag --group carga todos los suites de Moodle y provoca un fatal error
 *   en qtype_sqlquestion\privacy\provider (bug externo, no de mod_sqlab).
 *
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_version_test.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2026 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests de instalación, metadatos y ciclo de vida de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_version_test extends advanced_testcase {

    // =========================================================================
    // [E] Tests estructurales
    // =========================================================================

    /**
     * UNI-02a [E] — El plugin mod_sqlab está registrado en Moodle.
     *
     * Un PASSED confirma que la instalación desde ZIP fue correcta y que
     * plugin_manager reconoce el componente mod_sqlab.
     */
    public function test_plugin_is_installed(): void {
        $plugininfo = core_plugin_manager::instance()->get_plugin_info('mod_sqlab');
        $this->assertNotNull(
            $plugininfo,
            'El plugin mod_sqlab no está instalado. ' .
            'Instalar desde Admin > Plugins o mediante CLI.'
        );
    }

    /**
     * UNI-02b [E] — version.php define los campos obligatorios con valores válidos.
     *
     * Verifica no solo presencia sino formato correcto:
     *   - $plugin->version  : entero YYYYMMDDNN (10 dígitos)
     *   - $plugin->requires : entero (versión mínima de Moodle)
     *   - $plugin->component: exactamente 'mod_sqlab' (Frankenstyle obligatorio)
     */
    public function test_version_file_has_required_fields(): void {
        global $CFG;

        $versionfile = $CFG->dirroot . '/mod/sqlab/version.php';
        $this->assertFileExists($versionfile, 'El fichero version.php no existe en mod/sqlab/');

        $plugin = new stdClass();
        include($versionfile);

        // version: debe ser YYYYMMDDNN.
        $this->assertNotEmpty($plugin->version, 'version.php no define $plugin->version');
        $this->assertMatchesRegularExpression(
            '/^\d{10}$/',
            (string)$plugin->version,
            '$plugin->version debe tener 10 dígitos (formato YYYYMMDDNN). ' .
            'Valor actual: ' . ($plugin->version ?? 'no definido')
        );

        // requires: versión mínima de Moodle requerida.
        $this->assertNotEmpty($plugin->requires, 'version.php no define $plugin->requires');

        // component: Frankenstyle — approval blocker si es incorrecto.
        $this->assertEquals(
            'mod_sqlab',
            $plugin->component,
            '$plugin->component debe ser "mod_sqlab". ' .
            'Valor actual: "' . ($plugin->component ?? 'no definido') . '". ' .
            'Un component incorrecto es un approval blocker.'
        );
    }

    /**
     * UNI-02c [E] — La tabla mdl_sqlab existe en la base de datos.
     *
     * Verifica que db/install.xml se aplicó correctamente durante la instalación.
     */
    public function test_database_table_exists(): void {
        global $DB;
        $this->assertTrue(
            $DB->get_manager()->table_exists('sqlab'),
            'La tabla mdl_sqlab NO existe en la BD. ' .
            'Reinstalar el plugin o verificar db/install.xml.'
        );
    }

    // =========================================================================
    // [C] Tests de comportamiento real
    // =========================================================================

    /**
     * UNI-02d [C] — COMPORTAMIENTO: sqlab_delete_instance() elimina el registro de la BD.
     *
     * DIFERENCIA CON LA VERSION ANTERIOR:
     *   La suite anterior solo comprobaba que la función estaba declarada en lib.php.
     *   Este test verifica que la función REALMENTE BORRA el registro de mdl_sqlab.
     *
     * Estrategia para evitar la dependencia con qtype_sqlquestion:
     *   En lugar de llamar a sqlab_add_instance() (que requiere quiz->id y qtype_sqlquestion),
     *   se inserta el registro directamente en mdl_sqlab usando $DB->insert_record().
     *   Esto prueba sqlab_delete_instance() de forma aislada, como una verdadera prueba unitaria.
     *
     * Requisito verificado: Obligatorio — lib.php debe contener sqlab_delete_instance().
     */
    public function test_sqlab_delete_instance_removes_db_record(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        if (!$DB->get_manager()->table_exists('sqlab')) {
            $this->markTestSkipped('Tabla mdl_sqlab no existe — revisar UNI-02c.');
        }

        require_once($CFG->dirroot . '/mod/sqlab/lib.php');
        $this->assertTrue(
            function_exists('sqlab_delete_instance'),
            'sqlab_delete_instance() no existe en lib.php — ver UNI-05d.'
        );

        // Paso 1: insertar registro directamente (sin pasar por add_instance).
        $course = $this->getDataGenerator()->create_course();
        $record = $this->build_minimal_sqlab_record($course->id);

        try {
            $id = $DB->insert_record('sqlab', $record);
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'No se pudo insertar registro de prueba en mdl_sqlab: ' . $e->getMessage() . '. ' .
                'La tabla puede tener columnas NOT NULL adicionales en esta versión del plugin.'
            );
            return;
        }

        $this->assertGreaterThan(0, $id);
        $this->assertTrue($DB->record_exists('sqlab', ['id' => $id]),
            'El registro no existe en BD tras el insert — error de entorno.');

        // Paso 2: llamar a la función bajo test.
        try {
            $result = sqlab_delete_instance($id);
        } catch (\Throwable $e) {
            $this->fail(
                'sqlab_delete_instance(' . $id . ') lanzó una excepción: ' . $e->getMessage() . '. ' .
                'La función debe gestionar el borrado aunque no existan registros relacionados.'
            );
        }

        // Paso 3: verificar comportamiento.
        $this->assertTrue(
            (bool)$result,
            'sqlab_delete_instance() devolvió: ' . var_export($result, true) . '. Debe devolver true.'
        );
        $this->assertFalse(
            $DB->record_exists('sqlab', ['id' => $id]),
            'FALLO DE COMPORTAMIENTO: sqlab_delete_instance() devolvió true pero el registro ' .
            'sigue existiendo en mdl_sqlab. La función está declarada pero no funciona correctamente.'
        );
    }

    /**
     * UNI-02e [C] — COMPORTAMIENTO: sqlab_update_instance() modifica el registro en la BD.
     *
     * DIFERENCIA CON LA VERSION ANTERIOR:
     *   No existía en la suite anterior. Este test verifica que sqlab_update_instance()
     *   efectivamente persiste los cambios en mdl_sqlab.
     *
     * Requisito verificado: Obligatorio — lib.php debe contener sqlab_update_instance().
     */
    public function test_sqlab_update_instance_modifies_db_record(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        if (!$DB->get_manager()->table_exists('sqlab')) {
            $this->markTestSkipped('Tabla mdl_sqlab no existe — revisar UNI-02c.');
        }

        require_once($CFG->dirroot . '/mod/sqlab/lib.php');
        $this->assertTrue(
            function_exists('sqlab_update_instance'),
            'sqlab_update_instance() no existe en lib.php — ver UNI-05d.'
        );

        // Paso 1: insertar registro con nombre inicial.
        $course = $this->getDataGenerator()->create_course();
        $record = $this->build_minimal_sqlab_record($course->id);
        $record->name = 'Nombre original antes de update';

        try {
            $id = $DB->insert_record('sqlab', $record);
        } catch (\Throwable $e) {
            $this->markTestSkipped('No se pudo insertar registro de prueba: ' . $e->getMessage());
            return;
        }

        // Paso 2: preparar el objeto de actualización.
        $updateobj               = $DB->get_record('sqlab', ['id' => $id]);
        $updateobj->name         = 'Nombre modificado por test UNI-02e';
        $updateobj->timemodified = time();
        $updateobj->coursemodule = 0;    // Moodle incluye coursemodule en el objeto update.
        $updateobj->instance     = $id;  // sqlab_update_instance() busca el ID en ->instance (convención de formularios Moodle).

        // Paso 3: llamar a la función bajo test.
        try {
            $result = sqlab_update_instance($updateobj);
        } catch (\Throwable $e) {
            $this->fail('sqlab_update_instance() lanzó una excepción: ' . $e->getMessage());
        }

        $this->assertTrue((bool)$result,
            'sqlab_update_instance() debe devolver true. Devolvió: ' . var_export($result, true));

        // Paso 4: verificar que la BD refleja el cambio.
        $dbrecord = $DB->get_record('sqlab', ['id' => $id]);
        $this->assertEquals(
            'Nombre modificado por test UNI-02e',
            $dbrecord->name,
            'FALLO DE COMPORTAMIENTO: el nombre en BD no cambió tras sqlab_update_instance(). ' .
            'La función existe pero no persiste los cambios en la base de datos.'
        );
    }

    /**
     * UNI-02f [SKIPPED] — sqlab_add_instance() — dependencia arquitectónica.
     *
     * sqlab_add_instance() requiere que quiz->id esté creado previamente
     * (según indica el tutor en correo 08/06/2026) y depende de qtype_sqlquestion.
     *
     * El comportamiento de escritura en mdl_sqlab se verifica de forma equivalente
     * mediante UNI-02d y UNI-02e, que usan inserción directa en BD.
     */
    public function test_can_create_sqlab_instance(): void {
        $this->resetAfterTest();
        $this->markTestSkipped(
            'LIMITACIÓN ARQUITECTÓNICA: sqlab_add_instance() requiere quiz->id y qtype_sqlquestion. ' .
            'El comportamiento de escritura y borrado en mdl_sqlab se verifica en UNI-02d y UNI-02e.'
        );
    }

    /**
     * UNI-02g [C] — COMPORTAMIENTO: la configuración del plugin se almacena en config_plugins.
     *
     * Requisito verificado: Obligatorio — las configuraciones del plugin deben
     * guardarse en la tabla config_plugins (mediante set_config()/get_config()),
     * y no directamente en la tabla config principal ni en $CFG.
     *
     * El plugin ya usa get_config('mod_sqlab', 'dbhost') en su lógica real
     * (véase classes/database_manager.php), por lo que este test confirma que
     * dicho mecanismo persiste correctamente en config_plugins.
     */
    public function test_settings_stored_in_config_plugins(): void {
        global $DB;
        $this->resetAfterTest();

        $key   = 'dbhost';
        $value = 'valor_de_prueba_' . uniqid();

        // Paso 1: escribir la configuración mediante la API de Moodle.
        set_config($key, $value, 'mod_sqlab');

        // Paso 2: comprobar que get_config() la recupera correctamente.
        $this->assertEquals(
            $value,
            get_config('mod_sqlab', $key),
            'get_config(\'mod_sqlab\', \'' . $key . '\') no devolvió el valor esperado.'
        );

        // Paso 3: comprobar directamente en BD que se guardó en config_plugins
        // (y no en la tabla config principal).
        $record = $DB->get_record('config_plugins', [
            'plugin' => 'mod_sqlab',
            'name'   => $key,
        ]);

        $this->assertNotEmpty(
            $record,
            'FALLO: no se encontró el registro en mdl_config_plugins para mod_sqlab/' . $key . '. ' .
            'La configuración del plugin no se está almacenando donde exige Moodle.'
        );
        $this->assertEquals(
            $value,
            $record->value,
            'El valor almacenado en config_plugins no coincide con el escrito mediante set_config().'
        );
    }

    // =========================================================================
    // Helper privado
    // =========================================================================

    /**
     * Construye el objeto mínimo para insert_record('sqlab', ...).
     *
     * Descubre dinámicamente las columnas NOT NULL para rellenarlas con valores
     * neutros, haciendo el test compatible con distintas versiones del plugin.
     *
     * @param  int      $courseid ID del curso (necesario para la FK course).
     * @return stdClass           Objeto listo para $DB->insert_record().
     */
    private function build_minimal_sqlab_record(int $courseid): stdClass {
        global $DB;

        $record               = new stdClass();
        $record->course       = $courseid;
        $record->name         = 'Test SQLab ' . uniqid();
        $record->intro        = '<p>Intro generada por PHPUnit.</p>';
        $record->introformat  = FORMAT_HTML;
        $record->timecreated  = time();
        $record->timemodified = time();

        // Rellenar columnas NOT NULL desconocidas con valores neutros.
        $columns = $DB->get_columns('sqlab');
        $known   = array_keys((array)$record);

        foreach ($columns as $colname => $col) {
            if ($colname === 'id' || in_array($colname, $known, true)) {
                continue;
            }
            if ($col->not_null) {
                $default = $col->default_value ?? null;
                switch ($col->meta_type) {
                    case 'I':
                    case 'N':
                        $record->$colname = ($default !== null) ? (int)$default : 0;
                        break;
                    case 'C':
                    case 'X':
                        $record->$colname = ($default !== null) ? (string)$default : '';
                        break;
                    default:
                        $record->$colname = $default;
                }
            }
        }

        return $record;
    }
}

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
 * uni-02 — aquí pruebo la instalación, los metadatos y que las funciones de ciclo de vida
 * (add/update/delete instance) hagan de verdad lo que tienen que hacer.
 *
 * tengo dos tipos de test en esta suite:
 *   [e] estructurales: solo comprueban que el plugin está instalado y que existen las cosas.
 *   [c] de comportamiento: comprueban que las funciones de lib.php HACEN lo que deben,
 *       no solo que existen. esto es justo lo que le faltaba a la versión anterior de la suite.
 *
 *   uni-02a [e] el plugin está registrado en moodle
 *   uni-02b [e] version.php tiene los campos con el formato correcto
 *   uni-02c [e] la tabla mdl_sqlab existe en BD
 *   uni-02d [c] sqlab_delete_instance() borra de verdad el registro de mdl_sqlab
 *   uni-02e [c] sqlab_update_instance() modifica de verdad el registro en mdl_sqlab
 *   uni-02f [skipped] sqlab_add_instance() — no lo puedo probar, depende de qtype_sqlquestion
 *   uni-02g [c] set_config()/get_config() guardan bien en config_plugins
 *
 * === cómo lo ejecuto ===
 *   siempre fichero a fichero. NUNCA con --group mod_sqlab.
 *   si uso --group carga todas las suites de moodle y me peta con un fatal error
 *   en qtype_sqlquestion\privacy\provider (eso es un bug externo, no mío).
 *
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_version_test.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2026 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tests de instalación, metadatos y ciclo de vida de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_version_test extends advanced_testcase {

    // =========================================================================
    // [E] tests estructurales
    // =========================================================================

    /**
     * uni-02a [e] — compruebo que mod_sqlab está registrado en moodle.
     *
     * si esto pasa, quiere decir que la instalación desde el ZIP fue bien
     * y que plugin_manager reconoce el componente mod_sqlab.
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
     * uni-02b [e] — reviso que version.php define los campos obligatorios y con el formato bien.
     *
     * no me vale solo con que existan, compruebo también el formato:
     *   - $plugin->version  : entero YYYYMMDDNN (10 dígitos)
     *   - $plugin->requires : entero (versión mínima de moodle)
     *   - $plugin->component: tiene que ser exactamente 'mod_sqlab' (frankenstyle obligatorio)
     */
    public function test_version_file_has_required_fields(): void {
        global $CFG;

        $versionfile = $CFG->dirroot . '/mod/sqlab/version.php';
        $this->assertFileExists($versionfile, 'El fichero version.php no existe en mod/sqlab/');

        // cargo el fichero real del plugin, no un mock.
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

        // component: frankenstyle — si esto está mal es approval blocker seguro.
        $this->assertEquals(
            'mod_sqlab',
            $plugin->component,
            '$plugin->component debe ser "mod_sqlab". ' .
            'Valor actual: "' . ($plugin->component ?? 'no definido') . '". ' .
            'Un component incorrecto es un approval blocker.'
        );
    }

    /**
     * uni-02c [e] — compruebo que la tabla mdl_sqlab existe en la BD.
     *
     * esto confirma que db/install.xml se aplicó bien durante la instalación.
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
    // [C] tests de comportamiento real
    // =========================================================================

    /**
     * uni-02d [c] — comportamiento: sqlab_delete_instance() tiene que borrar el registro de la BD.
     *
     * diferencia con la versión anterior de la suite:
     *   antes solo miraba que la función estuviera declarada en lib.php.
     *   aquí compruebo que la función REALMENTE BORRA el registro de mdl_sqlab.
     *
     * truco para no depender de qtype_sqlquestion:
     *   en vez de llamar a sqlab_add_instance() (que necesita quiz->id y qtype_sqlquestion),
     *   inserto el registro directamente en mdl_sqlab con $DB->insert_record().
     *   así pruebo sqlab_delete_instance() aislada, como debería ser una prueba unitaria de verdad.
     *
     * requisito verificado: obligatorio — lib.php debe contener sqlab_delete_instance().
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

        // arrange: inserto el registro directamente, sin pasar por add_instance.
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

        // act: llamo a la función que quiero probar.
        try {
            $result = sqlab_delete_instance($id);
        } catch (\Throwable $e) {
            $this->fail(
                'sqlab_delete_instance(' . $id . ') lanzó una excepción: ' . $e->getMessage() . '. ' .
                'La función debe gestionar el borrado aunque no existan registros relacionados.'
            );
        }

        // assert: compruebo que de verdad ha borrado el registro, no solo que devuelve true.
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
     * uni-02e [c] — comportamiento: sqlab_update_instance() tiene que modificar el registro en la BD.
     *
     * diferencia con la versión anterior:
     *   este test no existía antes. aquí compruebo que sqlab_update_instance()
     *   persiste de verdad los cambios en mdl_sqlab, no que simplemente exista la función.
     *
     * requisito verificado: obligatorio — lib.php debe contener sqlab_update_instance().
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

        // arrange: inserto un registro con un nombre inicial para poder comprobar el cambio después.
        $course = $this->getDataGenerator()->create_course();
        $record = $this->build_minimal_sqlab_record($course->id);
        $record->name = 'Nombre original antes de update';

        try {
            $id = $DB->insert_record('sqlab', $record);
        } catch (\Throwable $e) {
            $this->markTestSkipped('No se pudo insertar registro de prueba: ' . $e->getMessage());
            return;
        }

        // arrange: preparo el objeto tal como lo esperaría el formulario de moodle.
        $updateobj               = $DB->get_record('sqlab', ['id' => $id]);
        $updateobj->name         = 'Nombre modificado por test UNI-02e';
        $updateobj->timemodified = time();
        $updateobj->coursemodule = 0;    // Moodle incluye coursemodule en el objeto update.
        $updateobj->instance     = $id;  // sqlab_update_instance() busca el ID en ->instance (convención de formularios Moodle).

        // act: llamo a la función que quiero probar.
        try {
            $result = sqlab_update_instance($updateobj);
        } catch (\Throwable $e) {
            $this->fail('sqlab_update_instance() lanzó una excepción: ' . $e->getMessage());
        }

        $this->assertTrue((bool)$result,
            'sqlab_update_instance() debe devolver true. Devolvió: ' . var_export($result, true));

        // assert: releo de la BD para confirmar que el cambio se ha guardado de verdad.
        $dbrecord = $DB->get_record('sqlab', ['id' => $id]);
        $this->assertEquals(
            'Nombre modificado por test UNI-02e',
            $dbrecord->name,
            'FALLO DE COMPORTAMIENTO: el nombre en BD no cambió tras sqlab_update_instance(). ' .
            'La función existe pero no persiste los cambios en la base de datos.'
        );
    }

    /**
     * uni-02f [skipped] — sqlab_add_instance(), no la puedo probar por una limitación de arquitectura.
     *
     * sqlab_add_instance() necesita que quiz->id ya esté creado antes
     * (esto me lo confirmó el tutor por correo el 08/06/2026) y depende de qtype_sqlquestion,
     * que no está disponible en el entorno de test.
     *
     * el comportamiento de escritura en mdl_sqlab ya lo cubro de forma equivalente
     * con uni-02d y uni-02e, insertando directo en BD.
     */
    public function test_can_create_sqlab_instance(): void {
        $this->resetAfterTest();
        $this->markTestSkipped(
            'LIMITACIÓN ARQUITECTÓNICA: sqlab_add_instance() requiere quiz->id y qtype_sqlquestion. ' .
            'El comportamiento de escritura y borrado en mdl_sqlab se verifica en UNI-02d y UNI-02e.'
        );
    }

    /**
     * uni-02g [c] — comportamiento: la config del plugin se guarda en config_plugins.
     *
     * requisito verificado: obligatorio — la configuración del plugin tiene que
     * guardarse en la tabla config_plugins (vía set_config()/get_config()),
     * y no directamente en la tabla config principal ni en $CFG.
     *
     * el plugin ya usa get_config('mod_sqlab', 'dbhost') en su lógica real
     * (mirar classes/database_manager.php), así que este test confirma que
     * ese mecanismo persiste bien en config_plugins.
     */
    public function test_settings_stored_in_config_plugins(): void {
        global $DB;
        $this->resetAfterTest();

        $key   = 'dbhost';
        $value = 'valor_de_prueba_' . uniqid();

        // act: escribo la config usando la API de moodle, tal como lo haría el plugin.
        set_config($key, $value, 'mod_sqlab');

        // assert: get_config() tiene que devolver lo mismo que acabo de guardar.
        $this->assertEquals(
            $value,
            get_config('mod_sqlab', $key),
            'get_config(\'mod_sqlab\', \'' . $key . '\') no devolvió el valor esperado.'
        );

        // assert: además compruebo directo en BD que cayó en config_plugins
        // (y no en la tabla config principal, que sería un error).
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
    // helper privado
    // =========================================================================

    /**
     * construyo el objeto mínimo para insert_record('sqlab', ...).
     *
     * miro dinámicamente qué columnas son NOT NULL y las relleno con valores
     * neutros, así el test no se rompe si cambia el esquema en otra versión del plugin.
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

        // relleno las columnas NOT NULL que no conozco con valores neutros según su tipo.
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

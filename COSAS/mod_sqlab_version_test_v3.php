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
 * UNI-02 — Pruebas funcionales de instalación, metadatos y funciones públicas de mod_sqlab.
 *
 * Esta suite verifica que el plugin mod_sqlab está correctamente instalado y que las
 * funciones obligatorias de lib.php se comportan conforme a su especificación:
 *
 * UNI-02a  El plugin está registrado en Moodle (plugin_manager).
 * UNI-02b  version.php define todas las variables obligatorias con valores válidos.
 * UNI-02c  sqlab_supports() devuelve los valores correctos para cada feature conocida.
 * UNI-02d  sqlab_supports() devuelve null para features no reconocidas.
 * UNI-02e  sqlab_add_instance() lanza coding_exception con quiz_id vacío.
 * UNI-02f  sqlab_add_instance() lanza coding_exception con quiz_id no numérico.
 * UNI-02g  sqlab_update_instance() existe y es invocable.
 * UNI-02h  sqlab_delete_instance() existe y es invocable.
 * UNI-02i  La tabla mdl_sqlab existe en la base de datos.
 * UNI-02j  La tabla mdl_sqlab_attempts existe en la base de datos.
 * UNI-02k  La tabla mdl_sqlab_db_user_credentials existe en la base de datos.
 * UNI-02l  sqlab_update_instance() lanza coding_exception cuando $data->instance está vacío.
 * UNI-02m  sqlab_delete_instance() devuelve false para un ID de instancia inexistente.
 * UNI-02n  sqlab_delete_instance() devuelve true y elimina el registro para un ID válido.
 * UNI-02o  sqlab_is_question_answered() devuelve false cuando no existe respuesta.
 *
 * Nota de ejecución:
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_version_test_v3.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2025 Carmen Crespo Navarro, Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Suite UNI-02: instalación, metadatos y funciones públicas de lib.php.
 *
 * @group mod_sqlab
 */
class mod_sqlab_version_test_v3 extends advanced_testcase {

    /** @var string Ruta absoluta a lib.php del plugin. */
    private string $libphp;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->libphp = $CFG->dirroot . '/mod/sqlab/lib.php';
        // Asegurar que las funciones de lib.php están disponibles.
        if (file_exists($this->libphp)) {
            require_once($this->libphp);
        }
    }

    // -------------------------------------------------------------------------
    // UNI-02a — Instalación
    // -------------------------------------------------------------------------

    /**
     * UNI-02a — El plugin mod_sqlab está registrado en Moodle.
     *
     * Verifica que plugin_manager reconoce el componente mod_sqlab, lo que confirma
     * que la instalación desde ZIP se realizó correctamente y que los metadatos del
     * plugin (version.php, db/install.xml) fueron procesados por Moodle.
     *
     * Requisito verificado: Instalación correcta desde paquete ZIP.
     */
    public function test_plugin_is_installed(): void {
        $plugininfo = core_plugin_manager::instance()->get_plugin_info('mod_sqlab');
        $this->assertNotNull(
            $plugininfo,
            'El plugin mod_sqlab no está registrado en Moodle. ' .
            'Asegúrate de que la instalación desde ZIP se completó correctamente.'
        );
        $this->assertEquals(
            'mod_sqlab',
            $plugininfo->component,
            'El componente del plugin no coincide con el Frankenstyle esperado "mod_sqlab".'
        );
    }

    // -------------------------------------------------------------------------
    // UNI-02b — version.php
    // -------------------------------------------------------------------------

    /**
     * UNI-02b — version.php define todas las variables obligatorias con valores válidos.
     *
     * Verifica que el fichero version.php sigue la estructura exigida por Moodle:
     * - $plugin->version  : número de versión en formato YYYYMMDDNN
     * - $plugin->requires : versión mínima de Moodle compatible
     * - $plugin->component: nombre Frankenstyle del plugin
     * - $plugin->release  : cadena de versión legible
     * - $plugin->maturity : constante de madurez (MATURITY_STABLE, etc.)
     *
     * Requisito verificado: Metadatos obligatorios en version.php / Frankenstyle.
     */
    public function test_version_file_has_required_fields(): void {
        global $CFG;
        $versionfile = $CFG->dirroot . '/mod/sqlab/version.php';
        $this->assertFileExists($versionfile, 'version.php no existe en mod/sqlab/');

        $plugin = new stdClass();
        include($versionfile);

        // Comprobar que $plugin->version es un entero con formato de fecha (≥ 2020010100).
        $this->assertNotEmpty($plugin->version,
            'version.php no define $plugin->version');
        $this->assertIsNumeric($plugin->version,
            '$plugin->version debe ser un número (formato YYYYMMDDNN)');
        $this->assertGreaterThan(2020010100, (int)$plugin->version,
            '$plugin->version debe ser ≥ 2020010100 (formato YYYYMMDDNN)');

        // Comprobar $plugin->requires.
        $this->assertNotEmpty($plugin->requires,
            'version.php no define $plugin->requires');
        $this->assertIsNumeric($plugin->requires,
            '$plugin->requires debe ser numérico (versión mínima de Moodle)');

        // Comprobar $plugin->component con Frankenstyle exacto.
        $this->assertNotEmpty($plugin->component,
            'version.php no define $plugin->component');
        $this->assertEquals('mod_sqlab', $plugin->component,
            '$plugin->component debe ser exactamente "mod_sqlab" (Frankenstyle)');

        // Comprobar $plugin->release (cadena legible de versión).
        $this->assertNotEmpty($plugin->release,
            'version.php no define $plugin->release');
        $this->assertIsString($plugin->release,
            '$plugin->release debe ser una cadena de texto');

        // Comprobar $plugin->maturity (constante predefinida de Moodle).
        $this->assertNotEmpty($plugin->maturity,
            'version.php no define $plugin->maturity');
        $validMaturities = [MATURITY_ALPHA, MATURITY_BETA, MATURITY_RC, MATURITY_STABLE];
        $this->assertContains((int)$plugin->maturity, $validMaturities,
            '$plugin->maturity debe ser una constante válida de Moodle: ' .
            'MATURITY_ALPHA(50), MATURITY_BETA(100), MATURITY_RC(150), MATURITY_STABLE(200)');
    }

    // -------------------------------------------------------------------------
    // UNI-02c / UNI-02d — sqlab_supports()
    // -------------------------------------------------------------------------

    /**
     * UNI-02c — sqlab_supports() devuelve los valores correctos para features conocidas.
     *
     * Verifica la lógica de negocio de sqlab_supports(), la función obligatoria de
     * lib.php que declara qué capacidades de Moodle implementa el módulo. Se comprueban
     * tanto los valores que deben ser true como los que deben ser false.
     *
     * Features que deben ser TRUE (el plugin las soporta):
     *   FEATURE_GRADE_HAS_GRADE         → el plugin puede asignar notas
     *   FEATURE_GRADE_OUTCOMES          → soporta resultados de calificación
     *   FEATURE_BACKUP_MOODLE2          → implementa Backup & Restore API
     *   FEATURE_SHOW_DESCRIPTION        → muestra descripción en el curso
     *   FEATURE_CONTROLS_GRADE_VISIBILITY → controla visibilidad de notas
     *
     * Features que deben ser FALSE (el plugin no las soporta):
     *   FEATURE_GROUPS                  → sin soporte de grupos
     *   FEATURE_GROUPINGS               → sin soporte de agrupaciones
     *   FEATURE_MOD_INTRO               → sin campo de introducción
     *
     * Requisito verificado: Declaración correcta de capacidades del módulo (lib.php).
     */
    public function test_sqlab_supports_returns_correct_values(): void {
        $this->assertFileExists($this->libphp,
            'lib.php no existe; no se puede verificar sqlab_supports()');
        $this->assertTrue(function_exists('sqlab_supports'),
            'La función sqlab_supports() no está definida en lib.php');

        // Features que el plugin DEBE soportar.
        $mustBeTrue = [
            'FEATURE_GRADE_HAS_GRADE'          => FEATURE_GRADE_HAS_GRADE,
            'FEATURE_GRADE_OUTCOMES'            => FEATURE_GRADE_OUTCOMES,
            'FEATURE_BACKUP_MOODLE2'            => FEATURE_BACKUP_MOODLE2,
            'FEATURE_SHOW_DESCRIPTION'          => FEATURE_SHOW_DESCRIPTION,
            'FEATURE_CONTROLS_GRADE_VISIBILITY' => FEATURE_CONTROLS_GRADE_VISIBILITY,
        ];
        foreach ($mustBeTrue as $name => $constant) {
            $this->assertTrue(
                sqlab_supports($constant),
                "sqlab_supports($name) debe devolver true — el plugin lo soporta"
            );
        }

        // Features que el plugin NO soporta.
        $mustBeFalse = [
            'FEATURE_GROUPS'    => FEATURE_GROUPS,
            'FEATURE_GROUPINGS' => FEATURE_GROUPINGS,
            'FEATURE_MOD_INTRO' => FEATURE_MOD_INTRO,
        ];
        foreach ($mustBeFalse as $name => $constant) {
            $this->assertFalse(
                sqlab_supports($constant),
                "sqlab_supports($name) debe devolver false — el plugin NO lo soporta"
            );
        }
    }

    /**
     * UNI-02d — sqlab_supports() devuelve null para features no reconocidas.
     *
     * El contrato de lib.php establece que cualquier feature no listada en el
     * array de capacidades debe devolver null, no false ni true. Esto permite
     * a Moodle aplicar sus valores por defecto para esa feature.
     *
     * Requisito verificado: Manejo correcto de features desconocidas en sqlab_supports().
     */
    public function test_sqlab_supports_returns_null_for_unknown_feature(): void {
        $this->assertTrue(function_exists('sqlab_supports'),
            'sqlab_supports() no está definida en lib.php');

        // Usar una constante de feature real que el plugin no declara explícitamente.
        $result = sqlab_supports('FEATURE_NONEXISTENT_ABCXYZ_99999');
        $this->assertNull(
            $result,
            'sqlab_supports() debe devolver null para features no reconocidas, ' .
            'no false ni true. Moodle usa null para aplicar sus defaults.'
        );
    }

    // -------------------------------------------------------------------------
    // UNI-02e / UNI-02f — sqlab_add_instance() validación de Quiz ID
    // -------------------------------------------------------------------------

    /**
     * UNI-02e — sqlab_add_instance() lanza coding_exception cuando quiz_id está vacío.
     *
     * sqlab_add_instance() tiene una validación explícita:
     *   if (empty($data->quizid) || !is_numeric($data->quizid)) {
     *       throw new coding_exception('Invalid Quiz ID');
     *   }
     * Este test verifica que esa validación funciona para quizid vacío.
     *
     * Requisito verificado: Manejo de errores de entrada en sqlab_add_instance().
     */
    public function test_add_instance_throws_on_empty_quiz_id(): void {
        $this->resetAfterTest();
        $this->assertTrue(function_exists('sqlab_add_instance'),
            'sqlab_add_instance() no está definida en lib.php');

        $data = new stdClass();
        $data->quizid = '';   // quizid vacío → debe lanzar coding_exception

        $this->expectException(coding_exception::class);
        sqlab_add_instance($data);
    }

    /**
     * UNI-02f — sqlab_add_instance() lanza coding_exception cuando quiz_id no es numérico.
     *
     * Verifica la segunda condición de la misma validación: !is_numeric($data->quizid).
     * Un quiz_id de tipo cadena alfanumérica debe ser rechazado.
     *
     * Requisito verificado: Manejo de errores de entrada en sqlab_add_instance().
     */
    public function test_add_instance_throws_on_non_numeric_quiz_id(): void {
        $this->resetAfterTest();

        $data = new stdClass();
        $data->quizid = 'invalid_id';  // No numérico → debe lanzar coding_exception

        $this->expectException(coding_exception::class);
        sqlab_add_instance($data);
    }

    // -------------------------------------------------------------------------
    // UNI-02g / UNI-02h — Existencia de funciones obligatorias de lib.php
    // -------------------------------------------------------------------------

    /**
     * UNI-02g — sqlab_update_instance() existe y es invocable.
     *
     * Moodle exige que todo módulo de actividad implemente sqlab_update_instance().
     * Este test verifica que la función está definida en lib.php.
     *
     * Requisito verificado: Función obligatoria update_instance en lib.php.
     */
    public function test_update_instance_function_exists(): void {
        $this->assertTrue(function_exists('sqlab_update_instance'),
            'sqlab_update_instance() no está definida en lib.php. ' .
            'Esta función es obligatoria para todo módulo de actividad de Moodle.');
    }

    /**
     * UNI-02h — sqlab_delete_instance() existe y es invocable.
     *
     * Moodle exige que todo módulo de actividad implemente sqlab_delete_instance().
     *
     * Requisito verificado: Función obligatoria delete_instance en lib.php.
     */
    public function test_delete_instance_function_exists(): void {
        $this->assertTrue(function_exists('sqlab_delete_instance'),
            'sqlab_delete_instance() no está definida en lib.php. ' .
            'Esta función es obligatoria para todo módulo de actividad de Moodle.');
    }

    // -------------------------------------------------------------------------
    // UNI-02l — sqlab_update_instance(): validación de instance ID
    // -------------------------------------------------------------------------

    /**
     * UNI-02l — sqlab_update_instance() lanza coding_exception cuando $data->instance está vacío.
     *
     * La implementación en lib.php tiene la validación:
     *   if (empty($data->instance)) { throw new coding_exception('Instance ID is missing'); }
     * Este test verifica que esa guardia funciona correctamente.
     *
     * Requisito verificado: Validación de datos de entrada en sqlab_update_instance().
     */
    public function test_update_instance_throws_on_missing_instance_id(): void {
        $this->resetAfterTest();
        $this->assertTrue(function_exists('sqlab_update_instance'),
            'sqlab_update_instance() no está definida en lib.php');

        $data = new stdClass();
        // instance vacío → debe lanzar coding_exception antes de tocar la BD.

        $this->expectException(coding_exception::class);
        sqlab_update_instance($data);
    }

    // -------------------------------------------------------------------------
    // UNI-02m / UNI-02n — sqlab_delete_instance(): lógica de borrado
    // -------------------------------------------------------------------------

    /**
     * UNI-02m — sqlab_delete_instance() devuelve false para un ID de instancia inexistente.
     *
     * La lógica de lib.php es:
     *   if (!$DB->record_exists('sqlab', ['id' => $id])) { return false; }
     * Sin instancia en BD, la función debe retornar false sin lanzar excepción.
     *
     * Requisito verificado: Comportamiento de sqlab_delete_instance() ante ID inválido.
     */
    public function test_delete_instance_returns_false_for_nonexistent_id(): void {
        $this->resetAfterTest();
        $this->assertTrue(function_exists('sqlab_delete_instance'),
            'sqlab_delete_instance() no está definida en lib.php');

        $result = sqlab_delete_instance(999999999);
        $this->assertFalse(
            $result,
            'sqlab_delete_instance() debe devolver false cuando el ID no existe en la BD.'
        );
    }

    /**
     * UNI-02n — sqlab_delete_instance() devuelve true y elimina el registro para un ID válido.
     *
     * Crea un registro en mdl_sqlab directamente (sin pasar por sqlab_add_instance,
     * que depende de qtype_sqlquestion), llama a sqlab_delete_instance() y verifica:
     * 1. El valor de retorno es true.
     * 2. El registro ya no existe en la BD.
     *
     * Requisito verificado: Eliminación funcional de instancia en sqlab_delete_instance().
     */
    public function test_delete_instance_returns_true_and_deletes_record(): void {
        $this->resetAfterTest();
        global $DB;

        // Crear un registro mínimo en mdl_sqlab directamente.
        $course = $this->getDataGenerator()->create_course();
        $record = new stdClass();
        $record->course         = $course->id;
        $record->name           = 'Test SQLab delete';
        $record->quizid         = 1;
        $record->activitypassword = '';
        $record->timecreated    = time();
        $record->timemodified   = time();
        $sqlabid = $DB->insert_record('sqlab', $record);

        // Verificar que el registro existe antes de borrarlo.
        $this->assertTrue($DB->record_exists('sqlab', ['id' => $sqlabid]),
            'El registro de prueba debe existir antes de llamar a sqlab_delete_instance().');

        // Borrar y verificar el valor de retorno.
        $result = sqlab_delete_instance($sqlabid);
        $this->assertTrue($result,
            'sqlab_delete_instance() debe devolver true para un ID válido.');

        // Verificar que el registro ya no existe.
        $this->assertFalse($DB->record_exists('sqlab', ['id' => $sqlabid]),
            'El registro debe haber sido eliminado de mdl_sqlab tras llamar a sqlab_delete_instance().');
    }

    // -------------------------------------------------------------------------
    // UNI-02o — sqlab_is_question_answered(): lógica de respuestas
    // -------------------------------------------------------------------------

    /**
     * UNI-02o — sqlab_is_question_answered() devuelve false cuando no existe respuesta.
     *
     * La función consulta mdl_sqlab_responses:
     *   $response = $DB->get_record('sqlab_responses', [...]);
     *   return !empty($response) && !empty($response->response);
     * Para un attemptid/questionid/userid inexistentes, debe devolver false.
     *
     * Requisito verificado: Detección correcta de preguntas sin respuesta.
     */
    public function test_is_question_answered_returns_false_when_no_response(): void {
        $this->assertTrue(function_exists('sqlab_is_question_answered'),
            'sqlab_is_question_answered() no está definida en lib.php');

        // IDs ficticios que no existen en la BD → debe devolver false, no lanzar excepción.
        $result = sqlab_is_question_answered(999999, 999999, 999999);
        $this->assertFalse(
            $result,
            'sqlab_is_question_answered() debe devolver false cuando no existe ninguna respuesta ' .
            'para el attemptid/questionid/userid especificados.'
        );
    }

    // -------------------------------------------------------------------------
    // UNI-02i / UNI-02j / UNI-02k — Tablas de base de datos
    // -------------------------------------------------------------------------

    /**
     * UNI-02i — La tabla principal mdl_sqlab existe en la base de datos.
     *
     * Verifica que el fichero db/install.xml se procesó correctamente al instalar
     * el plugin y que la tabla principal mdl_sqlab fue creada.
     *
     * Requisito verificado: Definición correcta de estructura de BD (install.xml).
     */
    public function test_main_table_sqlab_exists(): void {
        global $DB;
        $this->assertTrue(
            $DB->get_manager()->table_exists('sqlab'),
            'La tabla mdl_sqlab no existe. ' .
            'Verifica que db/install.xml define esta tabla y que la instalación fue correcta.'
        );
    }

    /**
     * UNI-02j — La tabla mdl_sqlab_attempts existe en la base de datos.
     *
     * Los intentos de los estudiantes se almacenan en esta tabla.
     * Su ausencia indica un fallo en la definición de db/install.xml.
     *
     * Requisito verificado: Estructura de BD completa (install.xml).
     */
    public function test_table_sqlab_attempts_exists(): void {
        global $DB;
        $this->assertTrue(
            $DB->get_manager()->table_exists('sqlab_attempts'),
            'La tabla mdl_sqlab_attempts no existe. ' .
            'Verifica que db/install.xml define esta tabla.'
        );
    }

    /**
     * UNI-02k — La tabla mdl_sqlab_db_user_credentials existe en la base de datos.
     *
     * Las credenciales de acceso al PostgreSQL externo de cada estudiante se almacenan
     * en esta tabla. Es creada por database_manager::create_user_database() y es
     * fundamental para el funcionamiento del plugin.
     *
     * Requisito verificado: Estructura de BD completa (install.xml).
     */
    public function test_table_sqlab_db_user_credentials_exists(): void {
        global $DB;
        $this->assertTrue(
            $DB->get_manager()->table_exists('sqlab_db_user_credentials'),
            'La tabla mdl_sqlab_db_user_credentials no existe. ' .
            'Esta tabla es necesaria para almacenar las credenciales de BD por usuario.'
        );
    }
}

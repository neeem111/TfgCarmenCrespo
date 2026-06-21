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
 * UNI-03 — Privacy API de mod_sqlab.
 *
 * Suite con tests estructurales y de comportamiento:
 *
 *   UNI-03a [E] Fichero classes/privacy/provider.php existe
 *   UNI-03b [E] Clase mod_sqlab\privacy\provider existe y se puede cargar
 *   UNI-03c [E] La clase implementa una interfaz de privacidad de Moodle
 *   UNI-03d [C] get_metadata() devuelve una Collection NO vacía
 *   UNI-03e [C] Las tablas declaradas en metadatos existen realmente en BD
 *
 * UNI-03d y UNI-03e son tests de comportamiento nuevos. La versión anterior
 * solo comprobaba estructura (fichero, clase, interfaz) sin llamar nunca a la API.
 *
 * === CÓMO EJECUTAR ===
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_privacy_test.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2026 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests de la Privacy API de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_privacy_test extends advanced_testcase {

    // =========================================================================
    // [E] Tests estructurales
    // =========================================================================

    /**
     * UNI-03a [E] — El fichero classes/privacy/provider.php existe.
     *
     * La Privacy API es obligatoria cuando el plugin almacena datos personales.
     * mod_sqlab almacena intentos e historial de consultas SQL de los estudiantes.
     */
    public function test_privacy_provider_file_exists(): void {
        global $CFG;
        $this->assertFileExists(
            $CFG->dirroot . '/mod/sqlab/classes/privacy/provider.php',
            'El fichero classes/privacy/provider.php no existe. ' .
            'La Privacy API es OBLIGATORIA para plugins que almacenan datos de usuario (RGPD).'
        );
    }

    /**
     * UNI-03b [E] — La clase mod_sqlab\privacy\provider existe y se puede cargar.
     */
    public function test_privacy_provider_class_exists(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\privacy\provider'),
            'La clase mod_sqlab\privacy\provider no existe o no se puede cargar. ' .
            'Verificar que el fichero provider.php define correctamente la clase con namespace.'
        );
    }

    /**
     * UNI-03c [E] — La clase provider implementa al menos una interfaz de privacidad de Moodle.
     *
     * Las interfaces aceptadas son:
     *   - core_privacy\local\metadata\provider       (almacena datos)
     *   - core_privacy\local\metadata\null_provider  (no almacena datos)
     *   - core_privacy\local\request\plugin\provider (gestiona solicitudes RGPD)
     */
    public function test_privacy_provider_implements_interface(): void {
        if (!class_exists('\mod_sqlab\privacy\provider')) {
            $this->markTestSkipped('Clase provider no existe — ver UNI-03b.');
        }

        $interfaces = class_implements('\mod_sqlab\privacy\provider');
        $accepted = [
            'core_privacy\local\metadata\provider',
            'core_privacy\local\metadata\null_provider',
            'core_privacy\local\request\plugin\provider',
        ];

        $found = false;
        foreach ($accepted as $iface) {
            if (isset($interfaces[$iface])) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'El provider no implementa ninguna interfaz de privacidad de Moodle. ' .
            'Interfaces implementadas: ' . implode(', ', array_keys($interfaces ?: []))
        );
    }

    // =========================================================================
    // [C] Tests de comportamiento real
    // =========================================================================

    /**
     * UNI-03d [C] — COMPORTAMIENTO: get_metadata() devuelve una Collection no vacía.
     *
     * DIFERENCIA CON LA VERSION ANTERIOR:
     *   La suite anterior solo comprobaba que la clase y la interfaz existían.
     *   Este test LLAMA REALMENTE a get_metadata() y verifica que:
     *     1. Devuelve una instancia de Collection (no null, no bool, no array).
     *     2. La colección contiene al menos un elemento.
     *
     *   Una colección vacía indicaría que el plugin no declara qué datos almacena,
     *   lo que incumpliría el RGPD aunque técnicamente implemente la interfaz.
     *
     * Requisito verificado: Obligatorio — Privacy API con declaración de datos (RGPD).
     */
    public function test_privacy_get_metadata_returns_populated_collection(): void {
        if (!class_exists('\mod_sqlab\privacy\provider')) {
            $this->markTestSkipped('Clase provider no existe — ver UNI-03b.');
        }

        if (!class_exists('\core_privacy\local\metadata\collection')) {
            $this->markTestSkipped('API de privacidad de Moodle no disponible en este entorno.');
        }

        // Llamar realmente a la API de privacidad.
        $collection = new \core_privacy\local\metadata\collection('mod_sqlab');
        $result     = \mod_sqlab\privacy\provider::get_metadata($collection);

        // Verificar tipo de retorno.
        $this->assertInstanceOf(
            \core_privacy\local\metadata\collection::class,
            $result,
            'get_metadata() debe devolver una instancia de collection. ' .
            'Tipo recibido: ' . gettype($result)
        );

        // Verificar que la colección no está vacía.
        $items = $result->get_collection();
        $this->assertNotEmpty(
            $items,
            'FALLO DE COMPORTAMIENTO: la Privacy API declara implementar get_metadata() ' .
            'pero devuelve una colección vacía. El plugin almacena intentos y credenciales ' .
            'de usuario y DEBE declararlos para cumplir el RGPD.'
        );
    }

    /**
     * UNI-03e [C] — COMPORTAMIENTO: las tablas declaradas en metadatos existen en la BD.
     *
     * DIFERENCIA CON LA VERSION ANTERIOR:
     *   No existía en la suite anterior. Este test cruza la declaración de Privacy API
     *   con el esquema real de la BD, detectando inconsistencias del tipo:
     *     - Se declara 'sqlab_attempts' pero la tabla se llama 'sqlab_attempt'.
     *     - Se declara una tabla que nunca se creó.
     *
     * Requisito verificado: Coherencia entre Privacy API y estructura de BD.
     */
    public function test_privacy_declared_tables_exist_in_db(): void {
        global $DB;

        if (!class_exists('\mod_sqlab\privacy\provider')) {
            $this->markTestSkipped('Clase provider no existe — ver UNI-03b.');
        }

        if (!class_exists('\core_privacy\local\metadata\collection')) {
            $this->markTestSkipped('API de privacidad de Moodle no disponible en este entorno.');
        }

        $collection = new \core_privacy\local\metadata\collection('mod_sqlab');
        $result     = \mod_sqlab\privacy\provider::get_metadata($collection);
        $items      = $result->get_collection();

        if (empty($items)) {
            $this->markTestSkipped('Colección de metadatos vacía — ver UNI-03d.');
        }

        $dbmanager    = $DB->get_manager();
        $missingtables = [];

        foreach ($items as $item) {
            // Comprobar solo los elementos que declaran tablas de la BD.
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablename = $item->get_name();
                if (!$dbmanager->table_exists($tablename)) {
                    $missingtables[] = $tablename;
                }
            }
        }

        $this->assertEmpty(
            $missingtables,
            'INCONSISTENCIA: las siguientes tablas están declaradas en la Privacy API ' .
            'pero NO existen en la BD: ' . implode(', ', $missingtables) . '. ' .
            'Verificar que los nombres en get_metadata() coinciden con db/install.xml.'
        );
    }
}

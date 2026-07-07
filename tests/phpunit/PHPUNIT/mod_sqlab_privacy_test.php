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
 * uni-03 — aquí reviso la privacy API de mod_sqlab (la parte de RGPD).
 *
 * suite con tests estructurales y de comportamiento:
 *
 *   uni-03a [e] existe el fichero classes/privacy/provider.php
 *   uni-03b [e] existe la clase mod_sqlab\privacy\provider y se puede cargar
 *   uni-03c [e] la clase implementa alguna interfaz de privacidad de moodle
 *   uni-03d [c] get_metadata() devuelve una collection que NO está vacía
 *   uni-03e [c] las tablas que declara en los metadatos existen de verdad en la BD
 *
 * uni-03d y uni-03e son tests de comportamiento que añadí yo, no estaban en la
 * versión anterior de la suite, que solo miraba estructura (fichero, clase, interfaz)
 * sin llegar a llamar nunca a la API de verdad.
 *
 * === cómo lo ejecuto ===
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_privacy_test.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2026 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tests de la privacy API de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_privacy_test extends advanced_testcase {

    // =========================================================================
    // [E] tests estructurales
    // =========================================================================

    /**
     * uni-03a [e] — miro si existe el fichero classes/privacy/provider.php.
     *
     * la privacy API es obligatoria en cuanto el plugin guarda datos personales,
     * y mod_sqlab guarda intentos e historial de consultas SQL de los alumnos, así que aplica.
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
     * uni-03b [e] — compruebo que la clase mod_sqlab\privacy\provider existe y carga bien.
     */
    public function test_privacy_provider_class_exists(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\privacy\provider'),
            'La clase mod_sqlab\privacy\provider no existe o no se puede cargar. ' .
            'Verificar que el fichero provider.php define correctamente la clase con namespace.'
        );
    }

    /**
     * uni-03c [e] — compruebo que provider implementa al menos una interfaz de privacidad de moodle.
     *
     * las interfaces que acepto son:
     *   - core_privacy\local\metadata\provider       (almacena datos)
     *   - core_privacy\local\metadata\null_provider  (no almacena datos)
     *   - core_privacy\local\request\plugin\provider (gestiona solicitudes RGPD)
     */
    public function test_privacy_provider_implements_interface(): void {
        // si no existe la clase, no tiene sentido seguir (eso ya lo cubre uni-03b).
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
    // [C] tests de comportamiento real
    // =========================================================================

    /**
     * uni-03d [c] — comportamiento: get_metadata() tiene que devolver una collection no vacía.
     *
     * diferencia con la versión anterior:
     *   antes solo se comprobaba que existieran la clase y la interfaz.
     *   aquí LLAMO DE VERDAD a get_metadata() y compruebo que:
     *     1. devuelve una instancia de collection (no null, no bool, no array).
     *     2. la colección tiene al menos un elemento dentro.
     *
     *   si la colección viniera vacía, significaría que el plugin no declara qué datos
     *   guarda, y eso incumple el RGPD aunque técnicamente implemente la interfaz.
     *
     * requisito verificado: obligatorio — privacy API con declaración de datos (RGPD).
     */
    public function test_privacy_get_metadata_returns_populated_collection(): void {
        if (!class_exists('\mod_sqlab\privacy\provider')) {
            $this->markTestSkipped('Clase provider no existe — ver UNI-03b.');
        }

        if (!class_exists('\core_privacy\local\metadata\collection')) {
            $this->markTestSkipped('API de privacidad de Moodle no disponible en este entorno.');
        }

        // act: llamo de verdad a la API de privacidad, no me quedo solo en comprobar que existe.
        $collection = new \core_privacy\local\metadata\collection('mod_sqlab');
        $result     = \mod_sqlab\privacy\provider::get_metadata($collection);

        // assert: primero el tipo de retorno.
        $this->assertInstanceOf(
            \core_privacy\local\metadata\collection::class,
            $result,
            'get_metadata() debe devolver una instancia de collection. ' .
            'Tipo recibido: ' . gettype($result)
        );

        // assert: y que dentro haya contenido, no una colección vacía.
        $items = $result->get_collection();
        $this->assertNotEmpty(
            $items,
            'FALLO DE COMPORTAMIENTO: la Privacy API declara implementar get_metadata() ' .
            'pero devuelve una colección vacía. El plugin almacena intentos y credenciales ' .
            'de usuario y DEBE declararlos para cumplir el RGPD.'
        );
    }

    /**
     * uni-03e [c] — comportamiento: las tablas que declara en metadatos existen en la BD.
     *
     * diferencia con la versión anterior:
     *   este test no existía antes. lo que hago es cruzar lo que declara la privacy API
     *   con el esquema real de la BD, para pillar cosas tipo:
     *     - declara 'sqlab_attempts' pero la tabla en realidad se llama 'sqlab_attempt'.
     *     - declara una tabla que nunca llegó a crearse.
     *
     * requisito verificado: coherencia entre privacy API y estructura de BD.
     */
    public function test_privacy_declared_tables_exist_in_db(): void {
        global $DB;

        if (!class_exists('\mod_sqlab\privacy\provider')) {
            $this->markTestSkipped('Clase provider no existe — ver UNI-03b.');
        }

        if (!class_exists('\core_privacy\local\metadata\collection')) {
            $this->markTestSkipped('API de privacidad de Moodle no disponible en este entorno.');
        }

        // arrange: recupero la misma colección que uso en uni-03d.
        $collection = new \core_privacy\local\metadata\collection('mod_sqlab');
        $result     = \mod_sqlab\privacy\provider::get_metadata($collection);
        $items      = $result->get_collection();

        if (empty($items)) {
            $this->markTestSkipped('Colección de metadatos vacía — ver UNI-03d.');
        }

        $dbmanager    = $DB->get_manager();
        $missingtables = [];

        // recorro solo los elementos que declaran tablas de BD (hay otros tipos de metadato).
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablename = $item->get_name();
                if (!$dbmanager->table_exists($tablename)) {
                    $missingtables[] = $tablename;
                }
            }
        }

        // assert: si hay alguna tabla declarada que no existe en BD, es una inconsistencia.
        $this->assertEmpty(
            $missingtables,
            'INCONSISTENCIA: las siguientes tablas están declaradas en la Privacy API ' .
            'pero NO existen en la BD: ' . implode(', ', $missingtables) . '. ' .
            'Verificar que los nombres en get_metadata() coinciden con db/install.xml.'
        );
    }
}

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
 * UNI-02 — Verifica que mod_sqlab está instalado y tiene version.php válido.
 *
 * @package    mod_sqlab
 * @copyright  2024 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests de verificación de instalación de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_version_test extends advanced_testcase {

    /**
     * UNI-02a — El plugin mod_sqlab está registrado en Moodle.
     */
    public function test_plugin_is_installed(): void {
        $plugininfo = core_plugin_manager::instance()->get_plugin_info('mod_sqlab');
        $this->assertNotNull($plugininfo, 'El plugin mod_sqlab no está instalado');
    }

    /**
     * UNI-02b — El fichero version.php define las variables obligatorias.
     */
    public function test_version_file_has_required_fields(): void {
        global $CFG;

        $versionfile = $CFG->dirroot . '/mod/sqlab/version.php';
        $this->assertFileExists($versionfile, 'version.php no existe');

        // Leer variables definidas en version.php
        $plugin = new stdClass();
        include($versionfile);

        $this->assertNotEmpty($plugin->version,
            'version.php no define $plugin->version');
        $this->assertNotEmpty($plugin->requires,
            'version.php no define $plugin->requires');
        $this->assertNotEmpty($plugin->component,
            'version.php no define $plugin->component');
        $this->assertEquals('mod_sqlab', $plugin->component,
            '$plugin->component no es mod_sqlab (Frankenstyle incorrecto)');
    }

    /**
     * UNI-02c — Se puede crear una instancia del plugin en la BD de test.
     */
    public function test_can_create_sqlab_instance(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sqlab  = $this->getDataGenerator()->create_module('sqlab', [
            'course' => $course->id,
            'name'   => 'Test SQLab',
        ]);

        $this->assertNotEmpty($sqlab->id, 'No se pudo crear la instancia del plugin');
        $this->assertEquals('Test SQLab', $sqlab->name);
    }

    /**
     * UNI-02d — El plugin tiene tabla propia en la BD.
     */
    public function test_database_table_exists(): void {
        global $DB;
        $this->assertTrue(
            $DB->get_manager()->table_exists('sqlab'),
            'La tabla mdl_sqlab no existe en la base de datos'
        );
    }
}

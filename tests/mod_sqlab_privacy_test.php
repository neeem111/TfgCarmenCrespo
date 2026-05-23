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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * UNI-03 - Verifica que mod_sqlab implementa la Privacy API.
 *
 * @package    mod_sqlab
 * @copyright  2024 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

class mod_sqlab_privacy_test extends advanced_testcase {

    /**
     * UNI-03a - El archivo privacy/provider.php existe en mod_sqlab.
     */
    public function test_privacy_provider_file_exists(): void {
        global $CFG;
        $providerfile = $CFG->dirroot . '/mod/sqlab/classes/privacy/provider.php';
        $this->assertFileExists($providerfile,
            'El fichero classes/privacy/provider.php no existe en mod_sqlab');
    }

    /**
     * UNI-03b - La clase provider implementa la interfaz de metadatos de privacidad.
     */
    public function test_privacy_provider_class_exists(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\privacy\provider'),
            'La clase mod_sqlab\privacy\provider no existe'
        );
    }

    /**
     * UNI-03c - La clase provider implementa al menos una interfaz de privacidad de Moodle.
     */
    public function test_privacy_provider_implements_interface(): void {
        $interfaces = class_implements('\mod_sqlab\privacy\provider');
        $moodlePrivacyInterfaces = [
            'core_privacy\local\metadata\provider',
            'core_privacy\local\metadata\null_provider',
            'core_privacy\local\request\plugin\provider',
        ];
        $implementsAny = false;
        foreach ($moodlePrivacyInterfaces as $interface) {
            if (isset($interfaces[$interface])) {
                $implementsAny = true;
                break;
            }
        }
        $this->assertTrue($implementsAny,
            'El provider no implementa ninguna interfaz de privacidad de Moodle');
    }
}

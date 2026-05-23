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
 * UNI-05 - Verifica la estructura de archivos obligatorios de mod_sqlab.
 *
 * Cubre los siguientes requisitos obligatorios de Moodle:
 * - UNI-05a: Estructura de BD (install.xml)
 * - UNI-05b: Gestión de actualizaciones (upgrade.php)
 * - UNI-05c: Paquete de idioma inglés (lang/en/)
 * - UNI-05d: lib.php con funciones add/update/delete_instance
 * - UNI-05e: Funciones de seguridad obligatorias (require_login, required_param, has_capability)
 * - UNI-05f: lang/en/sqlab.php sin sintaxis PHP compleja (heredoc, concatenación)
 *
 * @package    mod_sqlab
 * @copyright  2024 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

class mod_sqlab_structure_test extends advanced_testcase {

    /**
     * UNI-05a — Existe db/install.xml (definición de la estructura de base de datos).
     * Requisito: Definición de la estructura de BD mediante install.xml.
     */
    public function test_install_xml_exists(): void {
        global $CFG;
        $this->assertFileExists(
            $CFG->dirroot . '/mod/sqlab/db/install.xml',
            'El fichero db/install.xml no existe'
        );
    }

    /**
     * UNI-05b — Existe db/upgrade.php (gestión de actualizaciones entre versiones).
     * Requisito: Gestión de actualizaciones mediante upgrade.php.
     */
    public function test_upgrade_php_exists(): void {
        global $CFG;
        $this->assertFileExists(
            $CFG->dirroot . '/mod/sqlab/db/upgrade.php',
            'El fichero db/upgrade.php no existe'
        );
    }

    /**
     * UNI-05c — Existe el fichero de idioma inglés (lang/en/sqlab.php).
     * Requisito: El plugin debe incluir únicamente el paquete de idioma inglés.
     */
    public function test_lang_en_exists(): void {
        global $CFG;
        $this->assertFileExists(
            $CFG->dirroot . '/mod/sqlab/lang/en/sqlab.php',
            'El fichero lang/en/sqlab.php no existe'
        );
    }

    /**
     * UNI-05d — lib.php existe y contiene las tres funciones obligatorias de módulo de actividad.
     * Requisito: Archivos obligatorios con funciones add/update/delete_instance.
     */
    public function test_lib_php_has_required_functions(): void {
        global $CFG;
        $libfile = $CFG->dirroot . '/mod/sqlab/lib.php';
        $this->assertFileExists($libfile, 'El fichero lib.php no existe');

        $content = file_get_contents($libfile);
        $this->assertStringContainsString('sqlab_add_instance',
            $content, 'lib.php no contiene la función sqlab_add_instance');
        $this->assertStringContainsString('sqlab_update_instance',
            $content, 'lib.php no contiene la función sqlab_update_instance');
        $this->assertStringContainsString('sqlab_delete_instance',
            $content, 'lib.php no contiene la función sqlab_delete_instance');
    }

    /**
     * UNI-05e — Los ficheros PHP del plugin usan las funciones de seguridad obligatorias de Moodle.
     * Verifica la presencia de require_login(), required_param() y has_capability()
     * en el conjunto de ficheros PHP del plugin (excluyendo el directorio tests/).
     *
     * Requisito (approval blocker): Incumplimiento de normas de seguridad.
     * Referencia: Sección 3.2.1 Requisitos de seguridad.
     *
     * Nota metodológica: Este test verifica presencia mínima en el código fuente completo
     * del plugin. Un resultado PASSED confirma que al menos se utilizan; un resultado FAILED
     * indica ausencia total, que es un indicador de incumplimiento grave.
     * La auditoría detallada de uso correcto en cada punto de entrada la realiza phpcs (EST-01).
     */
    public function test_security_functions_present(): void {
        global $CFG;
        $plugindir = $CFG->dirroot . '/mod/sqlab/';

        $phpfiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $plugindir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $allcontent = '';
        foreach ($phpfiles as $file) {
            if ($file->getExtension() === 'php'
                    && strpos($file->getPathname(), '/tests/') === false) {
                $allcontent .= file_get_contents($file->getPathname());
            }
        }

        $this->assertStringContainsString('require_login',
            $allcontent,
            'Ningún fichero PHP del plugin usa require_login() — approval blocker de seguridad');
        $this->assertStringContainsString('required_param',
            $allcontent,
            'Ningún fichero PHP del plugin usa required_param() — approval blocker de seguridad');
        $this->assertStringContainsString('has_capability',
            $allcontent,
            'Ningún fichero PHP del plugin comprueba capabilities con has_capability() — approval blocker');
    }

    /**
     * UNI-05f — lang/en/sqlab.php no contiene sintaxis PHP compleja prohibida por Moodle.
     * Verifica que el archivo de idioma no usa heredoc/nowdoc ni concatenaciones en las
     * asignaciones de cadenas, tal como exige el estándar de calidad de Moodle.
     *
     * Requisito: El archivo de idioma debe contener exclusivamente asignaciones simples.
     * Referencia: Sección 3.2.1 Sistemas de cadenas de texto.
     */
    public function test_lang_file_has_no_complex_syntax(): void {
        global $CFG;
        $langfile = $CFG->dirroot . '/mod/sqlab/lang/en/sqlab.php';
        $this->assertFileExists($langfile,
            'El fichero lang/en/sqlab.php no existe; no se puede verificar su sintaxis');

        $content = file_get_contents($langfile);

        // Verificar ausencia de heredoc y nowdoc (<<< operador)
        $this->assertStringNotContainsString('<<<',
            $content,
            'lang/en/sqlab.php contiene sintaxis heredoc o nowdoc, prohibida en archivos de idioma');

        // Verificar ausencia de concatenación en asignaciones de $string[]
        // Patrón: $string['clave'] = 'algo' . 'otra cosa'; o = "algo" . $var;
        $this->assertDoesNotMatchRegularExpression(
            '/\$string\s*\[.*\]\s*=\s*[\'"].*[\'"](\s*\.\s*|\s*\.\s*[\'"])/m',
            $content,
            'lang/en/sqlab.php contiene concatenaciones en asignaciones de cadenas, prohibidas en archivos de idioma'
        );
    }
}

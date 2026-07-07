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
 * uni-05 - aquí compruebo que mod_sqlab tiene la estructura de ficheros obligatoria en moodle.
 *
 * cubro estos requisitos obligatorios:
 * - uni-05a: estructura de BD (install.xml)
 * - uni-05b: gestión de actualizaciones (upgrade.php)
 * - uni-05c: paquete de idioma inglés (lang/en/)
 * - uni-05d: lib.php con las funciones add/update/delete_instance
 * - uni-05e: funciones de seguridad obligatorias (require_login, required_param, has_capability)
 * - uni-05f: lang/en/sqlab.php sin sintaxis PHP rara (heredoc, concatenación)
 *
 * @package    mod_sqlab
 * @copyright  2024 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

class mod_sqlab_structure_test extends advanced_testcase {

    /**
     * uni-05a — miro si existe db/install.xml (define la estructura de la BD).
     * requisito: la estructura de BD tiene que estar definida en install.xml.
     */
    public function test_install_xml_exists(): void {
        global $CFG;
        $this->assertFileExists(
            $CFG->dirroot . '/mod/sqlab/db/install.xml',
            'El fichero db/install.xml no existe'
        );
    }

    /**
     * uni-05b — miro si existe db/upgrade.php (gestiona las actualizaciones entre versiones).
     * requisito: las actualizaciones se gestionan mediante upgrade.php.
     */
    public function test_upgrade_php_exists(): void {
        global $CFG;
        $this->assertFileExists(
            $CFG->dirroot . '/mod/sqlab/db/upgrade.php',
            'El fichero db/upgrade.php no existe'
        );
    }

    /**
     * uni-05c — miro si existe el fichero de idioma inglés (lang/en/sqlab.php).
     * requisito: el plugin solo debe incluir el paquete de idioma inglés.
     */
    public function test_lang_en_exists(): void {
        global $CFG;
        $this->assertFileExists(
            $CFG->dirroot . '/mod/sqlab/lang/en/sqlab.php',
            'El fichero lang/en/sqlab.php no existe'
        );
    }

    /**
     * uni-05d — lib.php existe y tiene las tres funciones obligatorias de todo módulo de actividad.
     * requisito: los ficheros obligatorios deben tener las funciones add/update/delete_instance.
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
     * uni-05e — compruebo que los ficheros PHP del plugin usan las funciones de seguridad
     * obligatorias de moodle: require_login(), required_param() y has_capability(),
     * mirando en todo el código fuente del plugin (menos el directorio tests/).
     *
     * requisito (approval blocker): incumplir las normas de seguridad tumba la aprobación.
     * referencia: sección 3.2.1 requisitos de seguridad.
     *
     * nota mía: este test solo comprueba presencia mínima en el código fuente completo.
     * un PASSED confirma que al menos se usan en algún sitio; un FAILED indica ausencia
     * total, que ya es grave. la auditoría fina de si el uso es correcto en cada punto
     * de entrada la hace phpcs (EST-01), aquí no entro en ese detalle.
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

        // arrange: concateno el contenido de todos los ficheros PHP del plugin, sin tests/.
        $allcontent = '';
        foreach ($phpfiles as $file) {
            if ($file->getExtension() === 'php'
                    && strpos($file->getPathname(), '/tests/') === false) {
                $allcontent .= file_get_contents($file->getPathname());
            }
        }

        // assert: las tres funciones de seguridad tienen que aparecer en algún sitio.
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
     * uni-05f — compruebo que lang/en/sqlab.php no usa sintaxis PHP compleja, que está
     * prohibida por moodle en los ficheros de idioma: nada de heredoc/nowdoc ni
     * concatenaciones en las asignaciones de cadenas.
     *
     * requisito: el archivo de idioma solo puede tener asignaciones simples.
     * referencia: sección 3.2.1 sistemas de cadenas de texto.
     */
    public function test_lang_file_has_no_complex_syntax(): void {
        global $CFG;
        $langfile = $CFG->dirroot . '/mod/sqlab/lang/en/sqlab.php';
        $this->assertFileExists($langfile,
            'El fichero lang/en/sqlab.php no existe; no se puede verificar su sintaxis');

        $content = file_get_contents($langfile);

        // compruebo que no hay heredoc ni nowdoc (el operador <<<).
        $this->assertStringNotContainsString('<<<',
            $content,
            'lang/en/sqlab.php contiene sintaxis heredoc o nowdoc, prohibida en archivos de idioma');

        // ahora compruebo que no hay concatenaciones en las asignaciones de $string[].
        // patrón que busco: $string['clave'] = 'algo' . 'otra'; o = "algo" . $var;
        // uso [^\']* y [^\"]* para no liarme con cadenas que llevan comillas dentro
        // (ej: $string['beforefinish'] que contiene "Evaluate Code". ).
        // el operador de concatenación . solo cuenta si aparece FUERA de las comillas del valor.
        $this->assertDoesNotMatchRegularExpression(
            '/\$string\s*\[[^\]]+\]\s*=\s*(?:\'[^\']*\'|"[^"]*")\s*\./m',
            $content,
            'lang/en/sqlab.php contiene concatenaciones en asignaciones de cadenas, prohibidas en archivos de idioma'
        );
    }
}

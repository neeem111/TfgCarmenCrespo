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
 * uni-04 — aquí pruebo la backup and restore API de mod_sqlab.
 *
 * ojo — approval blocker:
 *   la backup & restore API es OBLIGATORIA en cualquier módulo de actividad.
 *   si falta, automáticamente no se puede publicar en el directorio oficial de moodle,
 *   da igual lo bien hecho que esté el resto del plugin.
 *
 * suite con tests de existencia y de comportamiento:
 *
 *   uni-04a [e] existe el directorio backup/moodle2/
 *   uni-04b [e] existe el fichero backup_sqlab_activity_task.class.php
 *   uni-04c [e] existe el fichero restore_sqlab_activity_task.class.php
 *   uni-04d [c] la clase de backup hereda de backup_activity_task
 *   uni-04e [c] la clase de restauración hereda de restore_activity_task
 *
 * uni-04d y uni-04e son tests de comportamiento que añadí yo. antes la suite
 * solo miraba si existían los ficheros, sin comprobar que la implementación
 * fuera correcta (que heredara bien de las clases base de moodle).
 *
 * resultado que me da con la versión actual del plugin:
 *   uni-04a FAILED — no existe el directorio (approval blocker)
 *   uni-04b FAILED — no existe el fichero (approval blocker)
 *   uni-04c FAILED — no existe el fichero (approval blocker)
 *   uni-04d SKIPPED — no se puede comprobar sin el fichero
 *   uni-04e SKIPPED — no se puede comprobar sin el fichero
 *
 * === cómo lo ejecuto ===
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_backup_test.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2026 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tests de la backup and restore API de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_backup_test extends advanced_testcase {

    // =========================================================================
    // [E] tests de existencia — approval blockers
    // =========================================================================

    /**
     * uni-04a [e] — approval blocker: tiene que existir el directorio backup/moodle2/.
     *
     * si esto falla, el plugin NO se puede publicar en el directorio oficial de moodle.
     * lo que hay que hacer: crear el directorio y los ficheros de implementación.
     */
    public function test_backup_directory_exists(): void {
        global $CFG;
        $backupdir = $CFG->dirroot . '/mod/sqlab/backup/moodle2';

        if (!is_dir($backupdir)) {
            $this->fail(
                'APPROVAL BLOCKER: El directorio backup/moodle2/ NO existe en mod_sqlab. ' .
                'La Backup & Restore API es OBLIGATORIA para módulos de actividad. ' .
                'Crear la estructura: mod/sqlab/backup/moodle2/ con los ficheros ' .
                'backup_sqlab_activity_task.class.php y restore_sqlab_activity_task.class.php.'
            );
        }

        $this->assertDirectoryExists($backupdir);
    }

    /**
     * uni-04b [e] — approval blocker: tiene que existir el fichero de la tarea de backup.
     */
    public function test_backup_task_file_exists(): void {
        global $CFG;
        $file = $CFG->dirroot . '/mod/sqlab/backup/moodle2/backup_sqlab_activity_task.class.php';

        if (!file_exists($file)) {
            $this->fail(
                'APPROVAL BLOCKER: backup_sqlab_activity_task.class.php NO existe. ' .
                'Este fichero implementa la lógica de copia de seguridad de la actividad ' .
                'y es imprescindible para que los profesores puedan hacer backup del curso.'
            );
        }

        $this->assertFileExists($file);
    }

    /**
     * uni-04c [e] — approval blocker: tiene que existir el fichero de la tarea de restauración.
     */
    public function test_restore_task_file_exists(): void {
        global $CFG;
        $file = $CFG->dirroot . '/mod/sqlab/backup/moodle2/restore_sqlab_activity_task.class.php';

        if (!file_exists($file)) {
            $this->fail(
                'APPROVAL BLOCKER: restore_sqlab_activity_task.class.php NO existe. ' .
                'Este fichero implementa la restauración de la actividad desde una copia ' .
                'de seguridad y es imprescindible para la portabilidad del curso.'
            );
        }

        $this->assertFileExists($file);
    }

    // =========================================================================
    // [C] tests de comportamiento — que la herencia esté bien hecha
    // =========================================================================

    /**
     * uni-04d [c] — comportamiento: la clase de backup tiene que heredar de backup_activity_task.
     *
     * diferencia con la versión anterior:
     *   antes solo se comprobaba que el fichero existiera.
     *   aquí compruebo que la implementación está bien hecha de verdad:
     *     1. la clase backup_sqlab_activity_task está definida en el fichero.
     *     2. extiende backup_activity_task (para integrarse con el sistema de moodle).
     *     3. implementa el método obligatorio define_my_steps().
     *
     *   un fichero que existe pero hereda mal daría errores en tiempo de ejecución
     *   en cuanto el usuario intentara hacer un backup del curso.
     *
     * si el fichero no existe (uni-04b FAILED), este test se marca SKIPPED.
     */
    public function test_backup_task_extends_base_class(): void {
        global $CFG;
        $file = $CFG->dirroot . '/mod/sqlab/backup/moodle2/backup_sqlab_activity_task.class.php';

        if (!file_exists($file)) {
            $this->markTestSkipped(
                'backup_sqlab_activity_task.class.php no existe — ver UNI-04b (APPROVAL BLOCKER).'
            );
        }

        // arrange: cargo la clase base de moodle y la implementación del plugin.
        require_once($CFG->dirroot . '/backup/moodle2/backup_activity_task.class.php');
        require_once($file);

        // assert 1: que la clase esté definida con el nombre exacto que espera moodle.
        $this->assertTrue(
            class_exists('backup_sqlab_activity_task'),
            'El fichero backup_sqlab_activity_task.class.php no define la clase esperada. ' .
            'La clase debe llamarse exactamente "backup_sqlab_activity_task".'
        );

        // assert 2: que herede de la clase base de moodle (esto es lo importante).
        $this->assertTrue(
            is_subclass_of('backup_sqlab_activity_task', 'backup_activity_task'),
            'FALLO DE COMPORTAMIENTO: backup_sqlab_activity_task NO hereda de backup_activity_task. ' .
            'Sin esta herencia, Moodle no puede integrar la actividad en su sistema de backup.'
        );

        // assert 3: que tenga el método obligatorio implementado.
        $this->assertTrue(
            method_exists('backup_sqlab_activity_task', 'define_my_steps'),
            'backup_sqlab_activity_task no implementa define_my_steps(). ' .
            'Este método es obligatorio para definir qué datos se incluyen en el backup.'
        );
    }

    /**
     * uni-04e [c] — comportamiento: la clase de restauración tiene que heredar de restore_activity_task.
     *
     * diferencia con la versión anterior:
     *   este test no existía antes. hace lo mismo que uni-04d pero para restauración,
     *   comprobando la herencia correcta.
     *
     * si el fichero no existe (uni-04c FAILED), este test se marca SKIPPED.
     */
    public function test_restore_task_extends_base_class(): void {
        global $CFG;
        $file = $CFG->dirroot . '/mod/sqlab/backup/moodle2/restore_sqlab_activity_task.class.php';

        if (!file_exists($file)) {
            $this->markTestSkipped(
                'restore_sqlab_activity_task.class.php no existe — ver UNI-04c (APPROVAL BLOCKER).'
            );
        }

        // arrange: cargo la clase base y la del plugin.
        require_once($CFG->dirroot . '/backup/moodle2/restore_activity_task.class.php');
        require_once($file);

        // assert: la clase existe con el nombre esperado.
        $this->assertTrue(
            class_exists('restore_sqlab_activity_task'),
            'El fichero no define la clase "restore_sqlab_activity_task".'
        );

        // assert: y hereda de la clase base de moodle.
        $this->assertTrue(
            is_subclass_of('restore_sqlab_activity_task', 'restore_activity_task'),
            'FALLO DE COMPORTAMIENTO: restore_sqlab_activity_task NO hereda de restore_activity_task. ' .
            'Sin esta herencia, Moodle no puede integrar la actividad en su sistema de restauración.'
        );
    }
}

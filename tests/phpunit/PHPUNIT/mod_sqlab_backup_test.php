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
 * UNI-04 — Backup and Restore API de mod_sqlab.
 *
 * ATENCIÓN — APPROVAL BLOCKER:
 *   La Backup & Restore API es OBLIGATORIA para módulos de actividad.
 *   Su ausencia impide automáticamente la publicación en el directorio
 *   oficial de Moodle, independientemente de la calidad del resto del código.
 *
 * Suite con tests de existencia y de comportamiento:
 *
 *   UNI-04a [E] Directorio backup/moodle2/ existe
 *   UNI-04b [E] Fichero backup_sqlab_activity_task.class.php existe
 *   UNI-04c [E] Fichero restore_sqlab_activity_task.class.php existe
 *   UNI-04d [C] Clase de backup hereda de backup_activity_task
 *   UNI-04e [C] Clase de restauración hereda de restore_activity_task
 *
 * UNI-04d y UNI-04e son tests de comportamiento nuevos. La versión anterior
 * solo comprobaba existencia de ficheros sin verificar que la implementación
 * fuera correcta (herencia de clases base de Moodle).
 *
 * RESULTADO ESPERADO CON LA VERSION ACTUAL DEL PLUGIN:
 *   UNI-04a FAILED — directorio no existe (approval blocker)
 *   UNI-04b FAILED — fichero no existe (approval blocker)
 *   UNI-04c FAILED — fichero no existe (approval blocker)
 *   UNI-04d SKIPPED — no se puede verificar sin el fichero
 *   UNI-04e SKIPPED — no se puede verificar sin el fichero
 *
 * === CÓMO EJECUTAR ===
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_backup_test.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2026 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests de la Backup and Restore API de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_backup_test extends advanced_testcase {

    // =========================================================================
    // [E] Tests de existencia — APPROVAL BLOCKERS
    // =========================================================================

    /**
     * UNI-04a [E] — APPROVAL BLOCKER: El directorio backup/moodle2/ debe existir.
     *
     * Si falla: el plugin NO puede ser publicado en el directorio oficial de Moodle.
     * Acción requerida: crear el directorio y los ficheros de implementación.
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
     * UNI-04b [E] — APPROVAL BLOCKER: El fichero de tarea de backup debe existir.
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
     * UNI-04c [E] — APPROVAL BLOCKER: El fichero de tarea de restauración debe existir.
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
    // [C] Tests de comportamiento — herencia correcta de clases base
    // =========================================================================

    /**
     * UNI-04d [C] — COMPORTAMIENTO: La clase de backup hereda de backup_activity_task.
     *
     * DIFERENCIA CON LA VERSION ANTERIOR:
     *   La suite anterior solo comprobaba que el fichero existía.
     *   Este test verifica que la implementación es correcta:
     *     1. La clase backup_sqlab_activity_task está definida en el fichero.
     *     2. Extiende backup_activity_task (integración con el sistema de Moodle).
     *     3. Implementa el método obligatorio define_my_steps().
     *
     *   Un fichero que existe pero no hereda correctamente provocaría errores
     *   en tiempo de ejecución cuando el usuario realice un backup del curso.
     *
     * Si el fichero no existe (UNI-04b FAILED), este test se marca SKIPPED.
     */
    public function test_backup_task_extends_base_class(): void {
        global $CFG;
        $file = $CFG->dirroot . '/mod/sqlab/backup/moodle2/backup_sqlab_activity_task.class.php';

        if (!file_exists($file)) {
            $this->markTestSkipped(
                'backup_sqlab_activity_task.class.php no existe — ver UNI-04b (APPROVAL BLOCKER).'
            );
        }

        // Cargar la clase base de Moodle y la implementación del plugin.
        require_once($CFG->dirroot . '/backup/moodle2/backup_activity_task.class.php');
        require_once($file);

        // Verificar que la clase está definida en el fichero.
        $this->assertTrue(
            class_exists('backup_sqlab_activity_task'),
            'El fichero backup_sqlab_activity_task.class.php no define la clase esperada. ' .
            'La clase debe llamarse exactamente "backup_sqlab_activity_task".'
        );

        // Verificar herencia de la clase base de Moodle.
        $this->assertTrue(
            is_subclass_of('backup_sqlab_activity_task', 'backup_activity_task'),
            'FALLO DE COMPORTAMIENTO: backup_sqlab_activity_task NO hereda de backup_activity_task. ' .
            'Sin esta herencia, Moodle no puede integrar la actividad en su sistema de backup.'
        );

        // Verificar que implementa el método obligatorio.
        $this->assertTrue(
            method_exists('backup_sqlab_activity_task', 'define_my_steps'),
            'backup_sqlab_activity_task no implementa define_my_steps(). ' .
            'Este método es obligatorio para definir qué datos se incluyen en el backup.'
        );
    }

    /**
     * UNI-04e [C] — COMPORTAMIENTO: La clase de restauración hereda de restore_activity_task.
     *
     * DIFERENCIA CON LA VERSION ANTERIOR:
     *   No existía en la suite anterior. Verifica herencia correcta de la clase
     *   de restauración, análogamente a UNI-04d para el backup.
     *
     * Si el fichero no existe (UNI-04c FAILED), este test se marca SKIPPED.
     */
    public function test_restore_task_extends_base_class(): void {
        global $CFG;
        $file = $CFG->dirroot . '/mod/sqlab/backup/moodle2/restore_sqlab_activity_task.class.php';

        if (!file_exists($file)) {
            $this->markTestSkipped(
                'restore_sqlab_activity_task.class.php no existe — ver UNI-04c (APPROVAL BLOCKER).'
            );
        }

        require_once($CFG->dirroot . '/backup/moodle2/restore_activity_task.class.php');
        require_once($file);

        $this->assertTrue(
            class_exists('restore_sqlab_activity_task'),
            'El fichero no define la clase "restore_sqlab_activity_task".'
        );

        $this->assertTrue(
            is_subclass_of('restore_sqlab_activity_task', 'restore_activity_task'),
            'FALLO DE COMPORTAMIENTO: restore_sqlab_activity_task NO hereda de restore_activity_task. ' .
            'Sin esta herencia, Moodle no puede integrar la actividad en su sistema de restauración.'
        );
    }
}

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
 * UNI-04 - Verifica que mod_sqlab implementa la Backup and Restore API.
 * Requisito obligatorio para módulos de actividad (approval blocker si falta).
 *
 * @package    mod_sqlab
 * @copyright  2024 Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

class mod_sqlab_backup_test extends advanced_testcase {

    /**
     * UNI-04a - Existe el directorio backup/moodle2/.
     */
    public function test_backup_directory_exists(): void {
        global $CFG;
        $backupdir = $CFG->dirroot . '/mod/sqlab/backup/moodle2';
        $this->assertDirectoryExists($backupdir,
            'El directorio backup/moodle2 no existe en mod_sqlab');
    }

    /**
     * UNI-04b - Existe el fichero de tarea de backup.
     */
    public function test_backup_task_file_exists(): void {
        global $CFG;
        $backupfile = $CFG->dirroot .
            '/mod/sqlab/backup/moodle2/backup_sqlab_activity_task.class.php';
        $this->assertFileExists($backupfile,
            'El fichero backup_sqlab_activity_task.class.php no existe');
    }

    /**
     * UNI-04c - Existe el fichero de tarea de restauración.
     */
    public function test_restore_task_file_exists(): void {
        global $CFG;
        $restorefile = $CFG->dirroot .
            '/mod/sqlab/backup/moodle2/restore_sqlab_activity_task.class.php';
        $this->assertFileExists($restorefile,
            'El fichero restore_sqlab_activity_task.class.php no existe');
    }
}

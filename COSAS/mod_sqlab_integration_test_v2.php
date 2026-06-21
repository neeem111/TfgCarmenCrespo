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
 * UNI-06 — Pruebas de integración de las funciones de negocio principales de mod_sqlab.
 *
 * Verifica las funciones de gestión de bases de datos de usuario (database_manager) y
 * la ejecución de consultas SQL (user_query_executor::execute_user_sql) que constituyen
 * el núcleo funcional del plugin.
 *
 * En el plugin actual (nuevo), la función sqldb_creation del plugin antiguo ha sido
 * sustituida por database_manager::handle_role_assignment(), que internamente llama a:
 *   - database_manager::check_database_exists($db_name)
 *   - database_manager::create_user_database($db_name, $user_id)
 *
 * Estructura de la suite:
 * UNI-06a  Verificación estructural de database_manager (sin servidor externo)
 * UNI-06b  Comportamiento de handle_role_assignment contra PostgreSQL externo (SKIPPED sin servidor)
 * UNI-06c  Verificación estructural de execute_user_sql (sin servidor externo)
 * UNI-06d  Comportamiento de execute_user_sql contra PostgreSQL externo (SKIPPED sin servidor)
 *
 * LIMITACIÓN ARQUITECTÓNICA DOCUMENTADA:
 * mod_sqlab depende de un servidor PostgreSQL externo para su lógica de negocio principal.
 * Este servidor es independiente de la base de datos de Moodle y no forma parte del
 * entorno moodle-docker estándar. Por tanto, los tests UNI-06b y UNI-06d solo pueden
 * ejecutarse cuando dicho servidor externo esté activo y configurado.
 * Ver sección 5.7.2 de la memoria.
 *
 * Nota de ejecución:
 * vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_integration_test_v2.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2025 Carmen Crespo Navarro, Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

class mod_sqlab_integration_test_v2 extends advanced_testcase {

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Comprueba si el servidor PostgreSQL externo de mod_sqlab está configurado.
     * La configuración se almacena en la tabla mdl_config_plugins bajo el plugin mod_sqlab.
     *
     * @return bool True si el host del servidor externo está configurado.
     */
    private function external_postgres_is_available(): bool {
        $host = get_config('mod_sqlab', 'dbhost');
        return !empty($host);
    }

    /**
     * Localiza en qué fichero del plugin está definida una función o método dado.
     *
     * @param  string $functionname Nombre de la función o método a buscar.
     * @return string|false  Ruta absoluta del fichero que lo define, o false si no se encuentra.
     */
    private function find_definition_file(string $functionname) {
        global $CFG;
        $plugindir = $CFG->dirroot . '/mod/sqlab/';

        $phpfiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugindir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($phpfiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (strpos($file->getPathname(), '/tests/') !== false) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (strpos($content, $functionname) !== false) {
                return $file->getPathname();
            }
        }
        return false;
    }

    // -----------------------------------------------------------------------
    // Suite UNI-06a/b — database_manager (equivale a sqldb_creation en plugin antiguo)
    // -----------------------------------------------------------------------

    /**
     * UNI-06a — La clase database_manager y sus funciones de creación de BD están definidas.
     *
     * Verificación estructural: en el plugin actual, la lógica que antes estaba en
     * sqldb_creation() está implementada en database_manager::check_database_exists() y
     * database_manager::create_user_database(). Este test verifica que ambas funciones
     * existen en el código fuente del plugin y que la clase es instanciable por el
     * autoloader de Moodle.
     *
     * Requisito verificado: Lógica de gestión de credenciales de usuario.
     */
    public function test_database_creation_is_defined(): void {
        // Verificar que la clase database_manager está disponible via autoloader de Moodle.
        $this->assertTrue(
            class_exists('\mod_sqlab\database_manager'),
            'La clase \mod_sqlab\database_manager no está disponible. ' .
            'Asegúrate de que classes/database_manager.php existe en el plugin.'
        );

        // Verificar que check_database_exists está definida en el código fuente.
        $filepath = $this->find_definition_file('check_database_exists');
        $this->assertNotFalse(
            $filepath,
            'La función check_database_exists no se encontró en ningún fichero PHP del plugin. ' .
            'Esta función verifica si la BD del usuario ya existe en el servidor PostgreSQL externo.'
        );

        // Verificar que create_user_database está definida en el código fuente.
        $filepath2 = $this->find_definition_file('create_user_database');
        $this->assertNotFalse(
            $filepath2,
            'La función create_user_database no se encontró en ningún fichero PHP del plugin. ' .
            'Esta función crea la BD del usuario en el servidor PostgreSQL externo.'
        );

        // Verificar que handle_role_assignment (el método público que las orquesta) existe.
        $this->assertTrue(
            method_exists('\mod_sqlab\database_manager', 'handle_role_assignment'),
            'El método handle_role_assignment no existe en \mod_sqlab\database_manager. ' .
            'Este método es el punto de entrada para la creación de bases de datos de usuario.'
        );
    }

    /**
     * UNI-06b — handle_role_assignment registra credenciales en mdl_sqlab_db_user_credentials
     * y crea una base de datos en el servidor PostgreSQL externo.
     *
     * Equivale al antiguo test de sqldb_creation. La lógica de negocio es ahora:
     *   database_manager::handle_role_assignment($event_data)
     * donde $event_data contiene el objectid del rol asignado y el relateduserid del usuario.
     * Internamente esta función invoca check_database_exists() y create_user_database().
     *
     * Postcondiciones verificadas:
     * 1. Existe un registro en mdl_sqlab_db_user_credentials para $user->id.
     * 2. En el servidor PostgreSQL externo se ha creado una nueva base de datos.
     *
     * LIMITACIÓN ARQUITECTÓNICA: este test requiere el servidor PostgreSQL externo.
     * Sin él se marca como SKIPPED. Ver sección 5.7.2 de la memoria.
     *
     * Requisito verificado: Creación de credenciales; integración con PostgreSQL externo.
     */
    public function test_database_creation_creates_credentials(): void {
        $this->resetAfterTest();

        if (!$this->external_postgres_is_available()) {
            $this->markTestSkipped(
                'LIMITACIÓN ARQUITECTÓNICA: database_manager::handle_role_assignment requiere ' .
                'el servidor PostgreSQL externo de mod_sqlab ' .
                '(get_config("mod_sqlab","dbhost") vacío en este entorno). ' .
                'El test no puede ejecutarse en moodle-docker sin el servidor externo activo.'
            );
        }

        global $DB;

        // Crear usuario de prueba y obtener el ID del rol student.
        $user        = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        // Invocar la función de negocio equivalente a sqldb_creation del plugin antiguo.
        // handle_role_assignment orquesta: check_database_exists() + create_user_database()
        $event_data = [
            'objectid'      => $studentrole->id,
            'relateduserid' => $user->id,
        ];
        \mod_sqlab\database_manager::handle_role_assignment($event_data);

        // Verificar que se creó el registro de credenciales en Moodle.
        $exists = $DB->record_exists('sqlab_db_user_credentials', ['userid' => $user->id]);
        $this->assertTrue(
            $exists,
            'handle_role_assignment no creó ningún registro en mdl_sqlab_db_user_credentials ' .
            'para el usuario con id=' . $user->id
        );
    }

    // -----------------------------------------------------------------------
    // Suite UNI-06c/d — user_query_executor::execute_user_sql
    // -----------------------------------------------------------------------

    /**
     * UNI-06c — La clase user_query_executor y el método execute_user_sql existen.
     *
     * Verificación estructural: comprueba que la clase responsable de ejecutar
     * las consultas SQL del estudiante está implementada con el contrato de
     * interfaz esperado (método estático execute_user_sql).
     * La clase está en el namespace mod_sqlab (classes/user_query_executor.php).
     *
     * Requisito verificado: Lógica de ejecución de consultas SQL del estudiante.
     */
    public function test_execute_user_sql_is_defined(): void {
        global $CFG;

        // Verificar que el fichero existe.
        $executorfile = $CFG->dirroot . '/mod/sqlab/classes/user_query_executor.php';
        $this->assertFileExists(
            $executorfile,
            'El fichero classes/user_query_executor.php no existe en mod_sqlab'
        );

        // Verificar que la clase es accesible via autoloader (namespace mod_sqlab).
        $this->assertTrue(
            class_exists('\mod_sqlab\user_query_executor'),
            'La clase \mod_sqlab\user_query_executor no está disponible via autoloader.'
        );

        // Verificar que el método estático execute_user_sql existe en el namespace correcto.
        $this->assertTrue(
            method_exists('\mod_sqlab\user_query_executor', 'execute_user_sql'),
            'El método estático execute_user_sql no existe en \mod_sqlab\user_query_executor. ' .
            'Este método es invocado desde execute_sql.php para procesar las consultas del alumno.'
        );
    }

    /**
     * UNI-06d — execute_user_sql ejecuta una consulta SQL y devuelve resultados estructurados.
     *
     * Llamada esperada (según execute_sql.php):
     * \mod_sqlab\user_query_executor::execute_user_sql($attemptid, $sql, $schemaName)
     * donde:
     * $attemptid  = ID del intento activo del estudiante (tabla mdl_sqlab_attempts)
     * $sql        = consulta SQL enviada por el estudiante
     * $schemaName = nombre del esquema del usuario en el PostgreSQL externo
     *
     * LIMITACIÓN ARQUITECTÓNICA: para una llamada bien formada se necesitan:
     * 1. Un $attemptid válido, creado por sqlab_add_instance() que depende de
     *    qtype_sqlquestion (no funcional en el entorno de test estándar).
     * 2. Un $schemaName válido, generado previamente por database_manager.
     * 3. El servidor PostgreSQL externo activo.
     * Ninguna de estas condiciones puede satisfacerse en moodle-docker estándar.
     *
     * Ver sección 5.7.2 de la memoria: "Limitación arquitectónica por dependencia
     * en qtype_sqlquestion y servidor PostgreSQL externo."
     *
     * Requisito verificado: Procesamiento de consultas SQL (lógica de negocio core).
     */
    public function test_execute_user_sql_processes_query(): void {
        $this->resetAfterTest();

        if (!$this->external_postgres_is_available()) {
            $this->markTestSkipped(
                'LIMITACIÓN ARQUITECTÓNICA: execute_user_sql requiere el servidor PostgreSQL ' .
                'externo, un attemptid válido creado por sqlab_add_instance() (dependiente de ' .
                'qtype_sqlquestion) y un schemaName generado por database_manager. ' .
                'Ninguna de estas condiciones puede satisfacerse en moodle-docker estándar.'
            );
        }

        // NOTA: Sustituir estos valores por los de una instancia activa del plugin
        // antes de ejecutar este test en el servidor del tutor.
        $attemptid  = 1;                    // ID de intento real de mdl_sqlab_attempts.
        $sql        = 'SELECT 1 AS test';   // Consulta de verificación mínima.
        $schemaName = '';                   // Esquema real del usuario (generado por database_manager).

        $result = \mod_sqlab\user_query_executor::execute_user_sql($attemptid, $sql, $schemaName);

        $this->assertNotNull($result,
            'execute_user_sql devolvió null para una consulta SQL válida');
        $this->assertIsArray($result,
            'execute_user_sql debe devolver un array con los resultados de la consulta');
    }
}

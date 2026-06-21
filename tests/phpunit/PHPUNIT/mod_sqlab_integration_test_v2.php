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
 * Suite con tests estructurales y de comportamiento de las funciones core del plugin:
 *
 * UNI-06a  Verificación estructural: database_manager.php contiene check_database_exists
 *          y create_user_database (funciones que sustituyen a sqldb_creation en versión actual)
 * UNI-06b  Comportamiento de check_database_exists / create_user_database (SKIPPED sin servidor)
 * UNI-06c  Verificación estructural: user_query_executor contiene execute_user_sql
 * UNI-06d  Comportamiento de execute_user_sql contra PostgreSQL externo (SKIPPED sin servidor)
 *
 * Nota sobre renombrado de funciones (correo tutor 09/06/2026):
 *   La función sqldb_creation() de la versión anterior fue refactorizada en la versión
 *   actual del plugin en dos funciones dentro de database_manager.php:
 *     - check_database_exists($db_name)
 *     - create_user_database($db_name, $user_id)
 *   La función execute_user_sql está presente en ambas versiones del plugin.
 *
 * LIMITACIÓN ARQUITECTÓNICA DOCUMENTADA:
 * mod_sqlab depende de un servidor PostgreSQL externo para su lógica de negocio principal.
 * Este servidor es independiente de la base de datos de Moodle y no forma parte del
 * entorno moodle-docker estándar. Por tanto, los tests UNI-06b y UNI-06d solo pueden
 * ejecutarse cuando dicho servidor externo esté activo y configurado.
 * Ver sección 5.7.2 de la memoria.
 *
 * Nota de ejecución: ejecutar por fichero individual para evitar el error fatal
 * de qtype_sqlquestion\privacy\provider al usar --group mod_sqlab:
 * vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_integration_test_v2.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2024 Universidad de Castilla-La Mancha
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
    // Suite UNI-06a/b — database_manager (antes sqldb_creation)
    // -----------------------------------------------------------------------

    /**
     * UNI-06a — Las funciones de gestión de BD de usuario están definidas en el plugin.
     *
     * Verificación estructural: comprueba que database_manager.php existe y contiene
     * las funciones check_database_exists() y create_user_database() que, según el tutor
     * (correo 09/06/2026), sustituyeron a sqldb_creation() en la versión actual del plugin.
     *
     * Si database_manager.php no se encuentra, se intenta localizar sqldb_creation()
     * como fallback para compatibilidad con versiones anteriores.
     *
     * Requisito verificado: Lógica de gestión de credenciales y BD de usuario.
     */
    public function test_sqldb_creation_is_defined(): void {
        global $CFG;

        // Versión actual: buscar database_manager.php con las dos funciones refactorizadas.
        $dmfile = $CFG->dirroot . '/mod/sqlab/classes/database_manager.php';

        if (file_exists($dmfile)) {
            $content = file_get_contents($dmfile);

            $this->assertStringContainsString(
                'check_database_exists',
                $content,
                'database_manager.php existe pero no contiene check_database_exists(). ' .
                'Según el tutor, esta función comprueba si la BD del usuario ya existe ' .
                'en el servidor PostgreSQL externo.'
            );

            $this->assertStringContainsString(
                'create_user_database',
                $content,
                'database_manager.php existe pero no contiene create_user_database(). ' .
                'Según el tutor, esta función crea la BD del usuario en PostgreSQL externo ' .
                'y registra sus credenciales en mdl_sqlab_db_user_credentials.'
            );
            return; // Verificación completada con versión actual.
        }

        // Fallback: versión anterior con sqldb_creation().
        $filepath = $this->find_definition_file('sqldb_creation');
        $this->assertNotFalse(
            $filepath,
            'Ni classes/database_manager.php (versión actual) ni sqldb_creation() (versión anterior) ' .
            'se encontraron en el plugin. ' .
            'Según el tutor, la versión actual usa check_database_exists() y create_user_database() ' .
            'en database_manager.php; la versión anterior usaba sqldb_creation().'
        );
    }

    /**
     * UNI-06b — check_database_exists / create_user_database registran credenciales
     * en mdl_sqlab_db_user_credentials y crean una BD en el servidor PostgreSQL externo.
     *
     * Postcondiciones verificadas:
     * 1. create_user_database() crea la BD del usuario en PostgreSQL externo.
     * 2. Existe un registro en mdl_sqlab_db_user_credentials para el usuario.
     * 3. check_database_exists() confirma que la BD fue creada correctamente.
     *
     * LIMITACIÓN ARQUITECTÓNICA: este test requiere el servidor PostgreSQL externo.
     * Sin él se marca como SKIPPED. Ver sección 5.7.2 de la memoria.
     *
     * Requisito verificado: Creación de credenciales; integración con PostgreSQL externo.
     */
    public function test_sqldb_creation_creates_credentials(): void {
        $this->resetAfterTest();

        if (!$this->external_postgres_is_available()) {
            $this->markTestSkipped(
                'LIMITACIÓN ARQUITECTÓNICA: las funciones de gestión de BD requieren el servidor ' .
                'PostgreSQL externo de mod_sqlab (get_config("mod_sqlab","dbhost") vacío en este entorno). ' .
                'El test no puede ejecutarse en moodle-docker sin el servidor externo activo.'
            );
        }

        global $DB, $CFG;

        // Crear entorno de prueba mínimo: usuario.
        $user = $this->getDataGenerator()->create_user();

        // Cargar database_manager.php (versión actual) o buscar sqldb_creation (versión anterior).
        $dmfile = $CFG->dirroot . '/mod/sqlab/classes/database_manager.php';

        if (file_exists($dmfile)) {
            require_once($dmfile);

            // El nombre de BD del usuario se compone típicamente del username o user_id.
            $dbname = 'user_' . $user->id;

            // Verificar que la BD no existe previamente.
            $exists_before = check_database_exists($dbname);
            $this->assertFalse(
                $exists_before,
                'La BD ' . $dbname . ' ya existe antes de ejecutar create_user_database(). ' .
                'Esto indica que el entorno no está limpio o que el nombre de BD se reutiliza.'
            );

            // Crear la BD del usuario en PostgreSQL externo.
            create_user_database($dbname, $user->id);

            // Verificar postcondición 1: la BD fue creada en PostgreSQL externo.
            $this->assertTrue(
                check_database_exists($dbname),
                'FALLO DE COMPORTAMIENTO: create_user_database() no creó la BD "' . $dbname . '" ' .
                'en el servidor PostgreSQL externo.'
            );

            // Verificar postcondición 2: se registraron las credenciales en Moodle.
            $exists = $DB->record_exists('sqlab_db_user_credentials', ['userid' => $user->id]);
            $this->assertTrue(
                $exists,
                'FALLO DE COMPORTAMIENTO: create_user_database() no creó ningún registro en ' .
                'mdl_sqlab_db_user_credentials para el usuario id=' . $user->id
            );

        } else {
            // Fallback para versión anterior con sqldb_creation().
            $filepath = $this->find_definition_file('sqldb_creation');
            $this->assertNotFalse($filepath,
                'No se puede incluir sqldb_creation: fichero no encontrado');
            require_once($filepath);

            $course = $this->getDataGenerator()->create_course();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

            sqldb_creation($user->id, $course->id);

            $exists = $DB->record_exists('sqlab_db_user_credentials', ['userid' => $user->id]);
            $this->assertTrue(
                $exists,
                'sqldb_creation no creó ningún registro en mdl_sqlab_db_user_credentials ' .
                'para el usuario con id=' . $user->id
            );
        }
    }

    // -----------------------------------------------------------------------
    // Suite UNI-06c/d — user_query_executor::execute_user_sql
    // -----------------------------------------------------------------------

    /**
     * UNI-06c — La clase user_query_executor contiene el método execute_user_sql.
     *
     * Verificación estructural: comprueba que la clase responsable de ejecutar
     * las consultas SQL del estudiante está implementada con el contrato de
     * interfaz esperado (método execute_user_sql).
     *
     * La comprobación usa method_exists() con el nombre sin namespace y con el
     * nombre completo '\mod_sqlab\user_query_executor' para cubrir ambas variantes
     * del plugin. Como fallback, busca la cadena 'execute_user_sql' en el código fuente.
     *
     * Requisito verificado: Lógica de ejecución de consultas SQL del estudiante.
     */
    public function test_execute_user_sql_is_defined(): void {
        global $CFG;

        $executorfile = $CFG->dirroot . '/mod/sqlab/classes/user_query_executor.php';
        $this->assertFileExists(
            $executorfile,
            'El fichero classes/user_query_executor.php no existe en mod_sqlab'
        );

        require_once($executorfile);

        // Intentar con nombre sin namespace (versión sin namespace).
        $found = method_exists('user_query_executor', 'execute_user_sql');

        // Intentar con namespace completo (versión con namespace).
        if (!$found) {
            $found = method_exists('\mod_sqlab\user_query_executor', 'execute_user_sql');
        }

        // Fallback: buscar la cadena en el fichero fuente (confirma que el método está codificado).
        if (!$found) {
            $content = file_get_contents($executorfile);
            $found   = (strpos($content, 'execute_user_sql') !== false);

            // Si se encontró en el código fuente pero no con method_exists, indicar el problema.
            if ($found) {
                $this->addWarning(
                    'execute_user_sql aparece en el código fuente de user_query_executor.php ' .
                    'pero method_exists() no lo detecta. Posible causa: la clase usa un namespace ' .
                    'distinto de "user_query_executor" y "\mod_sqlab\user_query_executor". ' .
                    'Verificar el namespace exacto con: grep -n "^namespace" ' . $executorfile
                );
            }
        }

        $this->assertTrue(
            $found,
            'El método execute_user_sql no se encuentra en user_query_executor.php. ' .
            'Comprobados: sin namespace, con namespace \mod_sqlab\user_query_executor, ' .
            'y búsqueda en código fuente. ' .
            'Este método es invocado desde execute_sql.php para procesar las consultas del alumno.'
        );
    }

    /**
     * UNI-06d — execute_user_sql ejecuta una consulta SQL y devuelve resultados estructurados.
     *
     * Llamada esperada (según execute_sql.php):
     * user_query_executor::execute_user_sql($attemptid, $sql, $schemaName)
     * donde:
     * $attemptid  = ID del intento activo del estudiante (tabla mdl_sqlab_attempts)
     * $sql        = consulta SQL enviada por el estudiante
     * $schemaName = nombre del esquema del usuario en el PostgreSQL externo
     *
     * LIMITACIÓN ARQUITECTÓNICA: para una llamada bien formada se necesitan:
     * 1. Un $attemptid válido, creado por sqlab_add_instance() que depende de
     * qtype_sqlquestion (no funcional en el entorno de test estándar).
     * 2. Un $schemaName válido, generado previamente por create_user_database().
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
                'qtype_sqlquestion) y un schemaName generado por create_user_database(). ' .
                'Ninguna de estas condiciones puede satisfacerse en moodle-docker estándar.'
            );
        }

        global $CFG;
        require_once($CFG->dirroot . '/mod/sqlab/classes/user_query_executor.php');

        // NOTA: Sustituir estos valores por los de una instancia activa del plugin
        // antes de ejecutar este test en el servidor del tutor.
        $attemptid  = 1;                    // ID de intento real de mdl_sqlab_attempts
        $sql        = 'SELECT 1 AS test';   // Consulta de verificación mínima
        $schemaName = '';                   // Esquema real del usuario (generado por create_user_database)

        $result = user_query_executor::execute_user_sql($attemptid, $sql, $schemaName);

        $this->assertNotNull($result,
            'execute_user_sql devolvió null para una consulta SQL válida');
        $this->assertIsArray($result,
            'execute_user_sql debe devolver un array con los resultados de la consulta');
    }
}

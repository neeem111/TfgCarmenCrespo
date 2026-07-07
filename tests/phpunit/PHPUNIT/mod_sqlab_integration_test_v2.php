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
 * uni-06 — pruebas de integración de las funciones de negocio principales de mod_sqlab.
 *
 * suite con tests estructurales y de comportamiento de las funciones core del plugin:
 *
 * uni-06a  verificación estructural: database_manager.php contiene check_database_exists
 *          y create_user_database (estas dos sustituyen a sqldb_creation de la versión antigua)
 * uni-06b  comportamiento de check_database_exists / create_user_database (SKIPPED sin servidor)
 * uni-06c  verificación estructural: user_query_executor contiene execute_user_sql
 * uni-06d  comportamiento de execute_user_sql contra PostgreSQL externo (SKIPPED sin servidor)
 *
 * nota sobre el renombrado de funciones (esto me lo aclaró el tutor por correo el 09/06/2026):
 *   la función sqldb_creation() de la versión anterior del plugin se refactorizó
 *   en la versión actual en dos funciones dentro de database_manager.php:
 *     - check_database_exists($db_name)
 *     - create_user_database($db_name, $user_id)
 *   execute_user_sql en cambio está presente igual en las dos versiones del plugin.
 *
 * limitación arquitectónica documentada:
 * mod_sqlab depende de un servidor PostgreSQL externo para su lógica de negocio principal.
 * ese servidor es independiente de la BD de moodle y no forma parte del entorno
 * moodle-docker estándar. por eso uni-06b y uni-06d solo se pueden ejecutar
 * cuando el servidor externo está activo y configurado.
 * ver sección 5.7.2 de la memoria.
 *
 * nota de ejecución: lo ejecuto fichero a fichero para evitar el error fatal
 * de qtype_sqlquestion\privacy\provider si uso --group mod_sqlab:
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
    // helpers
    // -----------------------------------------------------------------------

    /**
     * miro si el servidor PostgreSQL externo de mod_sqlab está configurado.
     * la config se guarda en la tabla mdl_config_plugins bajo el plugin mod_sqlab.
     *
     * @return bool True si el host del servidor externo está configurado.
     */
    private function external_postgres_is_available(): bool {
        $host = get_config('mod_sqlab', 'dbhost');
        return !empty($host);
    }

    /**
     * busco en qué fichero del plugin está definida una función o método dado.
     * lo uso como fallback para compatibilidad con la versión anterior del plugin.
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
    // suite uni-06a/b — database_manager (antes se llamaba sqldb_creation)
    // -----------------------------------------------------------------------

    /**
     * uni-06a — compruebo que las funciones de gestión de BD de usuario están definidas en el plugin.
     *
     * verificación estructural: miro que database_manager.php existe y contiene
     * check_database_exists() y create_user_database(), que según el tutor
     * (correo 09/06/2026) sustituyeron a sqldb_creation() en la versión actual del plugin.
     *
     * ojo con el nombre del test (test_sqldb_creation_is_defined): lo mantengo así
     * aunque la función ya no se llame sqldb_creation, porque el fallback de abajo
     * sigue soportando la versión antigua del plugin por si acaso.
     *
     * si no encuentro database_manager.php, intento localizar sqldb_creation()
     * como fallback para compatibilidad con la versión anterior.
     *
     * requisito verificado: lógica de gestión de credenciales y BD de usuario.
     */
    public function test_sqldb_creation_is_defined(): void {
        global $CFG;

        // versión actual: busco database_manager.php con las dos funciones refactorizadas.
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
            return; // ya está verificado con la versión actual, no hace falta el fallback.
        }

        // fallback: por si acaso es la versión anterior con sqldb_creation().
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
     * uni-06b — compruebo que check_database_exists / create_user_database registran
     * credenciales en mdl_sqlab_db_user_credentials y crean la BD en el PostgreSQL externo.
     *
     * postcondiciones que verifico:
     * 1. create_user_database() crea la BD del usuario en PostgreSQL externo.
     * 2. queda un registro en mdl_sqlab_db_user_credentials para ese usuario.
     * 3. check_database_exists() confirma que la BD se creó bien.
     *
     * limitación arquitectónica: este test necesita el servidor PostgreSQL externo.
     * si no lo tengo disponible, se marca SKIPPED. ver sección 5.7.2 de la memoria.
     *
     * requisito verificado: creación de credenciales e integración con PostgreSQL externo.
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

        // arrange: creo un usuario de prueba.
        $user = $this->getDataGenerator()->create_user();

        // cargo database_manager.php (versión actual) o busco sqldb_creation (versión anterior).
        $dmfile = $CFG->dirroot . '/mod/sqlab/classes/database_manager.php';

        if (file_exists($dmfile)) {
            require_once($dmfile);

            // el nombre de la BD del usuario suele componerse del username o user_id.
            $dbname = 'user_' . $user->id;

            // assert previo: la BD no debe existir todavía.
            $exists_before = check_database_exists($dbname);
            $this->assertFalse(
                $exists_before,
                'La BD ' . $dbname . ' ya existe antes de ejecutar create_user_database(). ' .
                'Esto indica que el entorno no está limpio o que el nombre de BD se reutiliza.'
            );

            // act: creo la BD del usuario en PostgreSQL externo.
            create_user_database($dbname, $user->id);

            // assert postcondición 1: la BD tiene que haberse creado de verdad.
            $this->assertTrue(
                check_database_exists($dbname),
                'FALLO DE COMPORTAMIENTO: create_user_database() no creó la BD "' . $dbname . '" ' .
                'en el servidor PostgreSQL externo.'
            );

            // assert postcondición 2: las credenciales tienen que quedar registradas en moodle.
            $exists = $DB->record_exists('sqlab_db_user_credentials', ['userid' => $user->id]);
            $this->assertTrue(
                $exists,
                'FALLO DE COMPORTAMIENTO: create_user_database() no creó ningún registro en ' .
                'mdl_sqlab_db_user_credentials para el usuario id=' . $user->id
            );

        } else {
            // fallback para la versión anterior del plugin, con sqldb_creation().
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
    // suite uni-06c/d — user_query_executor::execute_user_sql
    // -----------------------------------------------------------------------

    /**
     * uni-06c — compruebo que la clase user_query_executor tiene el método execute_user_sql.
     *
     * verificación estructural: miro que la clase que ejecuta las consultas SQL
     * del alumno está implementada con el método que espero (execute_user_sql).
     *
     * pruebo method_exists() con el nombre sin namespace y con el nombre completo
     * '\mod_sqlab\user_query_executor' para cubrir las dos variantes que puede tener
     * el plugin. si ninguna funciona, como último recurso busco el string
     * 'execute_user_sql' directamente en el código fuente.
     *
     * requisito verificado: lógica de ejecución de consultas SQL del alumno.
     */
    public function test_execute_user_sql_is_defined(): void {
        global $CFG;

        $executorfile = $CFG->dirroot . '/mod/sqlab/classes/user_query_executor.php';
        $this->assertFileExists(
            $executorfile,
            'El fichero classes/user_query_executor.php no existe en mod_sqlab'
        );

        require_once($executorfile);

        // pruebo primero sin namespace (por si el plugin no usa namespaces).
        $found = method_exists('user_query_executor', 'execute_user_sql');

        // si no lo encuentro, pruebo con el namespace completo.
        if (!$found) {
            $found = method_exists('\mod_sqlab\user_query_executor', 'execute_user_sql');
        }

        // último recurso: busco el string en el fichero fuente (al menos confirma que está escrito).
        if (!$found) {
            $content = file_get_contents($executorfile);
            $found   = (strpos($content, 'execute_user_sql') !== false);

            // si aparece en el código pero method_exists no lo pilla, aviso del motivo probable.
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
     * uni-06d — compruebo que execute_user_sql ejecuta una consulta SQL y devuelve
     * resultados estructurados.
     *
     * la llamada esperada (según execute_sql.php) es:
     * user_query_executor::execute_user_sql($attemptid, $sql, $schemaName)
     * donde:
     * $attemptid  = ID del intento activo del alumno (tabla mdl_sqlab_attempts)
     * $sql        = la consulta SQL que manda el alumno
     * $schemaName = nombre del esquema del usuario en el PostgreSQL externo
     *
     * limitación arquitectónica: para hacer una llamada bien formada necesito:
     * 1. un $attemptid válido, que se crea con sqlab_add_instance(), que depende de
     * qtype_sqlquestion (no funciona en el entorno de test estándar).
     * 2. un $schemaName válido, generado antes por create_user_database().
     * 3. el servidor PostgreSQL externo activo.
     * no puedo cumplir ninguna de estas tres cosas en moodle-docker estándar, así que
     * este test se queda en SKIPPED salvo que tenga el servidor externo a mano.
     *
     * ver sección 5.7.2 de la memoria: "limitación arquitectónica por dependencia
     * en qtype_sqlquestion y servidor PostgreSQL externo."
     *
     * requisito verificado: procesamiento de consultas SQL (lógica de negocio core).
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

        // nota para cuando lo ejecute en el servidor del tutor: sustituir estos valores
        // por los de una instancia activa real del plugin.
        $attemptid  = 1;                    // ID de intento real de mdl_sqlab_attempts
        $sql        = 'SELECT 1 AS test';   // consulta mínima solo para verificar que responde
        $schemaName = '';                   // esquema real del usuario (lo genera create_user_database)

        // act: ejecuto la consulta a través de la función bajo test.
        $result = user_query_executor::execute_user_sql($attemptid, $sql, $schemaName);

        // assert: tiene que devolver un array con resultados, no null.
        $this->assertNotNull($result,
            'execute_user_sql devolvió null para una consulta SQL válida');
        $this->assertIsArray($result,
            'execute_user_sql debe devolver un array con los resultados de la consulta');
    }
}

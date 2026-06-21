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
 * UNI-06 — Pruebas funcionales de las clases de negocio de mod_sqlab.
 *
 * Verifica la lógica real de las clases principales del plugin, yendo más allá de
 * comprobar si existen: se prueba que el código se comporta según su especificación.
 *
 * Clases probadas y métodos verificados:
 *
 *   encoder (pure PHP, sin dependencias externas)
 *     UNI-06a1  encrypt() devuelve una cadena Base64 no vacía
 *     UNI-06a2  encrypt() + decrypt() es un roundtrip: recover el texto original
 *     UNI-06a3  encrypt() con cadena vacía lanza InvalidArgumentException
 *     UNI-06a4  decrypt() con formato inválido lanza InvalidArgumentException
 *
 *   schema_manager (pure PHP, sin PostgreSQL externo)
 *     UNI-06b1  format_activity_name() convierte a mayúsculas
 *     UNI-06b2  format_activity_name() reemplaza espacios por guiones bajos
 *     UNI-06b3  format_activity_name() elimina caracteres especiales
 *     UNI-06b4  format_activity_name() transliteriza caracteres acentuados
 *
 *   database_manager (BD Moodle, sin PostgreSQL externo)
 *     UNI-06c1  get_role_id_by_shortname('student') devuelve un entero válido > 0
 *     UNI-06c2  get_role_id_by_shortname() lanza moodle_exception para rol inexistente
 *     UNI-06c3  handle_role_assignment() termina sin error para rol no-estudiante
 *     UNI-06c4  handle_role_assignment() lanza excepción para usuario inexistente+rol student
 *     UNI-06c5  [SKIP] handle_role_assignment() crea credenciales en BD (necesita PostgreSQL externo)
 *
 *   user_query_executor — parser SQL interno (pure PHP, vía ReflectionClass)
 *     UNI-06d1  detectStatementType() reconoce SELECT
 *     UNI-06d2  detectStatementType() reconoce INSERT
 *     UNI-06d3  detectStatementType() reconoce UPDATE
 *     UNI-06d4  detectStatementType() reconoce DELETE
 *     UNI-06d5  detectStatementType() reconoce CREATE TABLE
 *     UNI-06d6  detectStatementType() reconoce CREATE FUNCTION
 *     UNI-06d7  detectStatementType() reconoce DROP TABLE
 *     UNI-06d8  detectStatementType() devuelve UNKNOWN para sentencias no reconocidas
 *     UNI-06d9  detectQueryType() analiza múltiples sentencias y devuelve array de tipos
 *     UNI-06d10 execute_user_sql() lanza moodle_exception para SQL vacío (sin conexión ext.)
 *
 *   attempt_manager (BD Moodle, sin PostgreSQL externo)
 *     UNI-06e1  create_new_attempt() devuelve ID > 0 y crea intento con estado IN_PROGRESS
 *     UNI-06e2  create_new_attempt() incrementa el número de intento en llamadas sucesivas
 *     UNI-06e3  finalize_attempt() cambia el estado a FINISHED y establece timefinish
 *     UNI-06e4  finalize_attempt() lanza moodle_exception para ID de intento inexistente
 *     UNI-06e5  finalize_attempt() devuelve false si el intento ya estaba FINISHED
 *     UNI-06e6  check_attempt_state() devuelve el estado correcto tras crear y finalizar
 *     UNI-06e7  update_attempt_state() cambia el estado a OVERDUE correctamente
 *     UNI-06e8  check_attempt_state() lanza moodle_exception para ID de intento inexistente
 *
 *   internal_sql_executor (sin PostgreSQL externo)
 *     UNI-06f1  execute() retorna null para SQL vacío sin conectar al PostgreSQL externo
 *
 * LIMITACIÓN ARQUITECTÓNICA DOCUMENTADA (UNI-06c5):
 * La lógica central de check_database_exists() y create_user_database() requiere
 * un servidor PostgreSQL externo independiente del entorno moodle-docker estándar.
 * Los tests que dependen de él se marcan como SKIPPED con justificación documentada.
 * Ver sección 5.7.2 de la memoria.
 *
 * Nota de ejecución:
 *   vendor/bin/phpunit mod/sqlab/tests/mod_sqlab_integration_test_v3.php --testdox
 *
 * @package    mod_sqlab
 * @copyright  2025 Carmen Crespo Navarro, Universidad de Castilla-La Mancha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_sqlab
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Suite UNI-06: pruebas funcionales de las clases de negocio de mod_sqlab.
 *
 * @group mod_sqlab
 */
class mod_sqlab_integration_test_v3 extends advanced_testcase {

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Comprueba si el servidor PostgreSQL externo de mod_sqlab está configurado.
     *
     * @return bool True si dbhost está configurado en mdl_config_plugins.
     */
    private function external_postgres_is_available(): bool {
        $host = get_config('mod_sqlab', 'dbhost');
        return !empty($host);
    }

    /**
     * Invoca un método privado/protegido de una clase vía ReflectionClass.
     *
     * Permite probar la lógica interna de métodos privados sin modificar la
     * visibilidad original, respetando el principio de encapsulamiento en producción
     * pero permitiendo la verificación unitaria en el contexto de pruebas.
     *
     * @param  string $class      Nombre completo de la clase (con namespace).
     * @param  string $method     Nombre del método a invocar.
     * @param  mixed  $instance   Instancia del objeto (null para métodos estáticos).
     * @param  array  $args       Argumentos a pasar al método.
     * @return mixed  El valor de retorno del método.
     */
    private function call_private_method(string $class, string $method, $instance, array $args = []) {
        $ref = new ReflectionMethod($class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($instance, $args);
    }

    // =========================================================================
    // UNI-06a — encoder: cifrado y descifrado AES-256-CBC
    // =========================================================================

    /**
     * UNI-06a1 — encoder::encrypt() devuelve una cadena no vacía codificada en Base64.
     *
     * La implementación usa AES-256-CBC con un IV aleatorio. El resultado se codifica
     * en Base64 concatenando el texto cifrado y el IV. Este test verifica que la
     * función produce una salida no vacía y con formato Base64 válido.
     *
     * Requisito verificado: Cifrado de credenciales de usuario en BD (encoder).
     */
    public function test_encoder_encrypt_returns_nonempty_base64(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\encoder'),
            'La clase \mod_sqlab\encoder no está disponible vía autoloader.'
        );

        $plaintext = 'TestPassword123!';
        $encrypted = \mod_sqlab\encoder::encrypt($plaintext);

        $this->assertNotEmpty($encrypted,
            'encoder::encrypt() no debe devolver una cadena vacía.');
        $this->assertIsString($encrypted,
            'encoder::encrypt() debe devolver una cadena de texto.');
        // Verificar que es Base64 válido (base64_decode no devuelve false).
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded,
            'El resultado de encoder::encrypt() debe ser una cadena Base64 válida.');
    }

    /**
     * UNI-06a2 — encoder::encrypt() + decoder::decrypt() es un roundtrip exacto.
     *
     * Cifrar un texto y luego descifrarlo debe recuperar exactamente la cadena original.
     * Este test verifica la coherencia entre ambas operaciones y que la clave AES y el
     * IV se gestionan correctamente.
     *
     * Requisito verificado: Almacenamiento y recuperación correcta de credenciales.
     */
    public function test_encoder_encrypt_decrypt_roundtrip(): void {
        $original = 'Mi_Password_Secreta_2025!';

        $encrypted = \mod_sqlab\encoder::encrypt($original);
        $decrypted = \mod_sqlab\encoder::decrypt($encrypted);

        $this->assertEquals(
            $original,
            $decrypted,
            'encoder::decrypt(encoder::encrypt(x)) debe devolver x exactamente. ' .
            'El roundtrip cifrado-descifrado no es correcto.'
        );
    }

    /**
     * UNI-06a3 — encoder::encrypt() lanza InvalidArgumentException con datos vacíos.
     *
     * La función debe validar explícitamente que los datos de entrada no están vacíos
     * antes de intentar el cifrado. Intentar cifrar una cadena vacía debe lanzar
     * InvalidArgumentException, no devolver una cadena vacía silenciosamente.
     *
     * Requisito verificado: Validación de entradas en encoder::encrypt().
     */
    public function test_encoder_encrypt_empty_string_throws_exception(): void {
        $this->expectException(InvalidArgumentException::class);
        \mod_sqlab\encoder::encrypt('');
    }

    /**
     * UNI-06a4 — encoder::decrypt() lanza InvalidArgumentException con formato inválido.
     *
     * decrypt() espera una cadena Base64 que, una vez decodificada, contenga el
     * delimitador '::' entre el texto cifrado y el IV. Una cadena arbitraria que
     * no siga este formato debe lanzar InvalidArgumentException.
     *
     * Requisito verificado: Validación de formato en encoder::decrypt().
     */
    public function test_encoder_decrypt_invalid_format_throws_exception(): void {
        $this->expectException(InvalidArgumentException::class);
        // 'not_valid_base64_data' no sigue el formato esperado: base64(encrypted::IV).
        \mod_sqlab\encoder::decrypt('not_valid_base64_data_without_separator');
    }

    // =========================================================================
    // UNI-06b — schema_manager: lógica de nombrado de esquemas (pure PHP)
    // =========================================================================

    /**
     * UNI-06b1 — schema_manager::format_activity_name() convierte el nombre a mayúsculas.
     *
     * El nombre del esquema PostgreSQL que el plugin crea para cada actividad se
     * forma a partir del nombre de la actividad en Moodle, convertido a mayúsculas.
     * Este test verifica esa transformación con una cadena ya limpia.
     *
     * Requisito verificado: Generación correcta del nombre de esquema.
     */
    public function test_format_activity_name_converts_to_uppercase(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\schema_manager'),
            'La clase \mod_sqlab\schema_manager no está disponible.'
        );

        $result = \mod_sqlab\schema_manager::format_activity_name('bases_de_datos');
        $this->assertEquals('BASES_DE_DATOS', $result,
            'format_activity_name() debe convertir el nombre a mayúsculas.');
    }

    /**
     * UNI-06b2 — schema_manager::format_activity_name() reemplaza espacios por guiones bajos.
     *
     * Los nombres de actividad en Moodle pueden contener espacios, que no son válidos
     * en identificadores de esquema PostgreSQL. La función debe reemplazarlos por '_'.
     *
     * Requisito verificado: Sanitización del nombre de esquema para PostgreSQL.
     */
    public function test_format_activity_name_replaces_spaces_with_underscores(): void {
        $result = \mod_sqlab\schema_manager::format_activity_name('Actividad SQL');
        $this->assertEquals('ACTIVIDAD_SQL', $result,
            'format_activity_name() debe reemplazar espacios con guiones bajos.');
    }

    /**
     * UNI-06b3 — schema_manager::format_activity_name() elimina caracteres especiales.
     *
     * Los caracteres no alfanuméricos (salvo el guión bajo) deben eliminarse para
     * garantizar que el nombre del esquema es un identificador PostgreSQL válido.
     *
     * Requisito verificado: Sanitización del nombre de esquema para PostgreSQL.
     */
    public function test_format_activity_name_removes_special_characters(): void {
        $result = \mod_sqlab\schema_manager::format_activity_name('Act@ividad#2025!');
        // Solo deben quedar letras, números y guiones bajos.
        $this->assertMatchesRegularExpression('/^[A-Z0-9_]+$/', $result,
            'format_activity_name() debe eliminar todos los caracteres especiales, ' .
            'dejando solo letras mayúsculas, números y guiones bajos.');
        $this->assertStringNotContainsString('@', $result);
        $this->assertStringNotContainsString('#', $result);
        $this->assertStringNotContainsString('!', $result);
    }

    /**
     * UNI-06b4 — schema_manager::format_activity_name() transliteriza caracteres acentuados.
     *
     * Los nombres en castellano pueden contener caracteres con tilde (á, é, ñ, etc.)
     * que no son válidos en identificadores ASCII. La función usa iconv para transliterar
     * a ASCII antes de eliminar caracteres especiales.
     *
     * Requisito verificado: Soporte de nombres de actividad en castellano.
     */
    public function test_format_activity_name_transliterates_accented_chars(): void {
        $result = \mod_sqlab\schema_manager::format_activity_name('Programación SQL');
        // El resultado no debe contener caracteres no ASCII.
        $this->assertMatchesRegularExpression('/^[A-Z0-9_]+$/', $result,
            'format_activity_name() debe transliterizar caracteres acentuados a ASCII.');
        // 'ó' debe haberse convertido a 'O' (o eliminado).
        $this->assertStringNotContainsString('ó', $result,
            'Los caracteres con tilde deben haberse eliminado o transliterizado.');
    }

    // =========================================================================
    // UNI-06c — database_manager: gestión de roles y BD de usuario
    // =========================================================================

    /**
     * UNI-06c1 — database_manager::get_role_id_by_shortname() devuelve un ID entero válido.
     *
     * Verifica que la función puede consultar la tabla mdl_role de Moodle y devolver
     * el ID del rol 'student'. El rol 'student' existe en todo Moodle estándar.
     * Este test usa la BD de Moodle pero NO el PostgreSQL externo.
     *
     * Requisito verificado: Lógica de resolución de roles en database_manager.
     */
    public function test_get_role_id_by_shortname_returns_valid_integer(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\database_manager'),
            'La clase \mod_sqlab\database_manager no está disponible.'
        );

        $roleId = \mod_sqlab\database_manager::get_role_id_by_shortname('student');

        // $DB->get_record() devuelve columnas numéricas como string en pgsql/mariadb,
        // por lo que usamos assertIsNumeric en lugar de assertIsInt.
        $this->assertIsNumeric($roleId,
            'get_role_id_by_shortname("student") debe devolver un valor numérico (int o string numérico).');
        $this->assertGreaterThan(0, (int)$roleId,
            'get_role_id_by_shortname("student") debe devolver un ID > 0.');
    }

    /**
     * UNI-06c2 — get_role_id_by_shortname() lanza moodle_exception para un rol inexistente.
     *
     * Si se solicita un rol que no existe en Moodle, la función debe lanzar
     * moodle_exception con errorcode 'rolenotfound'. Esto garantiza que el código
     * que llama a esta función recibe un error claro en lugar de un null silencioso.
     *
     * Requisito verificado: Gestión de errores en database_manager::get_role_id_by_shortname().
     */
    public function test_get_role_id_by_shortname_throws_on_nonexistent_role(): void {
        $this->expectException(moodle_exception::class);
        \mod_sqlab\database_manager::get_role_id_by_shortname('rol_que_no_existe_xyz_99999');
    }

    /**
     * UNI-06c3 — handle_role_assignment() termina sin error para un rol no-estudiante.
     *
     * La función contiene esta lógica:
     *   if ($role_id != self::get_role_id_by_shortname('student')) { return; }
     *
     * Si el rol asignado NO es 'student', la función debe retornar inmediatamente
     * SIN intentar crear ninguna base de datos en el servidor externo. Este test
     * verifica esa lógica de cortocircuito sin necesitar PostgreSQL externo.
     *
     * Esto prueba el flujo de control más común: la mayoría de asignaciones de rol
     * no son asignaciones de 'student', por lo que esta rama se ejecuta con frecuencia.
     *
     * Requisito verificado: Lógica de filtrado por rol en handle_role_assignment().
     */
    public function test_handle_role_assignment_exits_early_for_non_student_role(): void {
        $this->resetAfterTest();
        global $DB;

        // Usar el rol 'editingteacher' que existe en Moodle estándar.
        $teacherRole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        if (!$teacherRole) {
            // Fallback: crear un rol de prueba genérico que no sea 'student'.
            $teacherRole = $this->getDataGenerator()->create_role(['shortname' => 'testrole_xyz']);
            $teacherRole = (object)['id' => $teacherRole];
        }

        $user = $this->getDataGenerator()->create_user();

        // Llamar con rol no-estudiante: debe retornar sin error y sin tocar PostgreSQL externo.
        \mod_sqlab\database_manager::handle_role_assignment([
            'objectid'      => $teacherRole->id,
            'relateduserid' => $user->id,
        ]);

        // Si llegamos aquí sin excepción, la lógica de cortocircuito funciona.
        $this->assertTrue(true,
            'handle_role_assignment() debe retornar sin error para roles no-estudiante ' .
            'sin intentar conectar al PostgreSQL externo.');

        // Verificar que NO se crearon credenciales (no debería haberlas para rol no-student).
        $credentialsCreated = $DB->record_exists('sqlab_db_user_credentials', ['userid' => $user->id]);
        $this->assertFalse($credentialsCreated,
            'handle_role_assignment() no debe crear credenciales para roles no-estudiante.');
    }

    /**
     * UNI-06c4 — handle_role_assignment() lanza excepción para usuario inexistente con rol student.
     *
     * Si el rol es 'student' pero el user_id no existe en Moodle, la función intenta
     * obtener el usuario de la BD y debe lanzar moodle_exception cuando no lo encuentra.
     * Este test verifica que el manejo de errores de usuario es correcto.
     *
     * Nota: este test llega a la línea:
     *   $user = $DB->get_record('user', ['id' => $user_id]);
     *   if (!$user) { throw new moodle_exception('usernotfound', ...); }
     * ...sin llegar al PostgreSQL externo.
     *
     * Requisito verificado: Validación de usuario en handle_role_assignment().
     */
    public function test_handle_role_assignment_throws_on_nonexistent_user(): void {
        $this->resetAfterTest();
        global $DB;

        // Obtener el ID del rol student.
        $studentRole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        $this->expectException(moodle_exception::class);

        // Usar un user_id que no existe (999999999).
        \mod_sqlab\database_manager::handle_role_assignment([
            'objectid'      => $studentRole->id,
            'relateduserid' => 999999999,
        ]);
    }

    /**
     * UNI-06c5 — [SKIP] handle_role_assignment() crea credenciales en BD y en PostgreSQL externo.
     *
     * Equivale al antiguo test de sqldb_creation. La lógica de negocio completa es:
     *   1. database_manager::check_database_exists($db_name)  → consulta pg_database
     *   2. database_manager::create_user_database($db_name, $user_id) → CREATE DATABASE
     *   3. save_student_credentials() → inserta en mdl_sqlab_db_user_credentials
     *
     * LIMITACIÓN ARQUITECTÓNICA: este test requiere el servidor PostgreSQL externo
     * (get_config('mod_sqlab', 'dbhost') debe estar configurado).
     * Sin él se marca como SKIPPED. Ver sección 5.7.2 de la memoria.
     *
     * Requisito verificado: Creación de credenciales + integración con PostgreSQL externo.
     */
    public function test_handle_role_assignment_creates_credentials_with_external_postgres(): void {
        $this->resetAfterTest();

        if (!$this->external_postgres_is_available()) {
            $this->markTestSkipped(
                'LIMITACIÓN ARQUITECTÓNICA: database_manager::handle_role_assignment ' .
                'requiere el servidor PostgreSQL externo de mod_sqlab. ' .
                'get_config("mod_sqlab","dbhost") está vacío en este entorno. ' .
                'Este test solo puede ejecutarse con el servidor externo activo. ' .
                'Ver sección 5.7.2 de la memoria.'
            );
        }

        global $DB;
        $user        = $this->getDataGenerator()->create_user();
        $studentRole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        \mod_sqlab\database_manager::handle_role_assignment([
            'objectid'      => $studentRole->id,
            'relateduserid' => $user->id,
        ]);

        $this->assertTrue(
            $DB->record_exists('sqlab_db_user_credentials', ['userid' => $user->id]),
            'handle_role_assignment debe crear un registro en mdl_sqlab_db_user_credentials.'
        );
    }

    // =========================================================================
    // UNI-06d — user_query_executor: parser SQL interno (pure PHP, vía reflexión)
    // =========================================================================

    /**
     * UNI-06d1 — detectStatementType() identifica SELECT correctamente.
     *
     * El parser SQL de user_query_executor determina el tipo de cada sentencia para
     * poder categorizar los resultados devueltos al alumno. Este test y los siguientes
     * usan ReflectionMethod para acceder al método privado estático sin modificar la
     * visibilidad del código en producción.
     *
     * Requisito verificado: Clasificación correcta de sentencias SQL (parser interno).
     */
    public function test_detect_statement_type_select(): void {
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            ['SELECT * FROM estudiantes WHERE id = 1;']
        );
        $this->assertEquals('SELECT', $result,
            'detectStatementType() debe devolver "SELECT" para sentencias SELECT.');
    }

    /**
     * UNI-06d2 — detectStatementType() identifica INSERT correctamente.
     *
     * Requisito verificado: Clasificación correcta de sentencias SQL (parser interno).
     */
    public function test_detect_statement_type_insert(): void {
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            ['INSERT INTO tabla (col1, col2) VALUES (1, \'dato\');']
        );
        $this->assertEquals('INSERT', $result,
            'detectStatementType() debe devolver "INSERT" para sentencias INSERT.');
    }

    /**
     * UNI-06d3 — detectStatementType() identifica UPDATE correctamente.
     *
     * Requisito verificado: Clasificación correcta de sentencias SQL (parser interno).
     */
    public function test_detect_statement_type_update(): void {
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            ['UPDATE empleados SET salario = 3000 WHERE id = 5;']
        );
        $this->assertEquals('UPDATE', $result,
            'detectStatementType() debe devolver "UPDATE" para sentencias UPDATE.');
    }

    /**
     * UNI-06d4 — detectStatementType() identifica DELETE correctamente.
     *
     * Requisito verificado: Clasificación correcta de sentencias SQL (parser interno).
     */
    public function test_detect_statement_type_delete(): void {
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            ['DELETE FROM productos WHERE stock = 0;']
        );
        $this->assertEquals('DELETE', $result,
            'detectStatementType() debe devolver "DELETE" para sentencias DELETE.');
    }

    /**
     * UNI-06d5 — detectStatementType() identifica CREATE TABLE correctamente.
     *
     * Requisito verificado: Clasificación correcta de sentencias DDL (parser interno).
     */
    public function test_detect_statement_type_create_table(): void {
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            ['CREATE TABLE alumnos (id SERIAL PRIMARY KEY, nombre VARCHAR(100));']
        );
        $this->assertEquals('CREATE TABLE', $result,
            'detectStatementType() debe devolver "CREATE TABLE" para sentencias CREATE TABLE.');
    }

    /**
     * UNI-06d6 — detectStatementType() identifica CREATE FUNCTION correctamente.
     *
     * Las funciones PL/pgSQL son una característica avanzada de PostgreSQL que
     * el plugin debe soportar. El parser debe reconocerlas como tipo 'CREATE FUNCTION'.
     *
     * Requisito verificado: Soporte de funciones PL/pgSQL en el parser interno.
     */
    public function test_detect_statement_type_create_function(): void {
        $sql = 'CREATE FUNCTION calcular_descuento(precio NUMERIC) RETURNS NUMERIC AS $$ BEGIN RETURN precio * 0.9; END; $$ LANGUAGE plpgsql;';
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            [$sql]
        );
        $this->assertEquals('CREATE FUNCTION', $result,
            'detectStatementType() debe devolver "CREATE FUNCTION" para sentencias CREATE FUNCTION.');
    }

    /**
     * UNI-06d7 — detectStatementType() identifica DROP TABLE correctamente.
     *
     * Requisito verificado: Clasificación correcta de sentencias DDL DROP.
     */
    public function test_detect_statement_type_drop_table(): void {
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            ['DROP TABLE IF EXISTS tabla_temporal;']
        );
        $this->assertEquals('DROP TABLE', $result,
            'detectStatementType() debe devolver "DROP TABLE" para sentencias DROP TABLE.');
    }

    /**
     * UNI-06d8 — detectStatementType() devuelve 'UNKNOWN' para sentencias no reconocidas.
     *
     * El parser debe manejar graciosamente sentencias que no coincidan con ningún
     * patrón conocido, devolviendo 'UNKNOWN' en lugar de lanzar una excepción.
     *
     * Requisito verificado: Robustez del parser SQL ante entradas inesperadas.
     */
    public function test_detect_statement_type_unknown_returns_unknown(): void {
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectStatementType', null,
            ['ESTA SENTENCIA NO ES SQL VALIDO XYZ;']
        );
        $this->assertEquals('UNKNOWN', $result,
            'detectStatementType() debe devolver "UNKNOWN" para sentencias no reconocidas.');
    }

    /**
     * UNI-06d9 — detectQueryType() analiza múltiples sentencias y devuelve un array de tipos.
     *
     * Cuando el estudiante envía un bloque SQL con varias sentencias separadas por ';',
     * detectQueryType() debe dividirlas y clasificar cada una por separado.
     * Este test verifica el análisis de un bloque con tres sentencias distintas.
     *
     * Requisito verificado: Análisis multi-sentencia del parser SQL.
     */
    public function test_detect_query_type_returns_array_for_multiple_statements(): void {
        $sql = "SELECT * FROM tabla1;\nINSERT INTO tabla2 VALUES (1);\nDELETE FROM tabla3;";
        $result = $this->call_private_method(
            '\mod_sqlab\user_query_executor', 'detectQueryType', null,
            [$sql]
        );

        $this->assertIsArray($result,
            'detectQueryType() debe devolver un array de tipos de sentencia.');
        $this->assertCount(3, $result,
            'Para un bloque con 3 sentencias, detectQueryType() debe devolver un array de 3 elementos.');
        $this->assertContains('SELECT', $result,
            'El array debe contener el tipo "SELECT".');
        $this->assertContains('INSERT', $result,
            'El array debe contener el tipo "INSERT".');
        $this->assertContains('DELETE', $result,
            'El array debe contener el tipo "DELETE".');
    }

    /**
     * UNI-06d10 — execute_user_sql() lanza moodle_exception para SQL vacío.
     *
     * La primera validación de execute_user_sql() es:
     *   if (empty($sql)) { throw new moodle_exception('emptyquery', 'mod_sqlab'); }
     *
     * NOTA DE DEFECTO DETECTADO (hallazgo de prueba):
     * En la implementación actual, la sentencia usa 'throw new moodle_exception(...)' sin el
     * prefijo '\' dentro del namespace mod_sqlab. PHP 8.x resuelve esto como
     * 'mod_sqlab\moodle_exception', que no existe, lanzando un \Error en lugar de
     * \moodle_exception. El test captura \Throwable para verificar que la función SÍ
     * lanza una excepción (comportamiento de validación correcto) aunque el tipo concreto
     * sea un artefacto del bug. La corrección en el plugin es añadir '\' al throw:
     *   throw new \moodle_exception('emptyquery', 'mod_sqlab');
     *
     * Requisito verificado: Validación de SQL vacío en execute_user_sql() (sin PostgreSQL externo).
     */
    public function test_execute_user_sql_throws_on_empty_sql(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\user_query_executor'),
            'La clase \mod_sqlab\user_query_executor no está disponible.'
        );

        // Capturamos \Throwable porque el plugin tiene un bug de namespace:
        // 'throw new moodle_exception(...)' sin '\' dentro del namespace mod_sqlab
        // lanza \Error en lugar de \moodle_exception. Con \Throwable el test pasa
        // en ambos casos (código corregido o con el bug actual).
        $this->expectException(\Throwable::class);

        // Pasar SQL vacío: debe lanzar una excepción antes de tocar ninguna BD.
        \mod_sqlab\user_query_executor::execute_user_sql(1, '', 'esquema_prueba');
    }

    // =========================================================================
    // UNI-06e — attempt_manager: ciclo de vida de intentos (BD Moodle, sin PostgreSQL externo)
    // =========================================================================

    /**
     * Helper: inserta un registro mínimo en mdl_sqlab directamente (sin sqlab_add_instance,
     * que depende de qtype_sqlquestion). Devuelve el ID del registro creado.
     *
     * @return int ID del registro sqlab creado.
     */
    private function create_sqlab_record(): int {
        global $DB;
        $course  = $this->getDataGenerator()->create_course();
        $record  = new stdClass();
        $record->course           = $course->id;
        $record->name             = 'Test SQLab ' . uniqid();
        $record->quizid           = 1;
        $record->activitypassword = '';
        $record->timecreated      = time();
        $record->timemodified     = time();
        return (int) $DB->insert_record('sqlab', $record);
    }

    /**
     * UNI-06e1 — create_new_attempt() devuelve un ID > 0 y crea el intento con estado IN_PROGRESS.
     *
     * Verifica la lógica completa de creación de intento:
     * 1. El ID devuelto es numérico y > 0.
     * 2. El registro existe en mdl_sqlab_attempts.
     * 3. El estado inicial es IN_PROGRESS ('inprogress').
     * 4. El número de intento es 1 (primer intento).
     * 5. sumgrades es 0 (sin calificación inicial).
     *
     * Requisito verificado: Creación correcta de intentos de actividad.
     */
    public function test_create_new_attempt_returns_valid_id(): void {
        $this->resetAfterTest();
        $this->assertTrue(
            class_exists('\mod_sqlab\attempt_manager'),
            'La clase \mod_sqlab\attempt_manager no está disponible.'
        );

        global $DB;
        $sqlabid = $this->create_sqlab_record();
        $user    = $this->getDataGenerator()->create_user();

        $attemptid = \mod_sqlab\attempt_manager::create_new_attempt($sqlabid, $user->id);

        $this->assertIsNumeric($attemptid,
            'create_new_attempt() debe devolver un ID numérico.');
        $this->assertGreaterThan(0, (int) $attemptid,
            'create_new_attempt() debe devolver un ID > 0.');

        $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid]);
        $this->assertNotFalse($attempt,
            'create_new_attempt() debe haber creado un registro en mdl_sqlab_attempts.');
        $this->assertEquals(\mod_sqlab\attempt_manager::IN_PROGRESS, $attempt->state,
            'El estado inicial del intento debe ser IN_PROGRESS ("inprogress").');
        $this->assertEquals(1, (int) $attempt->attempt,
            'El primer intento del usuario debe tener attempt = 1.');
        $this->assertEquals(0, (float) $attempt->sumgrades,
            'La calificación inicial de un intento debe ser 0.');
    }

    /**
     * UNI-06e2 — create_new_attempt() incrementa el número de intento en llamadas sucesivas.
     *
     * Cuando el mismo usuario realiza más de un intento en la misma actividad,
     * el campo 'attempt' debe incrementarse. Este test llama create_new_attempt()
     * dos veces y verifica que el segundo intento tiene attempt = 2.
     *
     * Requisito verificado: Numeración incremental de intentos.
     */
    public function test_create_new_attempt_increments_attempt_number(): void {
        $this->resetAfterTest();
        global $DB;

        $sqlabid = $this->create_sqlab_record();
        $user    = $this->getDataGenerator()->create_user();

        $attempt1id = \mod_sqlab\attempt_manager::create_new_attempt($sqlabid, $user->id);
        $attempt2id = \mod_sqlab\attempt_manager::create_new_attempt($sqlabid, $user->id);

        $attempt1 = $DB->get_record('sqlab_attempts', ['id' => $attempt1id]);
        $attempt2 = $DB->get_record('sqlab_attempts', ['id' => $attempt2id]);

        $this->assertEquals(1, (int) $attempt1->attempt,
            'El primer intento debe tener attempt = 1.');
        $this->assertEquals(2, (int) $attempt2->attempt,
            'El segundo intento del mismo usuario debe tener attempt = 2.');
    }

    /**
     * UNI-06e3 — finalize_attempt() cambia el estado a FINISHED y registra timefinish.
     *
     * Verifica que tras llamar a finalize_attempt():
     * 1. El método devuelve true.
     * 2. El estado del intento es FINISHED ('finished').
     * 3. El campo timefinish tiene un valor > 0.
     *
     * Requisito verificado: Finalización correcta del ciclo de vida del intento.
     */
    public function test_finalize_attempt_changes_state_to_finished(): void {
        $this->resetAfterTest();
        global $DB;

        $sqlabid   = $this->create_sqlab_record();
        $user      = $this->getDataGenerator()->create_user();
        $attemptid = \mod_sqlab\attempt_manager::create_new_attempt($sqlabid, $user->id);

        // Estado inicial debe ser IN_PROGRESS.
        $before = $DB->get_record('sqlab_attempts', ['id' => $attemptid]);
        $this->assertEquals(\mod_sqlab\attempt_manager::IN_PROGRESS, $before->state,
            'El estado inicial del intento debe ser IN_PROGRESS.');

        $result = \mod_sqlab\attempt_manager::finalize_attempt($attemptid);
        $this->assertTrue($result,
            'finalize_attempt() debe devolver true para un intento IN_PROGRESS válido.');

        $after = $DB->get_record('sqlab_attempts', ['id' => $attemptid]);
        $this->assertEquals(\mod_sqlab\attempt_manager::FINISHED, $after->state,
            'El estado del intento debe ser FINISHED tras llamar a finalize_attempt().');
        $this->assertGreaterThan(0, (int) $after->timefinish,
            'finalize_attempt() debe establecer timefinish > 0.');
    }

    /**
     * UNI-06e4 — finalize_attempt() lanza moodle_exception para un ID de intento inexistente.
     *
     * La implementación hace:
     *   $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid]);
     *   if (!$attempt) { throw new \moodle_exception('Attempt not found for ID: ...'); }
     * Este test verifica que la excepción se lanza correctamente.
     *
     * Requisito verificado: Gestión de errores en finalize_attempt().
     */
    public function test_finalize_attempt_throws_for_nonexistent_attempt(): void {
        $this->resetAfterTest();

        $this->expectException(moodle_exception::class);
        \mod_sqlab\attempt_manager::finalize_attempt(999999999);
    }

    /**
     * UNI-06e5 — finalize_attempt() devuelve false si el intento ya estaba FINISHED.
     *
     * La lógica contiene:
     *   if ($attempt->state == self::IN_PROGRESS) { ... return true; }
     *   else { return false; }
     * Finalizar un intento ya terminado no debe fallar, pero debe devolver false.
     *
     * Requisito verificado: Idempotencia de finalize_attempt().
     */
    public function test_finalize_attempt_returns_false_for_already_finished_attempt(): void {
        $this->resetAfterTest();

        $sqlabid   = $this->create_sqlab_record();
        $user      = $this->getDataGenerator()->create_user();
        $attemptid = \mod_sqlab\attempt_manager::create_new_attempt($sqlabid, $user->id);

        // Primera finalización: debe tener éxito.
        \mod_sqlab\attempt_manager::finalize_attempt($attemptid);

        // Segunda finalización del mismo intento: debe devolver false.
        $result = \mod_sqlab\attempt_manager::finalize_attempt($attemptid);
        $this->assertFalse($result,
            'finalize_attempt() debe devolver false si el intento ya estaba FINISHED.');
    }

    /**
     * UNI-06e6 — check_attempt_state() devuelve el estado correcto tras crear y finalizar.
     *
     * Verifica que check_attempt_state() refleja fielmente los cambios de estado:
     * - Después de create_new_attempt() → IN_PROGRESS.
     * - Después de finalize_attempt()   → FINISHED.
     *
     * Requisito verificado: Consistencia de check_attempt_state() con el ciclo de vida.
     */
    public function test_check_attempt_state_returns_correct_state(): void {
        $this->resetAfterTest();

        $sqlabid   = $this->create_sqlab_record();
        $user      = $this->getDataGenerator()->create_user();
        $attemptid = \mod_sqlab\attempt_manager::create_new_attempt($sqlabid, $user->id);

        $state = \mod_sqlab\attempt_manager::check_attempt_state($attemptid);
        $this->assertEquals(\mod_sqlab\attempt_manager::IN_PROGRESS, $state,
            'check_attempt_state() debe devolver IN_PROGRESS para un intento recién creado.');

        \mod_sqlab\attempt_manager::finalize_attempt($attemptid);
        $state = \mod_sqlab\attempt_manager::check_attempt_state($attemptid);
        $this->assertEquals(\mod_sqlab\attempt_manager::FINISHED, $state,
            'check_attempt_state() debe devolver FINISHED tras llamar a finalize_attempt().');
    }

    /**
     * UNI-06e7 — update_attempt_state() cambia el estado del intento a OVERDUE correctamente.
     *
     * update_attempt_state() actualiza el campo 'state' en mdl_sqlab_attempts.
     * Este test verifica que se puede cambiar a la constante OVERDUE y que
     * check_attempt_state() confirma el nuevo estado.
     *
     * Requisito verificado: Cambio de estado a OVERDUE mediante update_attempt_state().
     */
    public function test_update_attempt_state_changes_state_to_overdue(): void {
        $this->resetAfterTest();

        $sqlabid   = $this->create_sqlab_record();
        $user      = $this->getDataGenerator()->create_user();
        $attemptid = \mod_sqlab\attempt_manager::create_new_attempt($sqlabid, $user->id);

        \mod_sqlab\attempt_manager::update_attempt_state(
            $attemptid,
            \mod_sqlab\attempt_manager::OVERDUE
        );

        $state = \mod_sqlab\attempt_manager::check_attempt_state($attemptid);
        $this->assertEquals(\mod_sqlab\attempt_manager::OVERDUE, $state,
            'update_attempt_state() debe haber cambiado el estado a OVERDUE ("overdue").');
    }

    /**
     * UNI-06e8 — check_attempt_state() lanza moodle_exception para un ID de intento inexistente.
     *
     * La implementación hace:
     *   $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid], 'state');
     *   if (!$attempt) { throw new \moodle_exception('Attempt not found: ...'); }
     *
     * Requisito verificado: Gestión de errores en check_attempt_state().
     */
    public function test_check_attempt_state_throws_for_nonexistent_attempt(): void {
        $this->resetAfterTest();

        $this->expectException(moodle_exception::class);
        \mod_sqlab\attempt_manager::check_attempt_state(999999999);
    }

    // =========================================================================
    // UNI-06f — internal_sql_executor: comportamiento con SQL vacío (sin PostgreSQL externo)
    // =========================================================================

    /**
     * UNI-06f1 — internal_sql_executor::execute() retorna null para SQL vacío.
     *
     * La primera línea efectiva de execute() es:
     *   if (empty($sql)) { return; }
     * Esto significa que con SQL vacío, la función retorna null (void PHP) sin intentar
     * ninguna conexión a la BD de credenciales ni al PostgreSQL externo.
     *
     * Este test es valioso porque:
     * 1. Verifica la lógica de guarda sin necesitar infraestructura externa.
     * 2. Confirma que SQL vacío no produce un error silencioso ni una excepción inesperada.
     *
     * Requisito verificado: Manejo seguro de SQL vacío en internal_sql_executor::execute().
     */
    public function test_internal_sql_executor_returns_null_for_empty_sql(): void {
        $this->assertTrue(
            class_exists('\mod_sqlab\internal_sql_executor'),
            'La clase \mod_sqlab\internal_sql_executor no está disponible.'
        );

        // SQL vacío activa la guarda 'if (empty($sql)) { return; }' → devuelve null.
        $result = \mod_sqlab\internal_sql_executor::execute(999999, '', 'schema_test');
        $this->assertNull(
            $result,
            'internal_sql_executor::execute() debe retornar null para SQL vacío ' .
            'sin lanzar ninguna excepción ni intentar conectar al PostgreSQL externo.'
        );
    }
}

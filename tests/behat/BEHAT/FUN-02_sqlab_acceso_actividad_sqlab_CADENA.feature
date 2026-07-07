@mod @mod_sqlab
Feature: FUN-02 (cadena de dependencias) Estudiante accede a una actividad SQLab
  # esta es mi variante "de rescate" para FUN-02, la propuesta que hablé con el
  # tutor por correo: ya que crear la actividad sqlab directamente no funciona
  # (falta el generador), voy a intentar montar antes toda la cadena de la que
  # depende, a ver si así consigo esquivar el problema:
  #   1. categoría de preguntas
  #   2. preguntas de tipo sqlquestion
  #   3. un Quiz que contenga esas preguntas
  #   4. la actividad SQLab enlazada al id de ese Quiz
  #
  # lo que quiero comprobar con este experimento: si existiendo ya el Quiz de verdad
  # desaparece el error "Invalid Quiz ID" que me tumbaba el Background en el FUN-02
  # normal, o si el problema está más abajo en la cadena.
  #
  # esto depende del propio código del plugin, no de cómo escriba el test:
  #   - qtype_sqlquestion tiene que exponer un generador de preguntas para Behat
  #     (el paso "the following questions exist" con qtype = sqlquestion).
  #   - mod_sqlab tiene que tener tests/generator/lib.php y aceptar el quiz.
  #
  # cómo leer el resultado si lo relanzo:
  #   - si pasa el Background entero, la cadena funciona y Behat sería viable.
  #   - si falla en el paso de "questions", es que qtype_sqlquestion no tiene generador.
  #   - si falla al crear la actividad "sqlab", es que mod_sqlab sigue sin generador
  #     (confirmaría lo mismo que ya vi en el FUN-02 normal).
  #
  # cosas que probablemente tenga que retocar si falla:
  #   - el campo que enlaza sqlab con el quiz puede llamarse "quiz", "quizid" o
  #     ir por "idnumber". dejo las variantes comentadas más abajo por si acaso.
  #   - el "questiontext" o el nombre de la categoría de preguntas puede necesitar ajuste.
  #
  # RESULTADO REAL (esto es lo importante): al ejecutarlo, la cadena se rompe ya
  # en el paso de crear la pregunta sqlquestion — qtype_sqlquestion no implementa
  # el helper.php que Moodle exige para los tipos de pregunta, así que ni siquiera
  # llego a probar si el enlace sqlab-quiz funcionaría. este fichero, igual que el
  # FUN-02 normal, se queda como diseño/documentación de la cadena de dependencias,
  # NO como una prueba que haya pasado.

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | category |
      | Base de Datos | BBDD      | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | BBDD   | student |
    # paso 1: categoría de preguntas en el contexto del curso
    And the following "question categories" exist:
      | contextlevel | reference | name          |
      | Course       | BBDD      | Preguntas SQL |
    # paso 2: la pregunta de tipo sqlquestion. AQUÍ ES DONDE SE ME ROMPE TODO,
    # porque qtype_sqlquestion no tiene el helper.php que Moodle pide para que
    # el generador de preguntas de Behat sepa crear este tipo de pregunta.
    And the following "questions" exist:
      | questioncategory | qtype       | name   | questiontext             |
      | Preguntas SQL    | sqlquestion | SQL-01 | Escribe un SELECT basico |
    # paso 3: actividad Quiz (con idnumber para poder referenciarla luego)
    And the following "activities" exist:
      | activity | course | name              | idnumber | section |
      | quiz     | BBDD   | Cuestionario SQL  | quiz1    | 1       |
    # paso 4: añadir la pregunta al quiz
    And quiz "Cuestionario SQL" contains the following questions:
      | question | page |
      | SQL-01   | 1    |
    # paso 5: actividad SQLab enlazada al Quiz.
    # pruebo primero con "quiz" = idnumber del cuestionario:
    And the following "activities" exist:
      | activity | course | name              | intro                      | quiz  | section |
      | sqlab    | BBDD   | Consultas basicas | Practica tus consultas SQL | quiz1 | 1       |
    # si la línea de arriba falla por el nombre del campo "quiz", dejo aquí anotadas
    # las variantes que probaría después (una a la vez, comentando la anterior):
    #   | activity | course | name              | intro | quizid | section |
    #   | sqlab    | BBDD   | Consultas basicas | ...   | quiz1  | 1       |
    #   | activity | course | name              | intro | quizidnumber | section |
    #   | sqlab    | BBDD   | Consultas basicas | ...   | quiz1        | 1       |
    # en la práctica nunca llegué a probar estas variantes porque el fallo del
    # paso 2 (la pregunta sqlquestion) ya impide seguir con la cadena.

  # de nuevo, mismo objetivo que en el FUN-02 normal (CU-01): ver la actividad
  # listada en el curso. si el Background no llega a montarse, esto no se ejecuta.
  Scenario: El estudiante ve la actividad SQLab en el curso
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should see "Consultas basicas"

  # y aquí comprobaría que se puede abrir la actividad y ver su descripción
  Scenario: El estudiante puede abrir la actividad SQLab y ver su descripcion
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas basicas"
    Then I should see "Practica tus consultas SQL"

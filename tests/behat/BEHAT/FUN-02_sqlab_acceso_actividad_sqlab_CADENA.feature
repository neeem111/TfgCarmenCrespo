@mod @mod_sqlab
Feature: FUN-02 (cadena de dependencias) Estudiante accede a una actividad SQLab
  # Variante propuesta por el tutor (correo): en lugar de crear la actividad sqlab
  # directamente, se construye primero su cadena de dependencias:
  #   1. Preguntas de tipo sqlquestion
  #   2. Actividad Quiz que contiene esas preguntas
  #   3. Actividad SQLab enlazada al id del Quiz
  #
  # OBJETIVO DEL EXPERIMENTO:
  #   Comprobar si, existiendo ya el Quiz, desaparece el error "Invalid Quiz ID"
  #   que aborta el Background en la versión directa de FUN-02.
  #
  # CONDICIONES QUE DEBEN CUMPLIRSE (dependen del código del plugin, no del test):
  #   - qtype_sqlquestion debe exponer generador de preguntas para Behat
  #     (paso 'the following "questions" exist' con qtype = sqlquestion).
  #   - mod_sqlab debe tener tests/generator/lib.php capaz de aceptar el quiz.
  #
  # CÓMO LEER EL RESULTADO:
  #   - Pasa el Background -> la cadena funciona; Behat es viable para 1 usuario.
  #   - Falla en 'questions' -> qtype_sqlquestion no tiene generador.
  #   - Falla al crear 'sqlab' -> mod_sqlab no tiene generador (confirma el hallazgo).
  #
  # AJUSTES PROBABLES (revisar si falla):
  #   - El campo que enlaza sqlab con el quiz puede llamarse 'quiz', 'quizid' o
  #     referenciarse por 'idnumber'. Probar las variantes comentadas abajo.
  #   - 'questiontext' / nombre de la categoría pueden requerir ajuste.

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
    # 1) Categoria de preguntas en el contexto del curso
    And the following "question categories" exist:
      | contextlevel | reference | name          |
      | Course       | BBDD      | Preguntas SQL |
    # 2) Preguntas de tipo sqlquestion
    And the following "questions" exist:
      | questioncategory | qtype       | name   | questiontext             |
      | Preguntas SQL    | sqlquestion | SQL-01 | Escribe un SELECT basico |
    # 3) Actividad Quiz (con idnumber para poder referenciarla)
    And the following "activities" exist:
      | activity | course | name              | idnumber | section |
      | quiz     | BBDD   | Cuestionario SQL  | quiz1    | 1       |
    # 4) Anadir la pregunta al quiz
    And quiz "Cuestionario SQL" contains the following questions:
      | question | page |
      | SQL-01   | 1    |
    # 5) Actividad SQLab enlazada al Quiz.
    #    Probar primero con 'quiz' = idnumber del cuestionario:
    And the following "activities" exist:
      | activity | course | name              | intro                      | quiz  | section |
      | sqlab    | BBDD   | Consultas basicas | Practica tus consultas SQL | quiz1 | 1       |
    # Si la linea anterior falla por el campo 'quiz', probar estas variantes
    # (de una en una, comentando la de arriba):
    #   | activity | course | name              | intro | quizid | section |
    #   | sqlab    | BBDD   | Consultas basicas | ...   | quiz1  | 1       |
    #   | activity | course | name              | intro | quizidnumber | section |
    #   | sqlab    | BBDD   | Consultas basicas | ...   | quiz1        | 1       |

  # CU-01
  Scenario: El estudiante ve la actividad SQLab en el curso
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    Then I should see "Consultas basicas"

  Scenario: El estudiante puede abrir la actividad SQLab y ver su descripcion
    Given I log in as "student1"
    And I am on "BBDD" course homepage
    When I follow "Consultas basicas"
    Then I should see "Practica tus consultas SQL"

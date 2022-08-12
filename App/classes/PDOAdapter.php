<?php

namespace App\classes;

use App\classes\logging\LoggingAdapter;
use Exception;
use PDO;
use PDOException;
use Redis;
use RedisException;

class PDOAdapter
{
    private static PDO|null $db = null;

    /**
     * DB connection used by parent before forking
     *
     * @return  PDO  instance of PDO DB connection
     */
    public static function db(): PDO
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Checking for DB connection...');

            if (!isset(self::$db)) {
                LoggingAdapter::logOrDebug(
                    LoggingAdapter::$logInfo,
                    'info',
                    'Connection is not set. Creating initial connection...');

                self::$db = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname:" . $_ENV['DB_DATABASE'],
                    $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                        PDO::ATTR_DEFAULT_FETCH_MODE => 2
                    ]);

                LoggingAdapter::logOrDebug(
                    LoggingAdapter::$logInfo,
                    'info',
                    'Connection created!');
            }
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo, 'info', 'Success.');
            return self::$db;
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Creating a DB connection for child processes
     *
     * @return  PDO  instance of PDO DB connection
     */
    public static function forceCreateConnectionToDB(): PDO
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Recreating child DB connection in fork.'
            );
            $db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname:' . $_ENV['DB_DATABASE'],
                $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                    PDO::ATTR_DEFAULT_FETCH_MODE => 2
                ]);
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Child DB connection in fork created.'
            );
            return $db;
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Closing parent DB connection
     *
     * @return  void
     */
    public static function forceCloseConnectionToDB(): void
    {
        LoggingAdapter::logOrDebug(
            LoggingAdapter::$logInfo,
            'info',
            'Closing parent DB connection.'
        );
        if (!is_null(self::$db)){
            self::$db->query('KILL CONNECTION_ID()');
            self::$db = null;
        }
        LoggingAdapter::logOrDebug(
            LoggingAdapter::$logInfo,
            'info',
            'Parent DB connection closed.'
        );
    }

    /**
     * Inserting character into DB table
     *
     * @param  string  $char  character to insert
     * @return  void
     */
    public static function insertCharToDB(string $char): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onInsert'],
                ['table' => 'character_table', 'field' => 'letter', 'value' => $char]
            );

            static::db()->prepare('insert into parser_data.character_table (letter)
                                values (?)')->execute(["$char"]);

            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['successInsert'],
                ['table' => 'character_table', 'field' => 'letter', 'value' => $char]
            );
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Getting char_id from DB by passing single character (in lowercase) to the "where" clause
     *
     * @param  PDO  $dbConnection  instance of DB connection
     * @param  string  $char       character to search in DB table
     * @return string|bool
     */
    public static function getCharIdFromDB(PDO $dbConnection, string $char): string|bool
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSelect'],
                ['table' => 'character_table', 'something' => 'letter', 'value' => $char]
            );

            $queryGet = $dbConnection->prepare('select char_id from parser_data.character_table where letter = ?');
            $queryGet->execute(["$char"]);
            return $queryGet->fetchColumn();

        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Getting question_id from DB by passing question string to the "where" clause
     *
     * @param  PDO  $dbConnection  instance of PDO DB connection
     * @param  string  $question   interval name to search in DB table
     * @return string|bool
     */
    public static function getQuestionIdFromDB(PDO $dbConnection, string $question): string|bool
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSelect'],
                ['table' => 'questions', 'something' => 'question', 'value' => $question]
            );

            $queryGet = $dbConnection->prepare('select question_id from parser_data.questions where question = ?');
            $queryGet->execute(["$question"]);
            return $queryGet->fetchColumn();

        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Getting answer_id from DB by passing answer string and question_id number to the "where" clauses.
     * If returning array has one or more records - there's a duplicate answer for the same question in DB table
     *
     * @param  PDO  $dbConnection    instance of PDO DB connection
     * @param  string  $whereValue1  answer string to search in DB table
     * @param  int  $whereValue2     question_id number bound to searched answer
     * @return  bool|void
     */
    public static function checkAnswerInDB(PDO $dbConnection, string $whereValue1, int $whereValue2)
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSelectAnswer'],
                ['table' => 'answers', 'answerValue' => $whereValue1, 'questionIdValue' => $whereValue2]
            );

            $queryGet = $dbConnection->prepare('select answer_id from parser_data.answers where (answer = ? and question_id = ?)');
            $queryGet->execute(["$whereValue1", $whereValue2]);
            $result = $queryGet->fetchColumn();

            if ($result === false) {
                LoggingAdapter::logOrDebug(
                    LoggingAdapter::$logInfo,
                    'info',
                    LoggingAdapter::$logMessages['answerNotFound'],
                    ['table' => 'answers', 'answerValue' => $whereValue1, 'questionIdValue' => $whereValue2]
                );
                return false;
            } else {
                LoggingAdapter::logOrDebug(
                    LoggingAdapter::$logInfo,
                    'info',
                    LoggingAdapter::$logMessages['answerFound'],
                    ['table' => 'answers', 'answerValue' => $whereValue1, 'questionIdValue' => $whereValue2]
                );
                return true;
            }
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Insert question into DB table
     *
     * @param  PDO  $dbConnection  instance of PDO DB connection
     * @param  int  $char_id       char_id to bind question to
     * @param  string  $question   question to insert into DB table
     * @param  string  $record
     * @return  void
     * @throws RedisException
     */
    public static function insertQuestionToDB(PDO $dbConnection, int $char_id, string $question, string $record): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onInsert'],
                ['table' => 'questions', 'field' => 'question', 'value' => $question]
            );

            $dbConnection->prepare("insert into parser_data.questions (`char_id`, `question`)
                                values (?, ?)")->execute([$char_id,  $question]);

            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['successInsert'],
                ['table' => 'questions', 'field' => 'question', 'value' => $question]
            );
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'notice',
                'An PDO Error occurred while inserting "{value}. Pushing back to queue"',
                ['value' => $question]
            );
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->config("SET", 'replica-read-only', 'no');

            Parser::$redis->rPush('url', $record);
        }
    }

    /**
     * Insert answer into DB table
     *
     * @param  PDO  $dbConnection  instance of PDO DB connection
     * @param  int  $question_id   question_id to bind answer to
     * @param  string  $answer     answer to insert
     * @param  int  $length        inserted answer length
     * @param  int  $char_id       char_id to bind answer with
     * @param  string  $record
     * @return  void
     * @throws RedisException
     */
    public static function insertAnswerToDB(PDO $dbConnection, int $question_id, string $answer, int $length, int $char_id, string $record): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onInsert'],
                ['table' => 'answers', 'field' => 'answer', 'value' => $answer]
            );

            $dbConnection->prepare("insert into parser_data.answers (`question_id`, `answer`, `length`, `char_id`)
                                values (?, ?, ?, ?)")->execute([$question_id, $answer, $length, $char_id]);

            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['successInsert'],
                ['table' => 'answers', 'field' => 'answer', 'value' => $answer]
            );
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'notice',
                'An PDO Error occurred while inserting "{value}. Pushing back to queue"',
                ['value' => $answer]
            );
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->config("SET", 'replica-read-only', 'no');

            Parser::$redis->rPush('answers', $record);
        }
    }

    /**
     * Dropping all existing table connected to this project
     *
     * @return  void
     */
    public static function dropTables(): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Dropping existing tables in parser_data DB. Cleaning Redis queue'
            );
            static::db()->prepare('SET foreign_key_checks = 0')->execute();
            static::db()->prepare('SET GLOBAL connect_timeout = 60')->execute();
            static::db()->prepare('SET GLOBAL interactive_timeout = 60')->execute();
            static::db()->prepare('SET GLOBAL wait_timeout = 60')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.character_table')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.questions')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.answers')->execute();
            static::db()->prepare('SET foreign_key_checks = 1')->execute();
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->config("SET", 'replica-read-only', 'no');

            Parser::$redis->del('url');
            Parser::$redis->del('answers');
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Dropped successfully. Redis queue has "{lengthURL}" url and "{lengthANSW}" answer records',
                ['lengthURL' => Parser::$redis->lLen('url'), 'lengthANSW' => Parser::$redis->lLen('answer')]
            );
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Create all tables connected to the project
     *
     * @return  void
     */
    public static function createTables(): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Creating tables in parser_data DB.'
            );
            static::db()->prepare(
                'CREATE TABLE IF NOT EXISTS parser_data.character_table (
                                        char_id int(15) auto_increment NOT NULL,
                                        letter char unique not null,
                                        PRIMARY KEY(char_id)
                            )
                        ')->execute();
            static::db()->query(
                'CREATE TABLE IF NOT EXISTS parser_data.questions (
                                        question_id int(15)  auto_increment NOT NULL,
                                        question varchar(255) not null,
                                        char_id int,
                                        FOREIGN KEY (char_id) references parser_data.character_table(char_id) ON DELETE CASCADE,
                                        PRIMARY KEY(question_id)
                            )
                        ')->execute();
            static::db()->prepare(
                'CREATE TABLE IF NOT EXISTS parser_data.answers (
                                        answer_id int(15)  auto_increment NOT NULL,
                                        answer varchar(255) not null,
                                        question_id int,
                                        FOREIGN KEY (question_id) references parser_data.questions(question_id) ON DELETE CASCADE,
                                        length int(15) not null ,
                                        char_id int,
                                        FOREIGN KEY (char_id) references parser_data.character_table(char_id) ON DELETE CASCADE,
                                        PRIMARY KEY(answer_id)
                            )
                        ')->execute();

            $char = static::db()->prepare('SELECT COUNT(1) FROM parser_data.character_table')->execute();
            $question = static::db()->prepare('SELECT COUNT(1) FROM parser_data.questions')->execute();
            $answer = static::db()->prepare('SELECT COUNT(1) FROM parser_data.answers')->execute();
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Created successfully. Tables have c = {char}, q = {question} and a = {answer} rows.',
                ['char' => $char, 'question' => $question, 'answer' => $answer]
            );
        } catch (PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

}

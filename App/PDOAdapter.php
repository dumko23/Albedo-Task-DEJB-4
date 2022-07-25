<?php

namespace App;

use PDO;
use PDOException;

class PDOAdapter
{
    private static PDO|null $db;

    /**
     * DB connection used by parent before forking
     *
     * @return PDO - instance of PDO DB connection
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

                self::$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname:' . $_ENV['DB_DATABASE'],
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
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Creating a DB connection for child processes
     *
     * @param int $forkNumber - number of child process in use
     * @return PDO - instance of PDO DB connection
     */
    public static function forceCreateConnectionToDB(int $forkNumber): PDO
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Recreating child DB connection in "fork#{number}".',
                ['number' => $forkNumber]
            );
            $db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname:' . $_ENV['DB_DATABASE'],
                $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                    PDO::ATTR_DEFAULT_FETCH_MODE => 2
                ]);
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Child DB connection in "fork#{number}" created.',
                ['number' => $forkNumber]
            );
            return $db;
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Closing parent DB connection
     *
     * @return void
     */
    public static function forceCloseConnectionToDB(): void
    {
        LoggingAdapter::logOrDebug(
            LoggingAdapter::$logInfo,
            'info',
            'Closing parent DB connection.'
        );
        self::$db = null;
        LoggingAdapter::logOrDebug(
            LoggingAdapter::$logInfo,
            'info',
            'Parent DB connection closed.'
        );
    }

    /**
     * Inserting character into DB table
     *
     * @param string $char - character to insert
     * @return void
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
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Getting char_id from DB by passing single character (in lowercase) to the "where" clause
     *
     * @param PDO $dbConnection - instance of DB connection
     * @param string $char - character to search in DB table
     * @return bool|array
     */
    public static function getCharIdFromDB(PDO $dbConnection, string $char): bool|array
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
            return $queryGet->fetchAll();
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Getting interval_id from DB by passing interval name (e.g. a-200) to the "where" clause
     *
     * @param PDO $dbConnection - instance of PDO DB connection
     * @param string $interval - interval name to search in DB table
     * @return bool|array
     */
    public static function getIntervalIdFromDB(PDO $dbConnection, string $interval): bool|array
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSelect'],
                ['table' => 'char_interval', 'something' => 'interval_id', 'value' => $interval]
            );
            $queryGet = $dbConnection->prepare('select interval_id from parser_data.char_interval where interval_name = ?');
            $queryGet->execute(["$interval"]);
            return $queryGet->fetchAll();
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Getting question_id from DB by passing question string to the "where" clause
     *
     * @param PDO $dbConnection - instance of PDO DB connection
     * @param string $question - interval name to search in DB table
     * @return bool|array
     */
    public static function getQuestionIdFromDB(PDO $dbConnection, string $question): bool|array
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSelect'],
                ['table' => 'questions', 'something' => 'question_id', 'value' => $question]
            );
            $queryGet = $dbConnection->prepare('select question_id from parser_data.questions where question = ?');
            $queryGet->execute(["$question"]);
            return $queryGet->fetchAll();
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Getting answer_id from DB by passing answer string and question_id number to the "where" clauses.
     * If returning array has one or more records - there's a duplicate answer for the same question in DB table
     *
     * @param PDO $dbConnection - instance of PDO DB connection
     * @param string $whereValue1 - answer string to search in DB table
     * @param int $whereValue2 - question_id number bound to searched answer
     * @return bool|void
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
            $result = $queryGet->fetchAll();
            if (isset($result[0])) {
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
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Insert interval into DB table
     *
     * @param PDO $dbConnection - instance of PDO DB connection
     * @param int $char_id - char_id to bind interval to
     * @param string $interval_name - interval_name to insert
     * @return void
     */
    public static function insertIntervalToDB(PDO $dbConnection, int $char_id, string $interval_name): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onInsert'],
                ['table' => 'char_interval', 'field' => 'interval_name', 'value' => $interval_name]
            );
            $dbConnection->prepare("insert into parser_data.char_interval (`char_id`, `interval_name`)
                                values (?, ?)")->execute([$char_id, $interval_name]);
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['successInsert'],
                ['table' => 'char_interval', 'field' => 'interval_name', 'value' => $interval_name]
            );
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Insert question into DB table
     *
     * @param PDO $dbConnection - instance of PDO DB connection
     * @param int $char_id - char_id to bind question to
     * @param int $interval_id - interval_id to bind question to
     * @param string $question - question to insert into DB table
     * @return void
     */
    public static function insertQuestionToDB(PDO $dbConnection, int $char_id, int $interval_id, string $question): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onInsert'],
                ['table' => 'questions', 'field' => 'question', 'value' => $question]
            );
            $dbConnection->prepare("insert into parser_data.questions (`char_id`, `interval_id`, `question`)
                                values (?, ?, ?)")->execute([$char_id, $interval_id, $question]);
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['successInsert'],
                ['table' => 'questions', 'field' => 'question', 'value' => $question]
            );
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Insert answer into DB table
     *
     * @param PDO $dbConnection - instance of PDO DB connection
     * @param int $question_id - question_id to bind answer to
     * @param string $answer - answer to insert
     * @param int $length - inserted answer length
     * @param int $char_id - char_id to bind answer with
     * @return void
     */
    public static function insertAnswerToDB(PDO $dbConnection, int $question_id, string $answer, int $length, int $char_id): void
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
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Dropping all existing table connected to this project
     *
     * @return void
     */
    public static function dropTables(): void
    {
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Dropping existing tables in parser_data DB.'
            );
            static::db()->prepare('SET foreign_key_checks = 0')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.character_table')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.char_interval')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.questions')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.answers')->execute();
            static::db()->prepare('SET foreign_key_checks = 1')->execute();
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Dropped successfully.'
            );
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Create all tables connected to the project
     *
     * @return void
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
            static::db()->prepare(
                'CREATE TABLE IF NOT EXISTS parser_data.char_interval (
                                        interval_id int(15) auto_increment NOT NULL ,
                                        interval_name varchar(15) unique not null,
                                        char_id int,
                                        FOREIGN KEY (char_id) references parser_data.character_table(char_id) ON DELETE CASCADE,
                                        PRIMARY KEY(interval_id)
                            )
                        ')->execute();
            static::db()->query(
                'CREATE TABLE IF NOT EXISTS parser_data.questions (
                                        question_id int(15)  auto_increment NOT NULL,
                                        question varchar(255) not null,
                                        char_id int,
                                        interval_id int,
                                        FOREIGN KEY (interval_id) references parser_data.char_interval(interval_id) ON DELETE CASCADE,
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
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Created successfully.'
            );
        } catch (PDOException $exception) {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

}

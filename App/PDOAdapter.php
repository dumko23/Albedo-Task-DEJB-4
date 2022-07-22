<?php

namespace App;

use Monolog\Logger;
use PDO;
use PDOException;

class PDOAdapter
{
    private static PDO|null $db;

    public function __construct()
    {
    }

    public static function db(Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): PDO
    {
        try {
            Parser::logOrDebug($logInfo, $debugger, 'info', 'Checking for DB connection...');

            if (!isset(self::$db)) {
                Parser::logOrDebug($logInfo, $debugger, 'info', 'Connection is not set. Creating initial connection...');

                self::$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname:' . $_ENV['DB_DATABASE'],
                    $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                        PDO::ATTR_DEFAULT_FETCH_MODE => 2
                    ]);

                Parser::logOrDebug($logInfo, $debugger, 'info', 'Connection created!');
            }
            Parser::logOrDebug($logInfo, $debugger, 'info', 'Success.');
            return self::$db;
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function forceCreateConnectionToDB($forkNumber, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): PDO
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                'Recreating child DB connection in "fork#{number}".',
                ['number' => $forkNumber]
            );
            $db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname:' . $_ENV['DB_DATABASE'],
                $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                    PDO::ATTR_DEFAULT_FETCH_MODE => 2
                ]);
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                'Child DB connection in "fork#{number}" created.',
                ['number' => $forkNumber]
            );
            return $db;
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function forceCloseConnectionToDB(Logger $debugger, Logger $logInfo): void
    {
        Parser::logOrDebug($logInfo,
            $debugger,
            'info',
            'Closing parent DB connection.'
        );
        self::$db = null;
        Parser::logOrDebug($logInfo,
            $debugger,
            'info',
            'Parent DB connection closed.'
        );
    }

    public static function insertCharToDB($char, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): void
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onInsert'],
                ['table' => 'character_table', 'field' => 'letter', 'value' => $char]
            );
            static::db($logInfo, $logError, $logMessages)->prepare('insert into parser_data.character_table (letter)
                                values (?)')->execute(["$char"]);

            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['successInsert'],
                ['table' => 'character_table', 'field' => 'letter', 'value' => $char]
            );
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function getCharIdFromDB($dbConnection, $char, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): bool|array
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onSelect'],
                ['table' => 'character_table', 'something' => 'letter', 'value' => $char]
            );
            $queryGet = $dbConnection->prepare('select char_id from parser_data.character_table where letter = ?');
            $queryGet->execute(["$char"]);
            return $queryGet->fetchAll();
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function getIntervalIdFromDB($dbConnection, $interval, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): bool|array
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onSelect'],
                ['table' => 'char_interval', 'something' => 'interval_id', 'value' => $interval]
            );
            $queryGet = $dbConnection->prepare('select interval_id from parser_data.char_interval where interval_name = ?');
            $queryGet->execute(["$interval"]);
            return $queryGet->fetchAll();
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function getQuestionIdFromDB($dbConnection, $question, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): bool|array
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onSelect'],
                ['table' => 'questions', 'something' => 'question_id', 'value' => $question]
            );
            $queryGet = $dbConnection->prepare('select question_id from parser_data.questions where question = ?');
            $queryGet->execute(["$question"]);
            return $queryGet->fetchAll();
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function checkAnswerInDB($dbConnection, $whereValue1, $whereValue2, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages)
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onSelectAnswer'],
                ['table' => 'answers', 'answerValue' => $whereValue1, 'questionIdValue' => $whereValue2]
            );
            $queryGet = $dbConnection->prepare('select answer_id from parser_data.answers where (answer = ? and question_id = ?)');
            $queryGet->execute(["$whereValue1", $whereValue2]);
            $result = $queryGet->fetchAll();
            if (isset($result[0])) {
                Parser::logOrDebug($logInfo,
                    $debugger,
                    'info',
                    $logMessages['answerNotFound'],
                    ['table' => 'answers', 'answerValue' => $whereValue1, 'questionIdValue' => $whereValue2]
                );
                return false;
            } else {
                Parser::logOrDebug($logInfo,
                    $debugger,
                    'info',
                    $logMessages['answerFound'],
                    ['table' => 'answers', 'answerValue' => $whereValue1, 'questionIdValue' => $whereValue2]
                );
                return true;
            }
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }


    }

    public static function insertIntervalToDB($dbConnection, $char_id, $interval_name, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): void
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onInsert'],
                ['table' => 'char_interval', 'field' => 'interval_name', 'value' => $interval_name]
            );
            $dbConnection->prepare("insert into parser_data.char_interval (`char_id`, `interval_name`)
                                values (?, ?)")->execute([$char_id, $interval_name]);
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['successInsert'],
                ['table' => 'char_interval', 'field' => 'interval_name', 'value' => $interval_name]
            );
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function insertQuestionToDB($dbConnection, $char_id, $interval_id, $question, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): void
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onInsert'],
                ['table' => 'questions', 'field' => 'question', 'value' => $question]
            );
            $dbConnection->prepare("insert into parser_data.questions (`char_id`, `interval_id`, `question`)
                                values (?, ?, ?)")->execute([$char_id, $interval_id, $question]);
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['successInsert'],
                ['table' => 'questions', 'field' => 'question', 'value' => $question]
            );
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function insertAnswerToDB($dbConnection, $question_id, $answer, $length, $char_id, Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): void
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['onInsert'],
                ['table' => 'answers', 'field' => 'answer', 'value' => $answer]
            );
            $dbConnection->prepare("insert into parser_data.answers (`question_id`, `answer`, `length`, `char_id`)
                                values (?, ?, ?, ?)")->execute([$question_id, $answer, $length, $char_id]);
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                $logMessages['successInsert'],
                ['table' => 'answers', 'field' => 'answer', 'value' => $answer]
            );
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    public static function dropTables(Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): void
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                'Dropping existing tables in parser_data DB.'
            );
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare('SET foreign_key_checks = 0')->execute();
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare('DROP TABLE IF EXISTS parser_data.character_table')->execute();
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare('DROP TABLE IF EXISTS parser_data.char_interval')->execute();
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare('DROP TABLE IF EXISTS parser_data.questions')->execute();
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare('DROP TABLE IF EXISTS parser_data.answers')->execute();
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare('SET foreign_key_checks = 1')->execute();
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                'Dropped successfully.'
            );
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }

    }

    public static function createTables(Logger $debugger, Logger $logInfo, Logger $logError, array $logMessages): void
    {
        try {
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                'Creating tables in parser_data DB.'
            );
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare(
                'CREATE TABLE IF NOT EXISTS parser_data.character_table (
                                        char_id int(15) auto_increment NOT NULL,
                                        letter char unique not null,
                                        PRIMARY KEY(char_id)
                            )
                        ')->execute();
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare(
                'CREATE TABLE IF NOT EXISTS parser_data.char_interval (
                                        interval_id int(15) auto_increment NOT NULL ,
                                        interval_name varchar(15) unique not null,
                                        char_id int,
                                        FOREIGN KEY (char_id) references parser_data.character_table(char_id) ON DELETE CASCADE,
                                        PRIMARY KEY(interval_id)
                            )
                        ')->execute();
            static::db($debugger, $logInfo, $logError, $logMessages)->query(
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
            static::db($debugger, $logInfo, $logError, $logMessages)->prepare(
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
            Parser::logOrDebug($logInfo,
                $debugger,
                'info',
                'Created successfully.'
            );
        } catch (PDOException $exception) {
            Parser::logOrDebug($logError,
                $debugger,
                'error',
                $logMessages['onPDOError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

}

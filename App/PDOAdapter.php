<?php

namespace App;

use PDO;
use PDOException;

class PDOAdapter
{
    private static PDO|null $db;

    public function __construct()
    {
    }

    public static function db(): PDO
    {
        if (!isset(self::$db)) {
            self::$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname:' . $_ENV['DB_DATABASE'],
                $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                    PDO::ATTR_DEFAULT_FETCH_MODE => 2
                ]);
        }
        return self::$db;
    }

    public static function forceCreateConnectionToDB($forkNumber): PDO
    {
        echo date("Y-m-d H:i:s") . ". Recreating child DB connection in fork#$forkNumber." . PHP_EOL;
        $db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname:' . $_ENV['DB_DATABASE'],
            $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                PDO::ATTR_DEFAULT_FETCH_MODE => 2
            ]);
        echo date("Y-m-d H:i:s") . ". Child DB connection in fork#$forkNumber created." . PHP_EOL;
        return $db;
    }

    public static function forceCloseConnectionToDB(): void
    {
        echo date("Y-m-d H:i:s") . ". Closing parent DB connection." . PHP_EOL;
        self::$db = null;
        echo date("Y-m-d H:i:s") . ". Parent DB connection closed." . PHP_EOL;
    }

    public static function insertCharToDB($char): void
    {
        echo date("Y-m-d H:i:s") . ". Adding row to 'character_table' table. Value char: '$char'..." . PHP_EOL;
        static::db()->prepare('insert into parser_data.character_table (letter)
                                values (?)')->execute(["$char"]);
        echo date("Y-m-d H:i:s") . ". Successfully added to 'character_table'. Value char: '$char'." . PHP_EOL;
    }

    public static function getCharIdFromDB($dbConnection, $char): bool|array
    {

        $queryGet = $dbConnection->prepare('select char_id from parser_data.character_table where letter = ?');
        $queryGet->execute(["$char"]);
        return $queryGet->fetchAll();
    }

    public static function getIntervalIdFromDB($dbConnection, $interval): bool|array
    {
        $queryGet = $dbConnection->prepare('select interval_id from parser_data.char_interval where interval_name = ?');
        $queryGet->execute(["$interval"]);
        return $queryGet->fetchAll();
    }

    public static function getQuestionIdFromDB($dbConnection, $question): bool|array
    {
        $queryGet = $dbConnection->prepare('select question_id from parser_data.questions where question = ?');
        $queryGet->execute(["$question"]);
        return $queryGet->fetchAll();
    }

    public static function getAnswerIdFromDB($dbConnection, $whereValue1, $whereValue2){
        $queryGet = $dbConnection->prepare('select answer_id from parser_data.answers where answer = ? and question_id = ?');
        $queryGet->execute(["$whereValue1", "$whereValue2"]);
        return $queryGet->fetchAll();
    }

    public static function insertIntervalToDB($dbConnection, $char_id, $interval_name): void
    {
        echo date("Y-m-d H:i:s") . ". Adding row to 'char_interval' table. Values char_id: '$char_id', interval_name: '$interval_name'..." . PHP_EOL;
        $dbConnection->prepare("insert into parser_data.char_interval (`char_id`, `interval_name`)
                                values (?, ?)")->execute([$char_id, $interval_name]);
        echo date("Y-m-d H:i:s") . ". Successfully added to 'char_interval'. Values char_id: '$char_id', interval_name: '$interval_name'." .
            PHP_EOL . "~//~" . PHP_EOL;
    }

    public static function insertQuestionToDB($dbConnection, $char_id, $interval_id, $question): void
    {
        echo date("Y-m-d H:i:s") . ". Adding row to 'questions' table. Values char_id: '$char_id', interval_id: '$interval_id', question: '$question', char_id: '$char_id'..." . PHP_EOL;

        $dbConnection->prepare("insert into parser_data.questions (`char_id`, `interval_id`, `question`)
                                values (?, ?, ?)")->execute([$char_id, $interval_id, $question]);
        echo date("Y-m-d H:i:s") . ". Successfully added to 'questions'. Values char_id: '$char_id', interval_id: '$interval_id', question: '$question'." . PHP_EOL;
    }

    public static function insertAnswerToDB($dbConnection, $question_id, $answer, $length, $char_id): void
    {
        echo date("Y-m-d H:i:s") . ". Adding row to 'answers' table. Values question_id: '$question_id', answer: '$answer', length: '$length', char_id: '$char_id'..." . PHP_EOL;
        $dbConnection->prepare("insert into parser_data.answers (`question_id`, `answer`, `length`, `char_id`)
                                values (?, ?, ?, ?)")->execute([$question_id, $answer, $length, $char_id]);
        echo date("Y-m-d H:i:s") . ". Successfully added to 'answers'. Values question_id: '$question_id', answer: '$answer', length: '$length', char_id: '$char_id'."
            . PHP_EOL . "~//~" . PHP_EOL;
    }

//    public static function insertCharToDB($item)
//    {
//        static::db()->prepare('insert into parser_data.Character (char)
//                                values (?, ?)')->execute([$item]);
//    }

    public static function getFromDB(): bool|array
    {
        $queryGet = 'select cacheKey, cacheValue from cacheDB.items;';
        return static::db()->query($queryGet)->fetchAll();
    }

    public static function dropTables(): void
    {
        try {
            echo date("Y-m-d H:i:s") . ". Dropping existing tables in parser_data DB." . PHP_EOL;
            static::db()->prepare('SET foreign_key_checks = 0')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.character_table')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.char_interval')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.questions')->execute();
            static::db()->prepare('DROP TABLE IF EXISTS parser_data.answers')->execute();
            static::db()->prepare('SET foreign_key_checks = 1')->execute();
            echo date("Y-m-d H:i:s") . ". Dropped successfully." . PHP_EOL;
        } catch (PDOException $exception) {
            echo $exception->getMessage();
        }

    }

    public static function createTables(): void
    {
        try {
            echo date("Y-m-d H:i:s") . ". Creating tables in parser_data DB." . PHP_EOL;
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
            echo date("Y-m-d H:i:s") . '. Created successfully.' . PHP_EOL;
        } catch (PDOException $exception) {
            echo $exception;
        }
    }

}

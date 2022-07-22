<?php

namespace App;

use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use DiDom\Query;
use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PDO;
use PDOException;

class Parser
{
    public static Logger $logInfo;
    public static Logger $logError;
    public static Logger $debugLogger;
    public static array $logMessages = [
        'onInsert' => 'Adding row to "{table}" table. Value "{field}": "{value}"...',
        'successInsert' => 'Successfully added to "{table}". Value "{field}": "{value}".',
        'onError' => 'Got error with message "{message}" at line "{number}".',
        'onPDOError' => 'Got PDO error with message "{message}" at line "{number}".',
        'onSelect' => 'Trying to get "{something}" = "{value}" from table "{table}"...',
        'onSelectAnswer' => 'Trying to get "answer" = "{answerValue}" with question_id = "{questionIdValue}" from table "{table}"...',
        'answerFound' => 'Field "answer" with values "{answerValue}" and "{questionIdValue}" is already exist in "{table}" table! Skipping insert...',
        'answerNotFound' => 'Field "answer" with values "{answerValue}" and "{questionIdValue}" is not found in "{table}" table! Performing insert...',
        'onSkip' => 'Field "{field}": "{value}". Skipped!',
        'checkDuplicate' => 'Checking for duplicate entry for value "{field}": "{value}" in "{table}" table...',
        'onFound' => 'Field  "{field}": "{value}" is already exist in "{table}" table! Skipping insert...',
        'onNotFound' => 'Field with value "{field}": "{value}" is not found in "{table}" table! Performing insert..'
    ];

    public function __construct()
    {
    }

    /**
     * Registering loggers to work with, also can be used to drop & create DB in MYSQL (uncomment appropriate line)
     *
     * @return void
     */
    public static function initializeParser(): void
    {
        $dateFormat = "Y-m-d H:i:s";
        $output = "%datetime% > %channel%.%level_name% > %message%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        $infoStream = new StreamHandler(__DIR__ . '/logs/log_file.log', Level::Info);
        $infoStream->setFormatter($formatter);
        $errorStream = new StreamHandler(__DIR__ . '/logs/log_file.log', Level::Error);
        $errorStream->setFormatter($formatter);
        $debugStream = new DebugLogger();
        $debugStream->setFormatter($formatter);

        static::$logInfo = new Logger('parser_info');
        static::$logInfo->pushHandler($infoStream);
        static::$logInfo->pushProcessor(new PsrLogMessageProcessor());

        static::$logError = new Logger('parser_errors');
        static::$logError->pushHandler($errorStream);
        static::$logError->pushProcessor(new PsrLogMessageProcessor());

        static::$debugLogger = new Logger('parser_debug');
        static::$debugLogger->pushHandler($debugStream);
        static::$debugLogger->pushProcessor(new PsrLogMessageProcessor());


        static::logOrDebug(static::$logInfo,
            static::$debugLogger,
            'info',
            'Initializing Parser...'
        );

//        Parser::dropNCreate(); // To initialize fresh tables
    }

    /**
     * Drop existing tables connected to task and create fresh ones
     *
     * @return void
     */
    public static function dropNCreate(): void
    {
        PDOAdapter::dropTables(static::$debugLogger, static::$logInfo, static::$logError, static::$logMessages);
        PDOAdapter::createTables(static::$debugLogger, static::$logInfo, static::$logError, static::$logMessages);
    }

    /**
     * Performing logging process according to DEBUG_MODE in .env
     *
     * @param Logger $logger - logger instance to log in normal mode
     * @param Logger $debugger - logger instance to log in debug mode
     * @param string $logType - log type to write in log stream
     * @param array|string $logMessage - log message to write in log stream (prepared or custom string)
     * @param array $params - array of parameters handled by PsrLogMessageProcessor
     * @return void
     */
    public static function logOrDebug(Logger $logger, Logger $debugger, string $logType, array|string $logMessage, array $params = []): void
    {
        if ($_ENV['DEBUG_MODE'] === "ON") {
            $debugger->$logType($logMessage, $params);
        } else if ($_ENV['DEBUG_MODE'] === "OFF"){
            $logger->$logType($logMessage, $params);
        }
    }

    /**
     * Create a new instance of DiDom\Document from given url
     *
     * @param string $href - url to create from
     * @return Document
     */
    public static function createNewDocument(string $href = ''): Document
    {
        static::logOrDebug(static::$logInfo,
            static::$debugLogger,
            'info',
            'Creating new document from {url}',
            ['url' => $_ENV['URL'] . $href]
        );
        try {
            return new Document($_ENV['URL'] . $href, true);
        } catch (Exception $exception) {
            static::logOrDebug(static::$logError,
                static::$debugLogger,
                'error',
                static::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Parse DiDom\Document to find a needle element
     *
     * @param Document $doc - document where you're searching in
     * @param string $needle - an element that you're searching for
     * @return \DiDom\Element[]|\DOMElement[]|void
     */
    public static function parseArrayOfElementsFromDocument(Document $doc, string $needle)
    {
        try {
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                'Searching for "{needle}"...',
                ['needle' => $needle]
            );
            return $doc->find($needle)[0]
                ->find('a');
        } catch (InvalidSelectorException $exception) {
            static::logOrDebug(static::$logError,
                static::$debugLogger,
                'error',
                static::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Insert characters to table from an array of DiDom\Document anchor elements
     *
     * @param array $array - an array of elements
     * @return void
     */
    public static function insertCharactersFromAnchors(array $array): void
    {
        foreach ($array as $anchor) {
            $character = $anchor->getAttribute('href');
            if (strlen($character) === 1) {
                if (
                    static::checkForDuplicateEntries(
                        'character_table',
                        $anchor->getAttribute('href'),
                        PDOAdapter::getCharIdFromDB(
                            PDOAdapter::db(
                                static::$debugLogger,
                                static::$logInfo,
                                static::$logError,
                                static::$logMessages),
                            $character,
                            static::$debugLogger,
                            static::$logInfo,
                            static::$logError,
                            static::$logMessages),
                        'letter'
                    )
                ) {
                    PDOAdapter::insertCharToDB($character, static::$debugLogger,  static::$logInfo, static::$logError, static::$logMessages);
                } else {
                    static::logOrDebug(static::$logInfo,
                        static::$debugLogger,
                        'info',
                        static::$logMessages['onSkip'],
                        ['field' => 'letter', 'value' => $character]
                    );
                }
            }
        }
    }

    /**
     * Create an array with content "href" => "innerHTML" from DiDom\Document table
     *
     * @param Document $doc - table to create from
     * @param string $needle - needle to search in table
     * @return array
     */
    public static function makeArrayFromTable(Document $doc, string $needle): array
    {
        $result = static::parseArrayOfElementsFromDocument($doc, $needle);
        $resultArray = [];
        for ($i = 0; $i < count($result); $i = $i + 2) {
            $resultArray[$result[$i]->getAttribute('href')] = $result[$i + 1]->innerHtml();
        }
        return $resultArray;
    }

    /**
     * Insert interval to MYSQL table
     *
     * @param string $character - letter to search for a char_id to create table reference
     * @param Element $seiteLink - DiDom\Document element to parse interval name
     * @return void
     */
    public static function insertIntervalOfAnswers(PDO $db, string $character, Element $seiteLink): void
    {
        $interval = $seiteLink->getAttribute('href');
        if (
            static::checkForDuplicateEntries(
                'interval_id',
                $seiteLink->getAttribute('href'),
                PDOAdapter::getIntervalIdFromDB(
                    $db,
                    $interval,
                    static::$debugLogger,
                    static::$logInfo,
                    static::$logError,
                    static::$logMessages),
                'interval_name'
            )
        ) {
            PDOAdapter::insertIntervalToDB($db,
                intval(
                    PDOAdapter::getCharIdFromDB(
                        $db,
                        $character,
                        static::$debugLogger,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['char_id']),
                $interval,
                static::$debugLogger,
                static::$logInfo,
                static::$logError,
                static::$logMessages
            );
        } else {
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                static::$logMessages['onSkip'],
                ['field' => 'interval', 'value' => $interval]
            );
        }
    }

    /**
     * Insert question and  answer in MYSQL table
     *
     * @param Document $questionPage - DiDom\Document element to parse question, answer and answer length from
     * @param string $character - letter to search for a char_id to create table reference
     * @param string $interval - interval to search for an interval_id to create table reference
     * @return void
     * @throws InvalidSelectorException
     */
    public static function insertQuestionAndAnswer(PDO $db, Document $questionPage, string $character, string $interval): void
    {
        $question = $questionPage->find('#HeaderString')[0]->innerHtml();
        if (
            static::checkForDuplicateEntries(
                'questions',
                $question,
                PDOAdapter::getQuestionIdFromDB($db,
                    $question,
                    static::$debugLogger,
                    static::$logInfo,
                    static::$logError,
                    static::$logMessages),
                'question'
            )
        ) {
            PDOAdapter::insertQuestionToDB($db,
                intval(
                    PDOAdapter::getCharIdFromDB($db,
                        $character,
                        static::$debugLogger,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['char_id']),
                intval(
                    PDOAdapter::getIntervalIdFromDB($db,
                        $interval,
                        static::$debugLogger,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['interval_id']),
                $question,
                static::$debugLogger,
                static::$logInfo,
                static::$logError,
                static::$logMessages
            );
        } else {
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                static::$logMessages['onSkip'],
                ['field' => 'question', 'value' => $question]
            );
        }

        $answers = $questionPage
            ->find('td.Answer');

        if (count($answers) > 1) {
            for ($i = 0; $i < count($answers); $i++) {

                $answer = $answers[$i]
                    ->firstChild()
                    ->getNode()
                    ->textContent;

                Parser::insertAnswer($db, $answer, $question, $character);
            }
        } else {
            $answer = $answers[0]
                ->firstChild()
                ->getNode()
                ->textContent;

            Parser::insertAnswer($db, $answer, $question, $character);
        }
    }

    /**
     * Insert answer in MYSQL table
     *
     * @param $db - DB connection to work with DB
     * @param $answer - answer to insert
     * @param $question - question to search for question_id to create table reference
     * @param $character - letter to search for char_id to create table reference
     * @return void
     */
    public static function insertAnswer($db, $answer, $question, $character): void
    {
        if (
            PDOAdapter::checkAnswerInDB($db,
                $answer,
                intval(
                    PDOAdapter::getQuestionIdFromDB($db,
                        $question,
                        static::$debugLogger,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['question_id']
                ),
                static::$debugLogger,
                static::$logInfo,
                static::$logError,
                static::$logMessages)
        ) {
            PDOAdapter::insertAnswerToDB($db,
                intval(
                    PDOAdapter::getQuestionIdFromDB($db,
                        $question,
                        static::$debugLogger,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['question_id']
                ),
                $answer,
                strlen($answer),
                intval(
                    PDOAdapter::getCharIdFromDB($db,
                        $character,
                        static::$debugLogger,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['char_id']),
                static::$debugLogger,
                static::$logInfo,
                static::$logError,
                static::$logMessages
            );
        } else {
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                static::$logMessages['onSkip'],
                ['field' => 'answer', 'value' => $answer]
            );
        }
    }

    /**
     * Processing $result parameter for duplicate entries of  given element (letter, interval or question)
     *
     * @param $tableName - table searched in
     * @param $whereValue - element value searched for
     * @param $result - resulting array from search process
     * @param $field - element searched for (letter, interval or question)
     * @return bool
     */
    public static function checkForDuplicateEntries($tableName, $whereValue, $result, $field): bool
    {
        static::logOrDebug(static::$logInfo,
            static::$debugLogger,
            'info',
            static::$logMessages['checkDuplicate'],
            ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
        );
        if (isset($result[0])) {
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                static::$logMessages['onFound'],
                ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
            );
            return false;
        } else {
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                static::$logMessages['onNotFound'],
                ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
            );
            return true;
        }
    }

    /**
     * Perform general parse process. Includes creating documents from given url, inserting characters to DB,
     * checking for duplicates and performing multithreading processes
     *
     * @return void
     */
    public static function doParse()
    {

        $mainDocument = Parser::createNewDocument();
        $arrayOfCharacterAnchors = Parser::parseArrayOfElementsFromDocument($mainDocument, '.dnrg');

        Parser::insertCharactersFromAnchors($arrayOfCharacterAnchors);


        try {
            $chunkedArray = array_chunk($arrayOfCharacterAnchors, ceil(count($arrayOfCharacterAnchors) / $_ENV['THREAD_NUM']));
            $arrayLength = count($chunkedArray);
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                'Creating "{number}" threads of execution!',
                ['number' => $_ENV['THREAD_NUM']]
            );
            static::logOrDebug(static::$logInfo,
                static::$debugLogger,
                'info',
                'Array chunked into "{number}" parts!',
                ['number' => $arrayLength]
            );

            for ($j = 0; $j < count($chunkedArray); $j++) {
                $subArray = $chunkedArray[$j];

                $pid = pcntl_fork();

                if ($pid == -1) {
                    static::logOrDebug(static::$logInfo,
                        static::$debugLogger,
                        'info',
                        'Error forking...'
                    );
                    exit();
                } else if (!$pid) {
                    // make new connection in the child process.
                    $db = PDOAdapter::forceCreateConnectionToDB(
                        $j,
                        static::$debugLogger,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages
                    );
                    static::logOrDebug(static::$logInfo,
                        static::$debugLogger,
                        'info',
                        'Executing "fork #{number}"',
                        ['number' => $j]
                    );
                    for ($i = 0; $i < count($subArray); $i++) {
                        $anchor = $subArray[$i];
                        if (strlen($anchor->getAttribute('href')) === 1) {
                            //
                            Parser::doJob($anchor, $db);
                            //
                        }
                    }
                    exit;
                } else {
                    // parent node
                    PDOAdapter::forceCloseConnectionToDB(static::$debugLogger, static::$logInfo);
                }
            }
            while (pcntl_waitpid(0, $status) != -1) ;
        } catch (InvalidSelectorException $exception) {
            static::logOrDebug(static::$logError,
                static::$debugLogger,
                'error',
                static::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Performing specific parse processes for intervals, questions and answers.
     *
     * @param $anchor - DiDom\Document element with character
     * @param $db - DB connection to work with
     * @return void
     * @throws InvalidSelectorException
     */
    public static function doJob($anchor, $db): void
    {
        $character = $anchor->getAttribute('href');

        $intervalsPage = Parser::createNewDocument($anchor->getAttribute('href'));

        $listOfIntervals = Parser::parseArrayOfElementsFromDocument($intervalsPage, '.dnrg');

        foreach ($listOfIntervals as $interval) {
            $intervalName = $interval->getAttribute('href');
            Parser::insertIntervalOfAnswers($db, $character, $interval);

            $tableOfQuestions = Parser::createNewDocument($intervalName);

            $arrayOfQuestions = Parser::makeArrayFromTable($tableOfQuestions, 'tbody');

            foreach ($arrayOfQuestions as $link => $question) {

                $answerPage = Parser::createNewDocument($link);
                Parser::insertQuestionAndAnswer($db, $answerPage, $character, $intervalName);
            }
        }
    }
}

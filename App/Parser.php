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


    public static function initializeParser(): void
    {
        $dateFormat = "Y-m-d H:i:s";
        $output = "%datetime% > %channel%.%level_name% > %message%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        $infoStream = new StreamHandler( __DIR__. '/logs/log_file.log', Level::Info);
        $infoStream->setFormatter($formatter);
        $errorStream = new StreamHandler(__DIR__. '/logs/log_file.log', Level::Error);
        $errorStream->setFormatter($formatter);

        static::$logInfo = new Logger('parser_info');
        static::$logInfo->pushHandler($infoStream);
        static::$logInfo->pushProcessor(new PsrLogMessageProcessor());

        static::$logError = new Logger('parser_errors');
        static::$logError->pushHandler($errorStream);
        static::$logError->pushProcessor(new PsrLogMessageProcessor());

        static::$logInfo->info('Initializing Parser...', );

//        Parser::dropNCreate(); // To initialize fresh tables
    }

    /**
     *
     */
    public static function dropNCreate(): void
    {

        PDOAdapter::dropTables(static::$logInfo, static::$logError, static::$logMessages);
        PDOAdapter::createTables(static::$logInfo, static::$logError, static::$logMessages);
    }

    /**
     * @param string $href
     * @return Document
     */
    public static function createNewDocument(string $href = ''): Document
    {
        static::$logInfo->info('Creating new document from {url}', ['url' => $_ENV['URL'] . $href]);
        try {
            return new Document($_ENV['URL'] . $href, true);
        } catch (Exception $exception) {
            static::$logError->error(static::$logMessages['onError'], ['message' => $exception->getMessage(), 'number' => $exception->getLine()]);
        }
    }

    /**
     * @param Document $doc
     * @param string $needle
     * @return \DiDom\Element[]|\DOMElement[]|void
     */
    public static function parseArrayOfElementsFromDocument(Document $doc, string $needle)
    {
        try {
            static::$logInfo->info('Searching for "{needle}"...', ['needle' => $needle]);

            return $doc->find($needle)[0]
                ->find('a');
        } catch (InvalidSelectorException $exception) {
            static::$logError->error(static::$logMessages['onError'], ['message' => $exception->getMessage(), 'number' => $exception->getLine()]);
        }
    }

    /**
     * @param array $array
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
                                static::$logInfo,
                                static::$logError,
                                static::$logMessages),
                            $character,
                            static::$logInfo,
                            static::$logError,
                            static::$logMessages),
                        'letter'
                    )
                ) {
                    PDOAdapter::insertCharToDB($character, static::$logInfo, static::$logError, static::$logMessages);
                } else {
                    static::$logInfo->info(static::$logMessages['onSkip'], ['field' => 'letter', 'value' => $character]);
                }
            }
        }
    }

    /**
     * @param Document $doc
     * @param string $needle
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
     * @param string $character
     * @param Element $seiteLink
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
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['char_id']),
                $interval,
                static::$logInfo,
                static::$logError,
                static::$logMessages
            );
        } else {
            static::$logInfo->info(static::$logMessages['onSkip'], ['field' => 'interval', 'value' => $interval]);
        }
    }

    /**
     * @param Document $questionPage
     * @param string $character
     * @param string $interval
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
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['char_id']),
                intval(
                    PDOAdapter::getIntervalIdFromDB($db,
                        $interval,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['interval_id']),
                $question,
                static::$logInfo,
                static::$logError,
                static::$logMessages
            );
        } else {
            static::$logInfo->info(static::$logMessages['onSkip'], ['field' => 'question', 'value' => $question]);
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

    public static function insertAnswer($db, $answer, $question, $character): void
    {
        if (
            PDOAdapter::checkAnswerInDB($db,
                $answer,
                intval(
                    PDOAdapter::getQuestionIdFromDB($db,
                        $question,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['question_id']),
                static::$logInfo,
                static::$logError,
                static::$logMessages)
        ) {
            PDOAdapter::insertAnswerToDB($db,
                intval(
                    PDOAdapter::getQuestionIdFromDB($db,
                        $question,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['question_id']),
                $answer,
                strlen($answer),
                intval(
                    PDOAdapter::getCharIdFromDB($db,
                        $character,
                        static::$logInfo,
                        static::$logError,
                        static::$logMessages)[0]['char_id']),
                static::$logInfo,
                static::$logError,
                static::$logMessages
            );
        } else {
            static::$logInfo->info(static::$logMessages['onSkip'], ['field' => 'answer', 'value' => $answer]);
        }
    }

    public static function checkForDuplicateEntries($tableName, $whereValue, $result, $field): bool
    {
        static::$logInfo->info(static::$logMessages['checkDuplicate'], ['table' => $tableName, 'field' => $field, 'value' => $whereValue]);
        if (isset($result[0])) {
            static::$logInfo->info(static::$logMessages['onFound'], ['table' => $tableName, 'field' => $field, 'value' => $whereValue]);            return false;
        } else {
            static::$logInfo->info(static::$logMessages['onNotFound'], ['table' => $tableName, 'field' => $field, 'value' => $whereValue]);            return true;
        }
    }

    public static function doParse()
    {

        $mainDocument = Parser::createNewDocument();
        $arrayOfCharacterAnchors = Parser::parseArrayOfElementsFromDocument($mainDocument, '.dnrg');

        Parser::insertCharactersFromAnchors($arrayOfCharacterAnchors);


        try {
            $chunkedArray = array_chunk($arrayOfCharacterAnchors, ceil(count($arrayOfCharacterAnchors) / $_ENV['THREAD_NUM']));
            $arrayLength = count($chunkedArray);
            static::$logInfo->info('Creating "{number}" threads of execution!', ['number' => $_ENV['THREAD_NUM']]);
            static::$logInfo->info('Array chunked into "{number}" parts!', ['number' => $arrayLength]);

            for ($j = 0; $j < count($chunkedArray); $j++) {
                $subArray = $chunkedArray[$j];

                $pid = pcntl_fork();

                if ($pid == -1) {
                    static::$logInfo->info('Error forking...');
                    exit();
                } else if (!$pid) {
                    // make new connection in the child process.
                    $db = PDOAdapter::forceCreateConnectionToDB($j, static::$logInfo, static::$logError, static::$logMessages);
                    static::$logInfo->info('Executing "fork #{number}"', ['number' => $j]);
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
                    PDOAdapter::forceCloseConnectionToDB(static::$logInfo);
                }
            }
            while (pcntl_waitpid(0, $status) != -1) ;
        } catch (InvalidSelectorException $exception) {
            static::$logError->error(static::$logMessages['onError'], ['message' => $exception->getMessage(), 'number' => $exception->getLine()]);
        }
    }

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

<?php

namespace App;

use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use PDO;


class Parser
{
    /**
     * Initial Parser method - initializing logger to work with, also can be used to drop & create DB in MYSQL (uncomment appropriate line)
     *
     * @return void
     */
    public static function initializeParser(): void
    {
        LoggingAdapter::initializeLogger();

        LoggingAdapter::logOrDebug(
            LoggingAdapter::$logInfo,
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
        PDOAdapter::dropTables();
        PDOAdapter::createTables();
    }

    /**
     * Create a new instance of DiDom\Document from given url
     *
     * @param string $href - url to create from
     * @return Document
     */
    public static function createNewDocument(string $href = ''): Document
    {
        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'Creating new document from {url}',
            ['url' => $_ENV['URL'] . $href]
        );
        try {
            return new Document($_ENV['URL'] . $href, true);
        } catch (Exception $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Parse DiDom\Document to find a needle element
     *
     * @param Document $doc - document where you're searching in
     * @param string $needle - an element that you're searching for
     * @return Element[]|\DOMElement[]|void
     */
    public static function parseArrayOfElementsFromDocument(Document $doc, string $needle)
    {
        try {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Searching for "{needle}"...',
                ['needle' => $needle]
            );
            return $doc->find($needle)[0]
                ->find('a');
        } catch (InvalidSelectorException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
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
                        PDOAdapter::getCharIdFromDB(PDOAdapter::db(), $character),
                        'letter'
                    )
                ) {
                    PDOAdapter::insertCharToDB($character);
                } else {
                    LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                        'info',
                        LoggingAdapter::$logMessages['onSkip'],
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
     * @param PDO $db
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
                PDOAdapter::getIntervalIdFromDB($db, $interval),
                'interval_name'
            )
        ) {
            PDOAdapter::insertIntervalToDB($db,
                intval(PDOAdapter::getCharIdFromDB($db, $character)[0]['char_id']),
                $interval,
            );
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSkip'],
                ['field' => 'interval', 'value' => $interval]
            );
        }
    }

    /**
     * Insert question and  answer in MYSQL table
     *
     * @param PDO $db
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
                PDOAdapter::getQuestionIdFromDB(
                    $db,
                    $question,
                ),
                'question'
            )
        ) {
            PDOAdapter::insertQuestionToDB($db,
                intval(PDOAdapter::getCharIdFromDB($db, $character)[0]['char_id']),
                intval(PDOAdapter::getIntervalIdFromDB($db, $interval)[0]['interval_id']),
                $question
            );
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSkip'],
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
     * @param PDO $db - DB connection to work with DB
     * @param string $answer - answer to insert
     * @param string $question - question to search for question_id to create table reference
     * @param string $character - letter to search for char_id to create table reference
     * @return void
     */
    public static function insertAnswer(PDO $db, string $answer, string $question, string $character): void
    {
        if (
            PDOAdapter::checkAnswerInDB($db,
                $answer,
                intval(PDOAdapter::getQuestionIdFromDB($db, $question)[0]['question_id']
                )
            )
        ) {
            PDOAdapter::insertAnswerToDB($db,
                intval(PDOAdapter::getQuestionIdFromDB($db, $question)[0]['question_id']
                ),
                $answer,
                strlen($answer),
                intval(PDOAdapter::getCharIdFromDB($db, $character)[0]['char_id'])
            );
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSkip'],
                ['field' => 'answer', 'value' => $answer]
            );
        }
    }

    /**
     * Processing $result parameter for duplicate entries of  given element (letter, interval or question)
     *
     * @param string $tableName - table searched in
     * @param int $whereValue - element value searched for
     * @param array $result - resulting array from search process
     * @param string $field - element searched for (letter, interval or question)
     * @return bool
     */
    public static function checkForDuplicateEntries(string $tableName, int $whereValue, array $result, string $field): bool
    {
        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            LoggingAdapter::$logMessages['checkDuplicate'],
            ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
        );
        if (isset($result[0])) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onFound'],
                ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
            );
            return false;
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onNotFound'],
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
    public static function doParse(): void
    {

        $mainDocument = Parser::createNewDocument();
        $arrayOfCharacterAnchors = Parser::parseArrayOfElementsFromDocument($mainDocument, '.dnrg');

        Parser::insertCharactersFromAnchors($arrayOfCharacterAnchors);


        try {
            $chunkedArray = array_chunk($arrayOfCharacterAnchors, ceil(count($arrayOfCharacterAnchors) / $_ENV['THREAD_NUM']));
            $arrayLength = count($chunkedArray);
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Creating "{number}" threads of execution!',
                ['number' => $_ENV['THREAD_NUM']]
            );
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Array chunked into "{number}" parts!',
                ['number' => $arrayLength]
            );

            if ($_ENV['THREAD_NUM'] > 1) {

                for ($j = 0; $j < count($chunkedArray); $j++) {
                    $subArray = $chunkedArray[$j];

                    $pid = pcntl_fork();

                    if ($pid == -1) {
                        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                            'info',
                            'Error forking...'
                        );
                        exit();
                    } else if (!$pid) {
                        // make new connection in the child process.
                        $db = PDOAdapter::forceCreateConnectionToDB($j);
                        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
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
                        PDOAdapter::forceCloseConnectionToDB();
                    }
                }
                while (pcntl_waitpid(0, $status) != -1) ;

            } else {
                foreach ($arrayOfCharacterAnchors as $anchor) {
                    if (strlen($anchor->getAttribute('href')) === 1) {
                        //
                        Parser::doJob($anchor, PDOAdapter::db());
                        //
                    }
                }
            }

        } catch (InvalidSelectorException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine()]
            );
        }
    }

    /**
     * Performing specific parse processes for intervals, questions and answers.
     *
     * @param Element $anchor - DiDom\Document element with character
     * @param PDO $db - DB connection to work with
     * @return void
     * @throws InvalidSelectorException
     */
    public static function doJob(Element $anchor, PDO $db): void
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

<?php

namespace App;

use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use DiDom\Query;
use Exception;
use PDO;
use PDOException;

class Parser
{

    public function __construct()
    {
    }

    /**
     *
     */
    public static function dropNCreate(): void
    {
        PDOAdapter::dropTables();
        PDOAdapter::createTables();
    }

    /**
     * @param string $href
     * @return Document
     */
    public static function createNewDocument(string $href = ''): Document
    {
        return new Document($_ENV['URL'] . $href, true);
    }

    /**
     * @param Document $doc
     * @param string $needle
     * @return \DiDom\Element[]|\DOMElement[]|void
     */
    public static function parseArrayOfElementsFromDocument(Document $doc, string $needle)
    {
        try {
            return $doc->find($needle)[0]
                ->find('a');
        } catch (InvalidSelectorException $exception) {
            echo PHP_EOL;
            print_r($exception);
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
            if (strlen($character) === 1)
                if (static::checkForDuplicateEntries(
                    'character_table',
                    $anchor->getAttribute('href'),
                    PDOAdapter::getCharIdFromDB(PDOAdapter::db(), $character))
                ) {
                    PDOAdapter::insertCharToDB($character);
                } else {
                    echo date("Y-m-d H:i:s") . ". Character: $character. Skipped!" . PHP_EOL;
                }
        }
    }

    /**
     * @param Document $doc
     * @param string $needle
     * @return array
     */
    public static function makeArrayFromTable(Document $doc, string $needle)
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
        if (static::checkForDuplicateEntries(
            'interval_id',
            $seiteLink->getAttribute('href'),
            PDOAdapter::getIntervalIdFromDB($db, $interval))
        ) {
            PDOAdapter::insertIntervalToDB($db,
                intval(PDOAdapter::getCharIdFromDB($db, $character)[0]['char_id']),
                $interval
            );
        } else {
            echo date("Y-m-d H:i:s") . ". Interval: $interval. Skipped!" . PHP_EOL;
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
        if (static::checkForDuplicateEntries(
            'questions',
            $question,
            PDOAdapter::getQuestionIdFromDB($db, $question))
        ) {
            PDOAdapter::insertQuestionToDB($db,
                intval(PDOAdapter::getCharIdFromDB($db, $character)[0]['char_id']),
                intval(PDOAdapter::getIntervalIdFromDB($db, $interval)[0]['interval_id']),
                $question
            );
        } else {
            echo date("Y-m-d H:i:s") . ". Question: $question. Skipped!" . PHP_EOL;
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
        if (PDOAdapter::getAnswerIdFromDB($db,
                $answer,
                intval(PDOAdapter::getQuestionIdFromDB($db, $question)[0]['question_id']))) {

            PDOAdapter::insertAnswerToDB($db,
                intval(PDOAdapter::getQuestionIdFromDB($db, $question)[0]['question_id']),
                $answer,
                strlen($answer),
                intval(PDOAdapter::getCharIdFromDB($db, $character)[0]['char_id'])
            );
        } else {
            echo date("Y-m-d H:i:s") . ". Answer: $answer . Skipped!" . PHP_EOL;
        }
    }

    public static function checkForDuplicateEntries($tableName, $whereValue, $result): bool
    {
        echo date("Y-m-d H:i:s") . ". Checking for duplicate entry for value '$whereValue' in '$tableName' table..." . PHP_EOL;
        if (isset($result[0])) {
            echo date("Y-m-d H:i:s") . ". Field with value '$whereValue' is already exist in '$tableName' table! Skipping insert..." . PHP_EOL;
            return false;
        } else {
            echo date("Y-m-d H:i:s") . ". Field with value '$whereValue' is not found in '$tableName' table! Performing insert.." . PHP_EOL;
            return true;
        }
    }

    public static function checkForDuplicateAnswerEntry($whereValue1, $whereValue2, $result): bool
    {

    }
}

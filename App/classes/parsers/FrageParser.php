<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use PDOException;
use Redis;
use RedisException;

class FrageParser implements ParserInterface
{
    /**
     * @inheritDoc
     *
     * @param  string  $url     URL of type "url-to-parse|ClassName"
     * @param  string  $record  Redis record to send back in queue in specific case
     * @return void
     * @throws RedisException
     */
    public static function parse(string $url, string $record): void
    {
        try {
            // Receiving SIGTERM signal from parent process
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function ($signal) use ($record) {
                if ($signal === SIGTERM) {
                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Force-closing fork...'
                    );
                    Parser::$redis = new Redis();
                    Parser::$redis->connect('redis-stack');
                    Parser::$redis->lPush('url', $record);

                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Returning URL: {url} in queue...',
                        ['url' => $record]
                    );

                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Exiting fork...'
                    );
                    exit();
                }
            });

            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Searching for "{needle}" in {url}...',
                ['needle' => 'tbody', 'url' => $url]
            );
            //
            $tableOfQuestions = Parser::createNewDocument($url, $record);

            $arrayOfQuestions = self::makeArrayFromTable($tableOfQuestions, 'tbody');

            foreach ($arrayOfQuestions as $link => $question) {
                self::insertQuestionToDB($question, $link, $record);
            }
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Exiting fork process...',
            );
            //
        } catch (RedisException|PDOException|Exception $exception2) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception2->getMessage(), 'number' => $exception2->getLine(), 'class' => self::class, 'record' => $record]
            );
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'notice',
                'An Error occurred while processing "{value}. Pushing back to queue"',
                ['value' => $record]
            );
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->rPush('url', $record);
        }
    }

    /**
     * Inserts question and  answer in MYSQL table
     *
     * @param  string  $question
     * @param  string  $link
     * @param  string  $record
     * @return  void
     * @throws RedisException
     */
    public static function insertQuestionToDB(string $question, string $link, string $record): void
    {
        $db = PDOAdapter::forceCreateConnectionToDB();
        $char_id = intval(PDOAdapter::getCharIdFromDB($db, substr(strtolower($question), 0, 1)));

        if (
            Parser::checkForDuplicateEntries(
                'questions',
                $question,
                PDOAdapter::getQuestionIdFromDB(
                    $db,
                    $question,
                ),
                'question') === false
        ) {
            PDOAdapter::insertQuestionToDB($db,
                $char_id,
                $question,
                $record
            );
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSkip'],
                ['field' => 'question', 'value' => $question]
            );
        }

        $question_id = PDOAdapter::getQuestionIdFromDB($db, $question);
        $newRecord = $_ENV['URL'] . $link . '|AntwortParser|' . $question_id . '|' . $char_id;

        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'Pushing new record to queue: "{value}"; processed: "{field}"',
            ['field' => $record, 'value' => $newRecord]
        );

        Parser::$redis = new Redis();
        Parser::$redis->connect('redis-stack');
        Parser::$redis->rPush('answers', $newRecord);
    }

    /**
     * Creates an array with content "href" => "innerHTML" from DiDom\Document table
     *
     * @param  Document  $doc   table to create from
     * @param  string  $needle  needle to search in table
     * @return  array
     */
    public static function makeArrayFromTable(Document $doc, string $needle): array
    {
        $result = Parser::parseArrayOfElementsFromDocument($doc, $needle);
        $resultArray = [];
        for ($i = 0; $i < count($result); $i = $i + 2) {
            if(!array_key_exists($result[$i]->getAttribute('href'), $resultArray)){
                $resultArray[$result[$i]->getAttribute('href')] = $result[$i]->innerHtml();
            }
        }
        return $resultArray;
    }
}
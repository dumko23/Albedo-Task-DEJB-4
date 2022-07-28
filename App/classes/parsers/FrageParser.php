<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use Redis;
use RedisException;

class FrageParser implements ParserInterface
{

    public static function parse(string $url): void
    {
        try {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function ($signal) use ($url) {
                if ($signal === SIGTERM) {
                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Force-closing fork...'
                    );
                    Parser::$redis = new Redis();
                    Parser::$redis->connect('redis-stack');
                    Parser::$redis->rPush('url', $url);

                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Returning URL: {url}...',
                        ['url' => $url]
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
                'Searching for "{needle}"...',
                ['needle' => 'tbody']
            );
            //
            $tableOfQuestions = Parser::createNewDocument($url);

            $arrayOfQuestions = self::makeArrayFromTable($tableOfQuestions, 'tbody');

            foreach ($arrayOfQuestions as $link => $question) {
                self::insertQuestionToDB($question, $link, $url);
            }
            PDOAdapter::forceCloseConnectionToDB();
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Exiting fork process...',
            );
            exit();
            //
        } catch (RedisException $exception2) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception2->getMessage(), 'number' => $exception2->getLine(), 'class' => self::class]
            );
        }
    }


    /**
     * Insert question and  answer in MYSQL table
     *
     * @param  string  $question
     * @param  string  $link
     * @param  string  $url
     * @return  void
     * @throws RedisException
     */
    public static function insertQuestionToDB(string $question, string $link, string $url): void
    {
        $db = PDOAdapter::forceCreateConnectionToDB();
        $array = explode('/', $url);
        if (
            Parser::checkForDuplicateEntries(
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
                intval(PDOAdapter::getCharIdFromDB($db, substr(strtolower($question), 0, 1))[0]['char_id']),
                intval(PDOAdapter::getIntervalIdFromDB($db, $array[count($array) - 1])[0]['interval_id']),
                $question
            );
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSkip'],
                ['field' => 'question', 'value' => $question]
            );
        }
        Parser::$redis = new Redis();
        Parser::$redis->connect('redis-stack');
        Parser::$redis->rPush('url', $_ENV['URL'] . $link . '|AntwortParser');
    }

    /**
     * Create an array with content "href" => "innerHTML" from DiDom\Document table
     *
     * @param  Document  $doc  table to create from
     * @param  string  $needle  needle to search in table
     * @return  array
     */
    public static function makeArrayFromTable(Document $doc, string $needle): array
    {
        $result = Parser::parseArrayOfElementsFromDocument($doc, $needle);
        $resultArray = [];
        for ($i = 0; $i < count($result); $i = $i + 2) {
            $resultArray[$result[$i]->getAttribute('href')] = $result[$i + 1]->innerHtml();
        }
        return $resultArray;
    }
}
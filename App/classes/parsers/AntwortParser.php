<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use PDO;
use PDOException;
use Redis;
use RedisException;

class AntwortParser implements ParserInterface
{
    /**
     * @inheritDoc
     *
     * @param  string  $url     URL  of type "url-to-parse|ClassName"
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
                ['needle' => 'td.Answer', 'url' => $url]
            );

            //
            $doc = Parser::createNewDocument($url, $record);

            self::prepareInsert($doc, $record);
            PDOAdapter::forceCloseConnectionToDB();
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Exiting fork process...',
            );
            //

        } catch (InvalidSelectorException|RedisException|PDOException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'notice',
                'An PDO Error occurred while processing "{value}". Pushing back to queue',
                ['value' => $record]
            );
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->lPush('url', $record);
        }
    }

    /**
     * Preparing answer record to insert by retrieving it and all necessary additional data from Answer page
     *
     * @param  Document  $questionPage  DiDom document to be parsed
     * @param  string  $record
     * @return void
     * @throws InvalidSelectorException|RedisException
     */
    public static function prepareInsert(Document $questionPage, string $record): void
    {
        $answers = $questionPage
            ->find('td.Answer');
        $question = $questionPage
            ->find('#HeaderString')[0]
            ->innerHtml();
        $db = PDOAdapter::forceCreateConnectionToDB();
        if (count($answers) > 1) {
            for ($i = 0; $i < count($answers); $i++) {

                $answer = $answers[$i]
                    ->firstChild()
                    ->getNode()
                    ->textContent;

                self::insertAnswer($db, $answer, substr(strtolower($question), 0, 1), $record);
            }
        } else {
            $answer = $answers[0]
                ->firstChild()
                ->getNode()
                ->textContent;

            self::insertAnswer($db, $answer, $question, substr(strtolower($question), 0, 1), $record);
        }

    }


    /**
     * Insert answer in MYSQL table
     *
     * @param  PDO  $db            DB connection to work with DB
     * @param  string  $answer     answer to insert
     * @param  string  $character  letter to search for char_id to create table reference
     * @param  string  $record
     * @return  void
     * @throws RedisException
     */
    public static function insertAnswer(PDO $db, string $answer,  string $character, string $record): void
    {
        $array = explode('|', $record);

        $question_id = end($array);
        $char_id = intval(PDOAdapter::getCharIdFromDB($db, $character));
        if($question_id === false){

            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->rPush('url', $record);
            exit;
        }
        $question_id = intval($question_id);
        if (
            PDOAdapter::checkAnswerInDB($db,
                $answer,
                $question_id
            ) === false
        ) {



            PDOAdapter::insertAnswerToDB($db,
                $question_id,
                $answer,
                strlen($answer),
                $char_id,
                $record
            );

        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onSkip'],
                ['field' => 'answer', 'value' => $answer]
            );
        }
    }
}
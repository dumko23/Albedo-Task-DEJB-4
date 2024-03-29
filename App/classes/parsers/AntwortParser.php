<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
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

                    Parser::$redis->lPush('answer', $record);

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

            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Exiting fork process...',
            );
            //

        } catch (InvalidSelectorException|RedisException|PDOException|Exception $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class, 'record' => $record]
            );
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'notice',
                'An PDO Error occurred while processing "{value}". Pushing back to queue',
                ['value' => $record]
            );


            Parser::$redis->rPush('url', $record);
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
        $answersCount = count($answers);

        $db = PDOAdapter::forceCreateConnectionToDB();

        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'Number of answers = "{count}" on the "{record}"',
            ['count' => $answersCount, 'record' => $record]
        );


        for ($i = 0; $i < $answersCount; $i++) {

            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Inserting "{num}" from "{count}" answers',
                ['count' => $answersCount, 'num' => $i+1]
            );

            $answer = $answers[$i]
                ->firstChild()
                ->getNode()
                ->textContent;

            self::insertAnswer($db, $answer, $record);
        }

        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'All of "{count}" answers inserted',
            ['count' => $answersCount,]
        );
    }


    /**
     * Insert answer in MYSQL table
     *
     * @param  PDO  $db         DB connection to work with DB
     * @param  string  $answer  answer to insert
     * @param  string  $record
     * @return  void
     * @throws RedisException
     */
    public static function insertAnswer(PDO $db, string $answer, string $record): void
    {
        $array = explode('|', $record);

        $question_id = intval($array[2]);
        $char_id = intval($array[3]);
        $length = strlen($answer);

        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'Verifying data: answer: "{answ}"; question_id: "{qid}; char_id: "{cid}"; length: "{len}"',
            ['answ' => $answer, 'qid' => $question_id, 'cid' => $char_id, 'len' => $length]
        );

        if (
            PDOAdapter::checkAnswerInDB($db,
                $answer,
                $question_id
            ) === false
        ) {

            PDOAdapter::insertAnswerToDB($db,
                $question_id,
                $answer,
                $length,
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
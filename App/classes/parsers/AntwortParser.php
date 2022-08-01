<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use PDO;
use Redis;
use RedisException;

class AntwortParser implements ParserInterface
{
    /**
     * @inheritDoc
     *
     * @param  string  $url  URL  of type "url-to-parse|ClassName"
     * @param  string  $record  Redis record to send back in queue in specific case
     * @return void
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
                    Parser::$redis->rPush('url', $record);

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

        } catch (InvalidSelectorException|RedisException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Preparing answer record to insert by retrieving it and all necessary additional data from Answer page
     *
     * @param  Document  $questionPage  DiDom document to be parsed
     * @param  string  $record
     * @return void
     * @throws InvalidSelectorException
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

                self::insertAnswer($db, $answer, $question, substr(strtolower($question), 0, 1), $record);
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
     * @param  string  $question   question to search for question_id to create table reference
     * @param  string  $character  letter to search for char_id to create table reference
     * @param  string  $record
     * @return  void
     */
    public static function insertAnswer(PDO $db, string $answer, string $question, string $character, string $record): void
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
                intval(PDOAdapter::getCharIdFromDB($db, $character)[0]['char_id']),
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
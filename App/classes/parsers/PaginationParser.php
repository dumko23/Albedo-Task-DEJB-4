<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Exceptions\InvalidSelectorException;
use PDOException;
use Redis;
use RedisException;

class PaginationParser implements ParserInterface
{
    /**
     * @inheritDoc
     *
     * @param  string  $url  URL of type "url-to-parse|ClassName"
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
                ['needle' => 'ul.dnrg', 'url' => $url]
            );

            //
            $doc = Parser::createNewDocument($url, $record);

            $arrayOfPagination = Parser::parseArrayOfElementsFromDocument($doc, 'ul.dnrg');

            self::insertIntervals($arrayOfPagination, $record);

            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Exiting fork process...',
            );
            //

        } catch (RedisException|PDOException $exception2) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception2->getMessage(), 'number' => $exception2->getLine(), 'class' => self::class, 'record' => $record]
            );
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'notice',
                'An PDO Error occurred while processing "{value}. Pushing back to queue"',
                ['value' => $record]
            );


            Parser::$redis->rPush('url', $record);
        }
    }

    /**
     * Insert intervals to table from an array of DiDom\Document anchor elements
     *
     * @param  array  $listOfIntervals  an array of elements
     * @param  string  $record
     * @return  void
     * @throws RedisException
     */
    private static function insertIntervals(array $listOfIntervals, string $record): void
    {
        foreach ($listOfIntervals as $interval) {
            $intervalLink = $interval->getAttribute('href');

            $newRecord = $_ENV['URL'] . $intervalLink . '|FrageParser';

            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Pushing new record to queue: "{value}"; processed: "{field}"',
                ['field' => $record, 'value' => $newRecord]
            );

            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->config("SET", 'replica-read-only', 'no');
            Parser::$redis->config("SET", 'protected-mode', 'yes');

            Parser::$redis->rPush('url', $newRecord);
        }
    }

}
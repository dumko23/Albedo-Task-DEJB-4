<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Exceptions\InvalidSelectorException;
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
                ['needle' => 'ul.dnrg', 'url' => $url]
            );

            //
            $doc = Parser::createNewDocument($url, $record);

            $arrayOfPagination = Parser::parseArrayOfElementsFromDocument($doc, 'ul.dnrg');

            PaginationParser::insertIntervals($arrayOfPagination);
            PDOAdapter::forceCloseConnectionToDB();
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Exiting fork process...',
            );
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
     * Insert intervals to table from an array of DiDom\Document anchor elements
     *
     * @param  array  $listOfIntervals  an array of elements
     * @return  void
     * @throws RedisException
     */
    private static function insertIntervals(array $listOfIntervals): void
    {
        $db = PDOAdapter::forceCreateConnectionToDB();
        foreach ($listOfIntervals as $interval) {
            $intervalName = $interval->getAttribute('href');

            if (
                Parser::checkForDuplicateEntries(
                    'interval_id',
                    $intervalName,
                    PDOAdapter::getIntervalIdFromDB($db, $intervalName),
                    'interval_name'
                )
            ) {
                PDOAdapter::insertIntervalToDB($db,
                    intval(PDOAdapter::getCharIdFromDB(
                        $db,
                        substr($intervalName, 0, 1)
                    )[0]['char_id']),
                    $intervalName,
                );
            } else {
                LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                    'info',
                    LoggingAdapter::$logMessages['onSkip'],
                    ['field' => 'interval', 'value' => $intervalName]
                );
            }
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->rPush('url', $_ENV['URL'] . $intervalName . '|FrageParser');
        }
    }

}
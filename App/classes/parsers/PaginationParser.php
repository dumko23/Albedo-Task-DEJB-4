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
                ['needle' => '.dnrg']
            );

            //
            $doc = Parser::createNewDocument($url);

            $arrayOfPagination = Parser::parseArrayOfElementsFromDocument($doc, '.dnrg');

            PaginationParser::insertIntervals($arrayOfPagination);
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
<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Exceptions\InvalidSelectorException;
use Redis;
use RedisException;

class CharParser implements ParserInterface
{
    /**
     * Parses HTML document from given URL
     *
     * @param  string  $url
     * @return void
     */
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
                        'Returning URL: {url} in queue...',
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

            $arrayOfAnchors = Parser::parseArrayOfElementsFromDocument($doc, '.dnrg');

            self::insertCharactersFromAnchors($arrayOfAnchors);
            PDOAdapter::forceCloseConnectionToDB();
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
     * Insert characters to table from an array of DiDom\Document anchor elements
     *
     * @param  array  $array  an array of elements
     * @return  void
     * @throws RedisException
     */
    public static function insertCharactersFromAnchors(array $array): void
    {
        $db = PDOAdapter::forceCreateConnectionToDB();
        foreach ($array as $anchor) {
            $character = $anchor->getAttribute('href');
            if (strlen($character) === 1) {
                if (
                    Parser::checkForDuplicateEntries(
                        'character_table',
                        $anchor->getAttribute('href'),
                        PDOAdapter::getCharIdFromDB($db, $character),
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
                Parser::$redis = new Redis();
                Parser::$redis->connect('redis-stack');
                Parser::$redis->rPush('url', $_ENV['URL'] . $character . '|PaginationParser');
            }
        }
    }
}
<?php

namespace App\classes\parsers;

use App\classes\logging\LoggingAdapter;
use App\classes\Parser;
use App\classes\PDOAdapter;
use DiDom\Exceptions\InvalidSelectorException;
use PDOException;
use Redis;
use RedisException;

class CharParser implements ParserInterface
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

            $arrayOfAnchors = Parser::parseArrayOfElementsFromDocument($doc, 'ul.dnrg');

            self::insertCharactersFromAnchors($arrayOfAnchors);

            //

        } catch (RedisException|PDOException $exception2) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception2->getMessage(), 'number' => $exception2->getLine(), 'class' => self::class]
            );
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'notice',
                'An PDO Error occurred while processing "{value}. Pushing back to queue"',
                ['value' => $record]
            );
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->lPush('url', $record);
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
                        $character,
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
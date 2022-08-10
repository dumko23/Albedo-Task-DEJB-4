<?php

namespace App\classes;

use App\classes\logging\LoggingAdapter;
use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use HTTP_Request2;
use HTTP_Request2_Exception;
use HTTP_Request2_LogicException;
use PDOException;
use Redis;
use RedisException;

class Parser
{
    public static array $pidList = [];
    public static bool $parse = true;

    public static Redis $redis;

    /**
     * Initial Parser method - initializing logger to work with, also can be used to drop & create DB in MYSQL
     * (uncomment appropriate line)
     *
     * @return  void
     */
    public static function initializeParser(): void
    {
        LoggingAdapter::initializeLogger();

        LoggingAdapter::logOrDebug(
            LoggingAdapter::$logInfo,
            'info',
            'Initializing Parser...'
        );
        try {
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Performing Redis connection...'
            );
            self::$redis = new Redis();
            self::$redis->connect('redis-stack');

            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Success...'
            );

            if (self::$redis->lLen('url') === 0) {
                self::$redis->rPush('url', $_ENV['URL'] . '|CharParser');
            }
            if($_ENV['FRESH_PARSE'] === 'true'){
                Parser::dropNCreate(); // To initialize fresh tables
                LoggingAdapter::logOrDebug(
                    LoggingAdapter::$logInfo,
                    'info',
                    'Parse done.'
                );
            }

        } catch (RedisException|Exception|PDOException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class, 'record' => 'Initialization']
            );
        }

    }

    /**
     * Drop existing tables connected to task and create fresh ones
     *
     * @return  void
     */
    public static function dropNCreate(): void
    {
        PDOAdapter::dropTables();
        PDOAdapter::createTables();
    }

    /**
     * Create a new instance of DiDom\Document from given url
     *
     * @param  string  $url  url to create from
     * @return  Document
     */
    public static function createNewDocument(string $url, string $record): Document
    {
        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'Creating new document from {url}',
            ['url' => $url]
        );
        try {
            $request = new HTTP_Request2($url, HTTP_Request2::METHOD_GET, [
                'proxy' => 'obfs4-bridge:9050',
            ]);
            $response = $request->send();
            if (200 == $response->getStatus()) {
                $body = $response->getBody();

                $doc = new Document($body);
                return $doc;
            } else {
                LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                    'info',
                    'Unexpected HTTP status: {status} {reason}',
                    ['status' => $response->getStatus(), 'reason' => $response->getReasonPhrase()]
                );
            }
        } catch (HTTP_Request2_LogicException|HTTP_Request2_Exception|Exception $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => $exception->getErrorClass(), 'record' => $record]
            );
            // Returning URL back to the queue
            Parser::$redis = new Redis();
            Parser::$redis->connect('redis-stack');
            Parser::$redis->rPush('url', $record);

            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Record {record} pushed back to the queue after an Error occurred.',
                ['record' => $record]
            );
        }
    }

    /**
     * Parse DiDom\Document to find a needle element
     *
     * @param  Document  $doc   document where you're searching in
     * @param  string  $needle  an element that you're searching for
     * @return  Element[]|\DOMElement[]|void
     */
    public static function parseArrayOfElementsFromDocument(Document $doc, string $needle)
    {
        try {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                'Searching for "{needle}"...',
                ['needle' => $needle]
            );
            return $doc->find($needle)[0]
                ->find('a');
        } catch (InvalidSelectorException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Processing $result parameter for duplicate entries of  given element (letter, interval or question)
     *
     * @param  string  $tableName    table searched in
     * @param  string  $whereValue   element value searched for
     * @param  string|bool  $result  resulting array from search process
     * @param  string  $field        element searched for (letter, interval or question)
     * @return  bool
     */
    public static function checkForDuplicateEntries(string $tableName, string $whereValue, string|bool $result, string $field): bool
    {
        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            LoggingAdapter::$logMessages['checkDuplicate'],
            ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
        );
        if ($result === false) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onNotFound'],
                ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
            );

            return false;
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onFound'],
                ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
            );
            return true;
        }
    }

    /**
     * Performing general parse process. Creates forked processes which calls special parsers specified
     * in Redis queue records. Maximum of created forks can be adjusted in .env file. Forks count
     * is checked in semi-infinite loop. Exits if there is no URL in Redis queue or on SIGTERM signal received,
     * closing all forked threads
     *
     * @return  void
     */
    public static function doParse(): void
    {
        try {
            self::$parse = true;
            if (self::$redis->lIndex('url', 0) === $_ENV['URL'] . '|CharParser') {
                self::doJob(self::$redis->lPop('url'));
            }

            // Handling parent termination signal and sending it to children
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function ($signal) {
                if ($signal === SIGTERM) {
                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Closing forks...'
                    );
                    self::$parse = false;
                    if (count(self::$pidList) !== 0) {
                        foreach (self::$pidList as $key => $pid) {

                            LoggingAdapter::logOrDebug(
                                LoggingAdapter::$logInfo,
                                'info',
                                'Killing pid: {pid}...',
                                ['pid' => $pid]
                            );
                            posix_kill($pid, SIGTERM);
                            unset(self::$pidList[$key]);
                        }
                    }

                    // Waiting till all children exited
                    while (pcntl_waitpid(0, $status) != -1) ;

                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Exiting parser...'
                    );
                    exit();
                }
            });

            while (self::$parse) {

                self::$redis = new Redis();
                self::$redis->connect('redis-stack');

                // Checking if there are any exited processes
                if (count(self::$pidList) !== 0) {
                    foreach (self::$pidList as $key => $pid) {
                        $res = pcntl_waitpid($pid, $status, WNOHANG);
                        // If the process has already exited
                        // Unsetting exited process from pidList
                        if ($res == -1 || $res > 0) {
                            LoggingAdapter::logOrDebug(
                                LoggingAdapter::$logInfo,
                                'info',
                                'Unsetting pid: {pid}',
                                ['pid' => $pid]
                            );
                            unset(self::$pidList[$key]);
                        }
                    }
                }

                // Skipping iteration if pidList is full or queue is empty while pidList isn't
                if (count(self::$pidList) === intval($_ENV['THREAD_NUM'])) {
                    continue;
                } else if (count(self::$pidList) !== 0 && (self::$redis->lLen('url') === 0 || self::$redis->lLen('url') === false)) {
                    continue;
                }

                // Breaking loop if there is no URL in queue and pidList is empty
                if (count(self::$pidList) === 0 && self::$redis->lLen('url') === 0) {
                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Breaking...'
                    );
                    static::$parse = false;
                    break;
                }

                // Forking
                $pid = pcntl_fork();

                if ($pid == -1) {
                    // If fork failed
                    LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                        'error',
                        'Error forking...'
                    );
                    exit();
                } else if ($pid) {
                    // Parent process after success fork
                    self::$pidList[] = $pid;

                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Forking pid: {pid}',
                        ['pid' => $pid]
                    );
                } else {
                    // Child process after success fork
                    LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                        'info',
                        'Executing fork'
                    );


                    //
                    self::$redis = new Redis();
                    self::$redis->connect('redis-stack');
                    $record = self::$redis->lPop('url');

//                    sleep(rand(1, 5));
                    Parser::doJob($record);
                    //

                    LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                        'info',
                        "Job done on URL: {url}",
                        ['url' => $record]
                    );
                    exit();
                }
            }
            LoggingAdapter::logOrDebug(
                LoggingAdapter::$logInfo,
                'info',
                'Parse done.'
            );

            exit();

        } catch (RedisException|Exception $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                "error",
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class, 'record' => 'Main process']
            );
        }
    }

    /**
     * Performing specific parse processes for intervals, questions and answers using ClassName specified in $record.
     *
     * @param  string|bool  $record  Redis queue record type of "url-to-parse|ClassName"
     * @return  void
     */
    public static function doJob(string|bool $record): void
    {
        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'Starting to process record: "{record}".',
            ['record' => $record]
        );
        if ($record !== false){
            $array = explode('|', $record);
            $url = $array[0];
            $className = $array[1];
            $parser = "App\classes\parsers\\$className";
            $parser::parse($url, $record);
        }
    }


}

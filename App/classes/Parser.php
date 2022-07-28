<?php

namespace App\classes;

use App\classes\logging\LoggingAdapter;
use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use PDO;
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

        } catch (RedisException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }

//        Parser::dropNCreate(); // To initialize fresh tables
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
    public static function createNewDocument(string $url = ''): Document
    {
        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            'Creating new document from {url}',
            ['url' => $url]
        );
        try {
            return new Document($url, true);
        } catch (Exception $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                'error',
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Parse DiDom\Document to find a needle element
     *
     * @param  Document  $doc  document where you're searching in
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
     * @param  string  $tableName  table searched in
     * @param  string  $whereValue  element value searched for
     * @param  array  $result  resulting array from search process
     * @param  string  $field  element searched for (letter, interval or question)
     * @return  bool
     */
    public static function checkForDuplicateEntries(string $tableName, string $whereValue, array $result, string $field): bool
    {
        LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
            'info',
            LoggingAdapter::$logMessages['checkDuplicate'],
            ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
        );
        if (isset($result[0])) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onFound'],
                ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
            );
            return false;
        } else {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                'info',
                LoggingAdapter::$logMessages['onNotFound'],
                ['table' => $tableName, 'field' => $field, 'value' => $whereValue]
            );
            return true;
        }
    }

    /**
     * Perform general parse process. Includes creating documents from given url, inserting characters to DB,
     * checking for duplicates and performing multithreading processes
     *
     * @return  void
     * @throws RedisException
     */
    public static function doParse(): void
    {
        try {
            if (self::$redis->lIndex('url', 0) === $_ENV['URL'] . '|CharParser') {
                self::doJob(self::$redis->lPop('url'));
            }

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

                    while(pcntl_waitpid(0, $status) != -1);

                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Exiting parser...'
                    );
                    exit();
                }
            });

            while (self::$parse) {
                // TODO

                self::$redis = new Redis();
                self::$redis->connect('redis-stack');


                if (count(self::$pidList) !== 0) {
                    foreach (self::$pidList as $key => $pid) {
                        $res = pcntl_waitpid($pid, $status, WNOHANG);
                        // If the process has already exited

                        LoggingAdapter::logOrDebug(
                            LoggingAdapter::$logInfo,
                            'info',
                            'Checking pid: {pid} - result: {res} ',
                            ['pid' => $pid, 'res' => $res]
                        );
                        if ($res == -1 || $res > 0) {
                            echo "Unsetting $pid..." . PHP_EOL;
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

                LoggingAdapter::logOrDebug(
                    LoggingAdapter::$logInfo,
                    'info',
                    'Pid list length: ' . count(self::$pidList)
                );

                if (count(self::$pidList) === intval($_ENV['THREAD_NUM'])) {
                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Continue...'
                    );
                    continue;
                }

                if (count(self::$pidList) === 0 && self::$redis->lLen('url') === 0) {
                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Breaking...'
                    );
                    static::$parse = false;
                }


                $pid = pcntl_fork();


                if ($pid == -1) {
                    LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                        'error',
                        'Error forking...'
                    );
                    exit();
                } else if ($pid) {
//                    PDOAdapter::forceCloseConnectionToDB();
                    self::$pidList[] = $pid;

                    LoggingAdapter::logOrDebug(
                        LoggingAdapter::$logInfo,
                        'info',
                        'Forking pid: {pid}',
                        ['pid' => $pid]
                    );
                    // parent node

                } else {
                    LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                        'info',
                        'Executing fork'
                    );


                    //
                    self::$redis = new Redis();
                    self::$redis->connect('redis-stack');
                    $record = self::$redis->lPop('url');
                    Parser::doJob($record);
                    //
                    LoggingAdapter::logOrDebug(LoggingAdapter::$logInfo,
                        'info',
                        "Job done on pid: $pid"
                    );
                    break;

                }
//                    while(pcntl_waitpid(0, $status) != -1);


            }
            exit();

        } catch (InvalidSelectorException $exception) {
            LoggingAdapter::logOrDebug(LoggingAdapter::$logError,
                "error",
                LoggingAdapter::$logMessages['onError'],
                ['message' => $exception->getMessage(), 'number' => $exception->getLine(), 'class' => self::class]
            );
        }
    }

    /**
     * Performing specific parse processes for intervals, questions and answers.
     *
     * @param  string  $record  Redis queue record
     * @return  void
     */
    public static function doJob(string $record): void
    {
        echo $record . PHP_EOL;
        $array = explode('|', $record);
        $url = $array[0];
        $className = $array[1];
        $parser = "App\classes\parsers\\$className";
        $parser::parse($url);
    }


}

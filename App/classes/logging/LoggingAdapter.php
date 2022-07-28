<?php

namespace App\classes\logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class LoggingAdapter
{
    public static Logger $logInfo;
    public static Logger $logError;
    public static Logger $debugLogger;
    public static array $logMessages = [
        'onInsert' => 'Adding row to "{table}" table. Value "{field}": "{value}"...',
        'successInsert' => 'Successfully added to "{table}". Value "{field}": "{value}".',
        'onError' => 'Got error with message "{message}" at line "{number}" in Class "{class}".',
        'onPDOError' => 'Got PDO error with message "{message}" at line "{number}" in Class "{class}".',
        'onSelect' => 'Trying to get "{something}" = "{value}" from table "{table}"...',
        'onSelectAnswer' => 'Trying to get "answer" = "{answerValue}" with question_id = "{questionIdValue}" from table "{table}"...',
        'answerFound' => 'Field "answer" with values "{answerValue}" and "{questionIdValue}" is already exist in "{table}" table! Skipping insert...',
        'answerNotFound' => 'Field "answer" with values "{answerValue}" and "{questionIdValue}" is not found in "{table}" table! Performing insert...',
        'onSkip' => 'Field "{field}": "{value}". Skipped!',
        'checkDuplicate' => 'Checking for duplicate entry for value "{field}": "{value}" in "{table}" table...',
        'onFound' => 'Field  "{field}": "{value}" is already exist in "{table}" table! Skipping insert...',
        'onNotFound' => 'Field with value "{field}": "{value}" is not found in "{table}" table! Performing insert..'
    ];

    /**
     * Registering loggers to work with
     *
     * @return  void
     */
    public static function initializeLogger(): void
    {
        $dateFormat = "Y-m-d H:i:s";
        $output = "%datetime% > %channel%.%level_name% > %message%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        $infoStream = new StreamHandler(__DIR__ . '/logs/log_file.log', Level::Info);
        $infoStream->setFormatter($formatter);
        $errorStream = new StreamHandler(__DIR__ . '/logs/log_file.log', Level::Error);
        $errorStream->setFormatter($formatter);
        $debugStream = new DebugLogger();
        $debugStream->setFormatter($formatter);

        static::$logInfo = new Logger('parser_info');
        static::$logInfo->pushHandler($infoStream);
        static::$logInfo->pushProcessor(new PsrLogMessageProcessor());

        static::$logError = new Logger('parser_errors');
        static::$logError->pushHandler($errorStream);
        static::$logError->pushProcessor(new PsrLogMessageProcessor());

        static::$debugLogger = new Logger('parser_debug');
        static::$debugLogger->pushHandler($debugStream);
        static::$debugLogger->pushProcessor(new PsrLogMessageProcessor());

        static::logOrDebug(static::$logInfo, 'info', 'Initializing logger');
    }

    /**
     * Performing logging process according to DEBUG_MODE in .env
     *
     * @param  Logger  $logger  logger instance to log in normal mode
     * @param  string  $logMethod  log method to write in log stream (info / error)
     * @param  array|string  $logMessage  log message to write in log stream (prepared or custom string)
     * @param  array  $params  array of parameters handled by PsrLogMessageProcessor
     * @return  void
     */
    public static function logOrDebug(Logger $logger, string $logMethod, array|string $logMessage, array $params = []): void
    {
        if ($_ENV['DEBUG_MODE'] === "ON") {
            LoggingAdapter::$debugLogger->$logMethod($logMessage, $params);
        } else if ($_ENV['DEBUG_MODE'] === "OFF") {
            $logger->$logMethod($logMessage, $params);
        }
    }
}
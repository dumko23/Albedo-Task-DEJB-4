<?php

namespace App;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class DebugLogger extends AbstractProcessingHandler
{
    /**
     * Inheriting parent construct logic to initialize handler
     *
     * @param int|string|Level $level - level of message significance
     * @param bool $bubble
     */
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * Main method that describes the logic of logging performed by this handler
     *
     * @param LogRecord $record - message, content and extra data of log message
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        echo $record->formatted;

        sleep(2);
    }
}
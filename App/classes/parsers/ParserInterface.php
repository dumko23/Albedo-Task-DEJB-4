<?php

namespace App\classes\parsers;

interface ParserInterface
{
    /**
     * Parses HTML document from given URL
     *
     * @param  string  $url  URL of type "url-to-parse|ClassName"
     * @param  string  $record  Redis record to send back in queue in specific case
     * @return void
     */
    public static function parse(string $url, string $record): void;

}
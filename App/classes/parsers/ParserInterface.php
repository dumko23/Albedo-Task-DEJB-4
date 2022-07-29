<?php

namespace App\classes\parsers;

interface ParserInterface
{
    /**
     * Parses HTML document from given URL
     *
     * @param  string  $url  URL of type "url-to-parse|ClassName"
     * @return void
     */
    public static function parse(string $url): void;

}
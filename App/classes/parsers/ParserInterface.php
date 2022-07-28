<?php

namespace App\classes\parsers;

interface ParserInterface
{
    /**
     * Parses HTML document from given URL
     *
     * @param  string  $url
     * @return void
     */
    public static function parse(string $url): void;

}
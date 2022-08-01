<?php


require 'vendor/autoload.php';

use App\classes\Parser;

use Dotenv\Dotenv;


$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


Parser::initializeParser();

Parser::doParse();


<?php


require 'vendor/autoload.php';

use App\Parser;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();



Parser::initializeParser();

Parser::doParse();

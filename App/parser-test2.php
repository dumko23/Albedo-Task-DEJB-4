<?php


require 'vendor/autoload.php';

use App\Parser;
use App\PDOAdapter;
use DiDom\Exceptions\InvalidSelectorException;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
echo '~//~~//~~//~~//~' . PHP_EOL .
    '~//~~//~~//~~//~' . PHP_EOL .
    date("Y-m-d H:i:s") . "Initializing parse process. Number of threads {$_ENV['THREAD_NUM']}." . PHP_EOL .
    '~//~~//~~//~~//~' . PHP_EOL .
    '~//~~//~~//~~//~' . PHP_EOL;
//Parser::dropNCreate(); // To initialize fresh tables

$mainDocument = Parser::createNewDocument();
$arrayOfCharacterAnchors = Parser::parseArrayOfElementsFromDocument($mainDocument, '.dnrg');

Parser::insertCharactersFromAnchors($arrayOfCharacterAnchors);


try {
    $chunkedArray = array_chunk($arrayOfCharacterAnchors, ceil(count($arrayOfCharacterAnchors) / $_ENV['THREAD_NUM']));
    $arrayLength = count($chunkedArray);
    echo "Creating {$_ENV['THREAD_NUM']} threads of execution!" . PHP_EOL;
    echo "Array chunked into $arrayLength parts!" . PHP_EOL;

    for ($j = 0; $j < count($chunkedArray); $j++) {
        $subArray = $chunkedArray[$j];

        $pid = pcntl_fork();

        if ($pid == -1) {
            exit("Error forking...\n");
        } else if (!$pid) {
            // make new connection in the child process.
            $db = PDOAdapter::forceCreateConnectionToDB($j);
            echo "Executing fork #$j" . PHP_EOL;
            for ($i = 0; $i < count($subArray); $i++) {
                $anchor = $subArray[$i];
                if (strlen($anchor->getAttribute('href')) === 1) {
                    //
                    doJob($anchor, $db);
                    //
                }
            }
            exit;
        } else {
            // parent node
            PDOAdapter::forceCloseConnectionToDB();
        }
    }
    while (pcntl_waitpid(0, $status) != -1) ;
} catch (InvalidSelectorException $exception) {
    echo PHP_EOL;
    echo date("Y-m-d H:i:s") . ". " . $exception->getMessage() . " at line " . $exception->getLine() . PHP_EOL;
}


function doJob($anchor, $db): void
{
    $character = $anchor->getAttribute('href');

    $intervalsPage = Parser::createNewDocument($anchor->getAttribute('href'));

    $listOfIntervals = Parser::parseArrayOfElementsFromDocument($intervalsPage, '.dnrg');

    foreach ($listOfIntervals as $interval) {
        $intervalName = $interval->getAttribute('href');
        Parser::insertIntervalOfAnswers($db, $character, $interval);

        $tableOfQuestions = Parser::createNewDocument($intervalName);

        $arrayOfQuestions = Parser::makeArrayFromTable($tableOfQuestions, 'tbody');

        foreach ($arrayOfQuestions as $link => $question) {

            $answerPage = Parser::createNewDocument($link);
            Parser::insertQuestionAndAnswer($db, $answerPage, $character, $intervalName);
        }
    }
}

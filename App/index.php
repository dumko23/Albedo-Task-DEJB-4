<?php


require 'vendor/autoload.php';

use App\classes\Parser;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;


$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();




//Parser::initializeParser();
//
//Parser::doParse();


//$request = new HTTP_Request2('https://www.kreuzwort-raetsel.net/b', HTTP_Request2::METHOD_GET, ['proxy' => 'obfs4-bridge:9050']);
//try {
//    $response = $request->send();
//    if (200 == $response->getStatus()) {
//        $body = $response->getBody();
//
//        $doc = new Document();
//        $result = $doc->loadHtml($body);
//
//        $result = $doc->find('.dnrg', \DiDom\Query::TYPE_CSS)[0]->find('a');
//        echo $result[0]->getAttribute('href');
////        foreach ($result as $interval) {
////            $intervalName = $interval->getAttribute('href');
////            print_r($intervalName);
////        }
//    } else {
//        echo 'Unexpected HTTP status: ' . $response->getStatus() . ' ' .
//            $response->getReasonPhrase();
//    }
//} catch (HTTP_Request2_LogicException|HTTP_Request2_Exception|InvalidSelectorException|Exception $e) {
//    print_r($e->getMessage());
//    echo PHP_EOL;
//}


$client = new Client([

]);
try {
    $response = $client->request('GET', 'https://www.kreuzwort-raetsel.net/', ['verify' => false, 'proxy' =>  'obfs4-bridge:9050']);
    echo $response->getBody();
    $body = $response->getBody();

    $doc = new Document($body, true);

    $result = $doc->find('.dnrg', \DiDom\Query::TYPE_CSS)[0];
    print_r($result);
} catch (GuzzleException $e) {
    print_r($e->getMessage());
    echo PHP_EOL;
} catch (InvalidSelectorException $e) {
    print_r($e->getMessage());
    echo PHP_EOL;
}

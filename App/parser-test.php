<?php
//require 'vendor/autoload.php';
//
//use DiDom\Document;
//use DiDom\Exceptions\InvalidSelectorException;
//use DiDom\Query;
//use App\PDOAdapter;
//
//PDOAdapter::dropTables();
//PDOAdapter::createTables();
//
//$document = createNewDocument();
//
//try {
//    $content = getArrayOfLinks($document, '.dnrg');
//// 1
//} catch (InvalidSelectorException $e) {
//    print_r($e);
//}
//
//
//$arrayOfAnchors = [];
//foreach ($content as $anchor) {
//    $href = createNewDocument($anchor->getAttribute('href'));
//    try {
//        $seiteLinks = getArrayOfLinks($href, '.dnrg'); // returns array of Seite links
//        PDOAdapter::insertCharToDB($anchor->getAttribute('href')); // adds letters to db
//
//        $arrayOfSeite = getSeite($seiteLinks, $anchor->getAttribute('href'));
//
//
//        $arrayOfAnchors[$anchor->getAttribute('href')] = $arrayOfSeite;
//
//        foreach ($arrayOfAnchors[$anchor->getAttribute('href')] as $intervalName => $seite) {
//            echo $seite . PHP_EOL;
//            foreach (array_keys($seite) as $key) {
//                $questionPage = createNewDocument($key);
//
//                PDOAdapter::insertQuestionToDB(
//                    intval(PDOAdapter::getCharIdFromDB($anchor->getAttribute('href'))[0]['char_id']),
//                    PDOAdapter::getIntervalIdFromDB($intervalName)[0]['interval_id'],
//                    $questionPage->find('#HeaderString')[0]->innerHtml()
//                );
//
//                $answer = $questionPage->find('.Answer')[0]->firstChild()->getNode()->textContent;
//
//                PDOAdapter::insertAnswerToDB(
//                    PDOAdapter::getQuestionIdFromDB(
//                        $questionPage
//                            ->find('#HeaderString')[0]
//                            ->innerHtml())[0]['question_id'],
//                    $answer,
//                    strlen($answer)
//                );
//            }
//        }
//// 2
//    } catch (InvalidSelectorException $e) {
//        echo PHP_EOL;
//        print_r($e);
//    }
//
//}
////echo '<pre>';
////print_r($arrayOfAnchors);
////echo '</pre>';
//$filename = 'content.txt';
//$file = fopen($filename, 'w+');
//
////file_put_contents($filename, json_encode($arrayOfAnchors));
//
//
//print_r(json_decode(file_get_contents($filename), TRUE));
//fclose($file);
//
//function putInFile($filename, $question, $answer): void
//{
//    file_put_contents($filename, $question . " => " . $answer . "\r\n");
//}
//
//
//function getSeite($links, $a): array
//{
//        $array = [];
//        foreach ($links as $href) {
//
//            PDOAdapter::insertIntervalToDB(
//                intval(PDOAdapter::getCharIdFromDB($a)[0]['char_id']),
//                $href->getAttribute('href')
//            );
//
//            $array[$href->getAttribute('href')] = getAnswerTable(createNewDocument($links[0]->getAttribute('href')));
//        }
//        return $array;
//    }
//
//function getArrayOfLinks($doc, $needle) // 3
//{
//
//    return $doc->find($needle, Query::TYPE_CSS)[0]
//        ->find('a', Query::TYPE_CSS);
//}
//
//function getArrayOfKeys($doc, $needle): array
//{
//    $result = $doc->find($needle, Query::TYPE_CSS)[0]
//        ->find('a', Query::TYPE_CSS);
//    $resultArray = [];
//    for ($i = 0; $i < count($result); $i = $i + 2) {
////        echo $result[$i]->getAttribute('href');
//        $resultArray[$result[$i]->getAttribute('href')] = $result[$i + 1]->innerHtml();
//    }
//
//    return $resultArray;
//}
//
//function getAnswerTable($link): array
//{
//    return getArrayOfKeys($link, 'tbody');
//}
//
//function createNewDocument($href = ''): Document
//{
//    return new Document('https://www.kreuzwort-raetsel.net/' . $href, true);
//}
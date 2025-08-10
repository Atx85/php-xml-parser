<?php
include "xml-parse.php";
$path = "./tests/assets/example.xml";

$src = file_get_contents($path);
$parser = new XMLParse($src);
$parser->encode();

$dom = new \DOMDocument('1.0');
$dom->preserveWhiteSpace = true;
$dom->formatOutput = true;
$dom->loadXML($parser->decode());
$prettyGenerated = $dom->saveXML();

$dom->loadXML($src);
$prettySrc = $dom->saveXML();

$res = $path;
if (strcmp($prettySrc, $prettyGenerated) === 0) {
  $res .= " [PASS]\n";
} else {
  $res .= " [FAIL]\n";
}
echo $res;

// $parser = new XMLParser("<test />");

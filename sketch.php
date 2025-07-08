<?php
include "./xml-parse.php";

$parser = new XMLParser(file_get_contents("./example.xml"));
$parser->encode();
$parsed = $parser->tree;
echo $parser->decode();

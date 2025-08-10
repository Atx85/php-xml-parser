<?php
include "xml-parse.php";
$paths = [
  "./tests/assets/example.xml",
  "./tests/assets/self-closing.xml",
  "./tests/assets/self-closing-min.xml",
  "./tests/assets/hidden-self-closing.xml",
];

function test ($path) {
  $src = file_get_contents($path);
  if (!$src) return "could not find: $path\n";
  
  $parser = new XMLParse($src);
  $parsed = $parser->encode();
  $parsed = $parser->decode();
  $parsed = str_replace("\n", "", $parsed);
  $parsed = str_replace(" ", "", $parsed);

  $src = str_replace("\n", "", $src);
  $src = str_replace(" ", "", $src);

  $res = $path;
  if (strcmp($src, $parsed) === 0) {
    $res .= " [PASS]\n";
  } else {
    $res .= " [FAIL]\n";
  }
  echo $res;
}
foreach ($paths as $path) {
  test($path);
}

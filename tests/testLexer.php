<?php
include "lexer.php";
$cases = [
  ["<?xml version=\"1.0\" encoding=\"UTF-8\"?>", "[<?: '<?']\n[name: 'xml']\n[name: 'version']\n[=: '=']\n[string: '1.0']\n[name: 'encoding']\n[=: '=']\n[string: 'UTF-8']\n[?>: '?>']\n"],
  ["<test />", "[<: '<']\n[name: 'test']\n[/>: '/>']\n"],
  ["<test>foo-bar:with special chars</test>", "[<: '<']\n[name: 'test']\n[>: '>']\n[name: 'foo']\n[unknown: '-']\n[name: 'bar:with']\n[name: 'special']\n[name: 'chars']\n[</: '</']\n[name: 'test']\n[>: '>']\n"],
  ["<test></test>","[<: '<']\n[name: 'test']\n[>: '>']\n[</: '</']\n[name: 'test']\n[>: '>']\n"],
  ["<test foo=\"bar\"></test>","[<: '<']\n[name: 'test']\n[name: 'foo']\n[=: '=']\n[string: 'bar']\n[>: '>']\n[</: '</']\n[name: 'test']\n[>: '>']\n"],
  ["<test foo=\"bar\" hello=\"world\"></test>","[<: '<']\n[name: 'test']\n[name: 'foo']\n[=: '=']\n[string: 'bar']\n[name: 'hello']\n[=: '=']\n[string: 'world']\n[>: '>']\n[</: '</']\n[name: 'test']\n[>: '>']\n"],
];

function test ($case) {
  $l = new Lex($case[0]);
  $res = "";
  $next = $l->next();
  while (!$next || $next->type !== Type::EOF) {
    $res .= $next;
    $next = $l->next();
  } 
  if($case[1] !==  $res) {
    var_dump($res);
    die('fail');
  }
  echo $case[0] . ": PASS \n";
}

foreach ($cases as $case) test($case);

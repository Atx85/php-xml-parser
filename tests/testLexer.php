<?php
include "lexer.php";
$cases = [
  ["<test />", "[<: '<']\n[name: 'test']\n[symbol: '/>']\n"],
  ["<test></test>","[<: '<']\n[name: 'test']\n[>: '>']\n[</: '</']\n[name: 'test']\n[>: '>']\n"],
  ["<test foo=\"bar\"></test>","[<: '<']\n[name: 'test']\n[name: 'foo']\n[=: '=']\n[string: 'bar']\n[>: '>']\n[</: '</']\n[name: 'test']\n[>: '>']\n"],
  ["<test foo=\"bar\" hello=\"world\"></test>","[<: '<']\n[name: 'test']\n[name: 'foo']\n[=: '=']\n[string: 'bar']\n[name: 'hello']\n[=: '=']\n[string: 'world']\n[>: '>']\n[</: '</']\n[name: 'test']\n[>: '>']\n"],
];

function test ($case) {
  $l = new Lex($case[0]);
  $res = "";
  $next = $l->next();
  while ($next) {
    $res .= $next;
    $next = $l->next();
  } 
  if($case[1] !==  $res) {
    var_dump($case[1], $res);
    die();
  }
  echo $case[0] . ": PASS \n";
}

foreach ($cases as $case) test($case);

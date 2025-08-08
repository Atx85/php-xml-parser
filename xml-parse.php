<?php
/*
    Copyright (C) 2025, Attila Banko
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/
include "./lexer.php";
class XMLParser {
  private $lexed;
  public $tree;
  public const OPEN_TAG = "open";
  public const CLOSE_TAG = "close";
  public const TAG_VALUE = "value";
  protected $next;
  private $index = 0;
 
  public function __construct($src) 
  {
    $this->lexed = new Lex($src);
    $this->tree = ["xml" => [], "root" => []];
  }
  protected function expect(&$lex, $tokenType, $value = null) {
    $peeked = $lex->peekToken();
    if (!$peeked) return false;
    if ($peeked->type !== $tokenType) return false;
    if ($value && $peeked->value !== $value) return false;
    $lex->next();
    return $peeked;
  }
  protected function parseXMLInfo (&$lex) {
    $this->expect($lex, Type::OPEN_XML);
    $this->expect($lex, Type::NAME, "xml");
    $isCloseXML = $this->expect($lex, Type::CLOSE_XML);
    while (!$isCloseXML) {
      $name =  $this->expect($lex, Type::NAME);
      $this->expect($lex, Type::EQUAL);
      $val = $this->expect($lex, Type::STRING);
     if ($name && $val) {
        $this->tree["xml"][$name->value] = $val->value;
      }
      $isCloseXML = $this->expect($lex, Type::CLOSE_XML);
    }
  }

  protected function getTagParams(&$lex, &$root) {
    $next = $lex->next();
    $nextValue = $next->value; // this is the tag name
     $params = [];
     while ($nextValue !== ">") {
        // x=y
        $lex->next(); // removing =
        $value = $lex->next();
        if ($value) {
           $params[$nextValue] = $value->value;
           $nextValue = $lex->next()->value;
        } else {
          break;
        }
     }
     if ($params) {
       $root["params"] = $params;
     }
     return $lex->next();
  }

  protected function getTagValue(&$lex, &$root) {
    $next = $this->next;
    if ($next && ($next->value === "<" || $next->value === "</")) return;// if it is an opening tag it is likely not a value    
    $valueChunks = [];
    while ($next->value && $next->value !== "</") {
      $valueChunks[] = $next->value;
      $next = $lex->next();
    } 
    $this->next = $next;
    $root["value"] = implode(' ', $valueChunks);
    $root["type"] = self::TAG_VALUE; 
  }

  protected function all($lex) {
    $all = [];
    $next = $this->parsetag2($lex);
    $keyChain = [];
    while ($next) {
      if ($next["type"] !== self::CLOSE_TAG) {
        $keyChain[] = $next["name"];
        $next["parent"] = count($keyChain) > 1 ? $keyChain[count($keyChain) - 2] : '';
        $all[$next["name"]] = $next;
      } else {
        array_pop($keyChain);
      }
      $next = $this->parsetag2($lex);
    }
    return $all;
  }
  protected function parseTags($all, &$_i ) {
    $res = [];
    // this recursion is taken from: https://www.geeksforgeeks.org/javascript/build-tree-array-from-flat-array-in-javascript/
    foreach ($all as $allKey => $item) {
     if (isset($item["parent"]) && !$item["parent"]) $res[] = &$all[$allKey];
      else {
        $all[$item["parent"]]["children"][] = &$all[$item["name"]];
      }
    }
    return $res;
  }

  protected function parseOpenTag($lex) {
    $next = $this->next;
    if ($next && $next->value !== "<") return false;
    $tagName = $lex->next(); 
    if (!$tagName) return false;
    $root = [
        "name" => $tagName->value,
        "type" => self::OPEN_TAG
    ];
    $next = $this->getTagParams($lex, $root);
    $this->next = $next;
    return $root;  
  }
  protected function parseCloseTag($lex) {
    $next = $this->next;
    if ($next && $next->value === "</") {
      $closingTagName = $lex->next();
      $root = [
              "name" => $closingTagName->value ,
              "type" => self::CLOSE_TAG
      ];

      $next = $lex->next(); // this is the ending >
      $next = $lex->next(); // setting the new next to < or similar --- maybe this should go outsise?
      $this->next = $next;
      return $root;
    }
  }
 protected function parseTag2(
    $lex, 
    &$root = null, 
  ) 
 {
    if (!$this->next) {
      $this->next = $lex->next();
    }
    $res = $this->parseOpenTag($lex);
    if ($res) {
      $key = array_keys($res)[0];
      $this->getTagValue($lex, $res);
      return $res;
    }
    return  $this->parseCloseTag($lex);
  }

  public function encode() {
    $root = [];
    $this->parseXMLInfo($this->lexed);
    $i = 0;
    $this->tree["root"] = $this->parseTags($this->all($this->lexed), $i);
  }

  public function decodeTag(&$out, $root) {
    foreach($root as $key => $tag) {
      $out .= '<' . $tag["name"];
      $params = $tag['params'] ?? '';
      if (is_array($params)) {
        foreach($params as $var => $value) {
          $out .= ' ' . $var . '="' . $value . '"';
        }
      }
      $out .= '>';
      if (isset($tag["children"])) {
        if ($tag['children']) {
          $this->decodeTag($out, $tag['children']);
        }
      }
      if (isset($tag['value'])) {
        $out .= $tag['value'];
      }
      $out .= '</' . $tag["name"] . '>';
    }
    
  }

  public function decode() {
    $out = '';
      $this->decodeTag($out, $this->tree['root']);
    echo($out);
  }
}


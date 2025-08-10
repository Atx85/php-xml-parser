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

class XMLParse {
  private $lexed;
  public $tree;
  public const OPEN_TAG = "open";
  public const SELF_CLOSE_TAG = "self-closing";
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
    $isOpenXml = $this->expect($lex, Type::OPEN_XML);
    if (!$isOpenXml) return false;
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

  protected function getTagValue(&$lex) {
      $begin = $lex->cur;
      $end = $begin;
      $peeked = $lex->peekToken();
      // <tag>value</tag> 
      // <tag><anotherTag> 
      // <tag></tag>
      // <tag><selfClosing />
      while ($peeked->type !== Type::EOF && $peeked->type !== Type::OPEN_CLOSE_TAG && $peeked->type !== Type::OPEN_ANGLE) {
        $end = $lex->cur;
        $peeked = $lex->next();
      }
     return substr($lex->context, $begin, $end - $begin);
  }

  protected function all($lex) {
    $all = [];
    $next = $this->parseTag($lex);
    $keyChain = [];
    while ($next) {
      if ($next["type"] === self::SELF_CLOSE_TAG) {
        $next["parent"] = count($keyChain) > 0 ? $keyChain[count($keyChain) - 1] : '';
        $all[$next["name"]] = $next;
      } else {
        if ($next["type"] !== self::CLOSE_TAG) {
          $keyChain[] = $next["name"];
          $next["parent"] = count($keyChain) > 1 ? $keyChain[count($keyChain) - 2] : '';
          $all[$next["name"]] = $next;
        } else {
          array_pop($keyChain);
        }
      }
      $next = $this->parseTag($lex);
    }
    return $all;
  }
  protected function parseTags($all, &$_i ) {
    $res = [];
    // this recursion is taken from: https://www.geeksforgeeks.org/javascript/build-tree-array-from-flat-array-in-javascript/
    foreach ($all as $allKey => $item) {
      if (isset($item["parent"]) && !$item["parent"]) $res[] = &$all[$allKey];
      else {
        // if ($all[$item["parent"]]["type"] !== self::SELF_CLOSE_TAG) {
          $all[$item["parent"]]["children"][] = &$all[$item["name"]];
        // }
      }
    }
    return $res;
  }

  protected function parseOpenTag($lex) {
    if (!$this->expect($lex, Type::OPEN_ANGLE)) return false;
    $tagName = $this->expect($lex, Type::NAME);
    if (!$tagName) return false;
    $root = [
      "name" => $tagName->value,
      "type" => self::OPEN_TAG
    ];
    $isCloseAngle = $this->expect($lex, Type::CLOSE_ANGLE);
    $isCloseSelfClose = $this->expect($lex, Type::CLOSE_SELF_CLOSE_TAG);
    $params = [];
    while (!$isCloseAngle && !$isCloseSelfClose) {
      $name =  $this->expect($lex, Type::NAME);
      $this->expect($lex, Type::EQUAL);
      $val = $this->expect($lex, Type::STRING);
      if ($name && $val) {
        $params[$name->value] = $val->value;
      }
      $isCloseAngle = $this->expect($lex, Type::CLOSE_ANGLE);
      $isCloseSelfClose = $this->expect($lex, Type::CLOSE_SELF_CLOSE_TAG);
    }
    if ($isCloseSelfClose) {
      $root["type"] = self::SELF_CLOSE_TAG;
    }
    if ($params) {
      $root["params"] = $params;
    }
    return $root; 
  }

  protected function parseCloseTag($lex) {
    $this->expect($lex, Type::OPEN_CLOSE_TAG);
    $name = $this->expect($lex, Type::NAME);
    $this->expect($lex, Type::CLOSE_ANGLE);
    if (!$name) {
      if ($lex->peekToken()->type === Type::EOF) return false;
      return die('parseCloseTag: "Closing tag name is not set."' . $lex->peekToken());
    }
    $root =  [
      "name" => $name->value,
      "type" => self::CLOSE_TAG
    ];
    return $root;
  }

  protected function parseTag($lex) 
  {
    if ($this->expect($lex, Type::EOF)) return false;
    $res = $this->parseOpenTag($lex);
    // if ($res["type"] !== self::SELF_CLOSE_TAG) {
    if ($res) {
      $res["value"] = $this->getTagValue($lex);
      return $res;
    }
    $close =  $this->parseCloseTag($lex);
    return $close;
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
        if ($tag["type"] === self::SELF_CLOSE_TAG) {
          $out = substr($out, 0, strlen($out) - 1);
          $out .= ' />';
        } else {
          $out .= '</' . $tag["name"] . '>';
        }
      }
  }

  public function decode() {
    $xml = []; 
    if ($this->tree["xml"]) {
      $xml = ["<?xml"];
      foreach ($this->tree["xml"] as $k => $v) {
        $xml[] = $k . "=\"" . $v . "\"";
      } 
      $xml[] = "?>";
    }
    $out = implode(" ", $xml);
      $this->decodeTag($out, $this->tree['root']);
    return $out;
  }
}


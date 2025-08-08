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
define("LINE_FEED_ASCII", 10);
class Type {
  public const NAME = "name";
  public const CLOSE_ANGLE = ">";
  public const OPEN_ANGLE = "<";
  public const OPEN_CLOSE_TAG = "</";
  public const CLOSE_SELF_CLOSE_TAG = "/>"; 
  public const OPEN_XML = "<?"; 
  public const CLOSE_XML = "?>"; 
  public const SYMBOL = "symbol";
  public const TEXT = "text";
  public const STRING = "string";
  public const EQUAL = "=";
  public const NUMBER = "number";
  public const EOF = "EOF";
}
class Token {
  public $type, $value;
  public function __construct($type, $value) {
    $this->type = $type;
    $this->value = $value;
  }
  public function __toString() {
    return "[" . $this->type . ": '" . $this->value . "']\n";
  }
}
class Lex {
  public $context;
  public $cur;
  public  $wordCount;
  public $collected;
  public $tokens;
  public $characterCount;
  public function __construct ($context) 
  {
    $this->context = $context;
    $this->tokens = [];
    $this->cur = 0;
    $this->characterCount = strlen($context);
  }

  private function chop()
  {
    if (!$this->isEOF()) {
      $this->cur++;
    }
  }

  private function trimLeft ()
  {
    while (!$this->isEOF() && 
      ($this->context[$this->cur] === " " 
      || ord($this->context[$this->cur]) === LINE_FEED_ASCII
      || $this->context[$this->cur] === "\n"
      || $this->context[$this->cur] === "\t") 
    ) {
      $this->chop();
    }
  }
  private function isEOF() 
  {
    return ($this->characterCount - $this->cur) < 1 ;
  }
  private function peek () {
    $i = $this->cur;
    return $this->context[$i++];
  }
  private function getCurrent() {
    if (isset($this->context[$this->cur]))
      return $this->context[$this->cur];
    die(strlen($this->context) . ":". $this->cur);
  }
  private function parseOpenAngle() {
    // current is "<"
    $val = $this->getCurrent();
    $this->chop();
    $peeked = $this->peek();
    $token = new Token(Type::OPEN_ANGLE, $val);
    switch ($peeked) {
      case "?": {
          $token->value = $val . $peeked;
          $token->type = Type::OPEN_XML;
          $this->chop();
          break;
      }
      case  "/": {
          $token->type = Type::OPEN_CLOSE_TAG;
          $token->value = $val . $peeked;
          $this->chop();
          break;
      }
    }
    return $token;
  }
  private function parseName() {
    if ($this->isTagName($this->getCurrent())) {
      $start = $this->cur;
      while ($this->isTagName($this->peek())) {
        $this->chop();
      }
      $tagName = substr($this->context, $start, $this->cur - $start);
      return new Token(Type::NAME, $tagName);
    }
  }
  private function isTagName($el) {
    return (ctype_alpha($el) || ctype_digit($el) || $el === ":"); 
  }
  public function peekToken () {
    $next = $this->next();
    if ($next->type !== Type::EOF) {
      $len = strlen($next->value);
      $this->cur = $this->cur - $len;
    } 
    return $next;
  }
  public function next ()
  {
    if ($this->isEOF()) return new Token(Type::EOF, 'EOF');
    $this->trimLeft();
    $name = $this->parseName(); 
    if ($name) {
      return $name;
    }
    switch ($this->getCurrent()) {
      case "<": return $this->parseOpenAngle(); 
      case "/": {
        $val = $this->getCurrent();
        $this->chop(); // /
        $peeked = $this->peek();
        if ($peeked === ">") {
          $val .= $peeked;
          $this->chop(); // >
        }
        return  new Token(Type::SYMBOL, $val);
      }
      case ">": {
        $val = $this->getCurrent();
        $this->chop();
        return  new Token(Type::CLOSE_ANGLE, $val);
      }
      case "=": {
        $val = $this->getCurrent();
        $this->chop();
        return  new Token(Type::EQUAL, $val);
      }
      case "?": {
        $val = $this->getCurrent();
        $this->chop();
        if ($this->peek() === ">") {
          $val .= $this->peek();
          $this->chop();
          return new TOKEN(Type::CLOSE_XML, $val);
        }
      }
      case "\"": {
        $this->chop();
        $start = $this->cur;
        while ($this->getCurrent() !== "\"") {
          $this->chop();
        }
        $str = substr($this->context, $start, $this->cur - $start);
        $this->chop();
        return new Token(Type::STRING, $str);
      }
    }
  }
}


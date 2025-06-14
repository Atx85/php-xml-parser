<?php
define("LINE_FEED_ASCII", 10);
class Type {
  public const SYMBOL = "symbol";
  public const TEXT = "text";
  public const NUMBER = "number";
}
class Token {
  public $type, $value;
  public function __construct($type, $value) {
    $this->type = $type;
    $this->value = $value;
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
    $this->collected = "";
    $this->tokens = [];
    $this->cur = 0;
    $this->wordCount = 0;
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
    return ($this->characterCount - $this->cur) <= 1;
  }
  public function next ()
  {
    if ($this->isEOF()) return false;
    $this->trimLeft();
    $current = $this->context[$this->cur];
    if (ctype_alpha($current) || is_numeric($current))  {
      $index = $this->cur;
      while (!$this->isEOF() 
        && !in_array(
          $this->context[$this->cur],
          [" ", ">", '"', "=", "<", chr(LINE_FEED_ASCII)]
        )
      ) {
        $this->chop();
      }
      $text = substr($this->context, $index, min($this->cur - $index, $this->characterCount - 1));
      return new Token(Type::TEXT, $text);
 
    }

    switch ($current) {
      case "<" : {
          $i = $this->cur; 
          $this->chop();
          switch ($this->context[$this->cur]) {
            case "?":
            case "/":
            {
              $this->chop();
              return new Token(Type::SYMBOL, substr(
                $this->context, 
                $i, 
                min($this->cur - $i, $this->characterCount -1)
              )); 
            } 
            default: {
              return new Token(Type::SYMBOL, $this->context[$i]); 
            }
          }
        } 
        case "?" : {
          $i = $this->cur;
          $this->chop();
          if ($this->context[$this->cur] === ">") {
            $this->chop();
            return new Token(Type::SYMBOL, substr(
              $this->context, 
              $i, 
              min($this->cur - $i, $this->characterCount -1)
            )); 
          }
        }
        case '"': {
          $this->chop(); // "
          $i = $this->cur;
          while ($this->context[$this->cur] !== '"') {
            $this->chop();
          }
          $end = $this->cur;
          $this->chop(); // "
           $res =  substr(
            $this->context, 
            $i, 
            min($end - $i, $this->characterCount -1)
           ); 
          return new Token(Type::NUMBER, $res);
        }
        case "=" : {
          $i = $this->cur; 
          $this->chop(); 
          return new Token(Type::SYMBOL, $this->context[$i]); 
        }       
        case ">" : {
          $i = $this->cur; 
          $this->chop(); 
          return new Token(Type::SYMBOL, $this->context[$i]); 
        } 
    }
  }
}
class XMLParser {
  private $lexed;
  public $tree;
  public function __construct($lexedContent) 
  {
    $this->lexed = $lexedContent;
    $this->tree = ["xml" => [], "root" => []];
  }
  protected function parseXMLInfo ($lex, $nextValue) {
    if ($nextValue !== "<?") return;
    $lex->next(); // removing <?
    $next = $lex->next(); // removing xml
    while ($next->value !== "?>") {
      // x=y
      $lex->next(); // removing =
      $value = $lex->next();
      $this->tree["xml"][$next->value] = $value->value;
      $next = $lex->next();
    }
  }
  protected function parseTags(&$lex, $nextValue) {
    $this->parseTag($lex, $nextValue, $this->tree["root"], "root"); 
  }
  protected function getTagParams(&$lex, $nextValue, &$root) {
    $nextValue = $lex->next()->value; // this is the tag name
     $params = [];
     while ($nextValue !== ">") {
        // x=y
        $lex->next(); // removing =
        $value = $lex->next();
        $params[$nextValue] = $value->value;
        $nextValue = $lex->next()->value;
     }
     $root["params"] = $params;
     $nextValue = $lex->next()->value; // >
     return $nextValue;
  }
  protected function parseTag($lex, $nextValue, &$root) {
    if ($nextValue !== "<") return $nextValue; // this seems to be the name on the closing tag
    $nextValue = $lex->next()->value; // removing <
    $rootKey = $nextValue;
    $root[$rootKey] = [
      "params" => [],
      "children" => [],
    ];
    $nextValue = $this->getTagParams($lex, $nextValue, $root[$nextValue]);
    if ($nextValue === "<") {
      $this->parseTag($lex, $nextValue, $root[$rootKey]["children"]);
    }
    else {
      $valueChunks = [];
      while ($nextValue && $nextValue !== "</") {
        $valueChunks[] = $nextValue;
        $nextValue = $lex->next()->value;
      }      
      // $root[$rootKey]['value'] = $nextValue;
      $root[$rootKey]['value'] = implode(' ', $valueChunks);
    }
  }

  public function encode() {
    $next = $this->lexed->next();
    while ($next) {
      $this->parseXMLInfo($this->lexed, $next->value);
      $this->parseTags($this->lexed, $next->value);
      $next = $this->lexed->next();
    }
  }

  public function decodeTag(&$out, $root) {
    foreach($root as $key => $tag) {
      $out .= '<' . $key;
      $params = $tag['params'];
      foreach($params as $var => $value) {
        $out .= "\n" . ' ' . $var . '="' . $value . '"';
      }
      $out .= '>' . "\n";
      if ($tag['children']) {
        $this->decodeTag($out, $tag['children']);
      }
      if (isset($tag['value'])) {
        $out .= $tag['value'];
      }
      $out .= "\n" . '</' . $key . '>';
    }
    
  }

  public function decode() {
    $out = '';
      $this->decodeTag($out, $this->tree['root']);
    echo($out);
  }
}
$lex = new Lex(file_get_contents("./example.xml"));
$parser = new XMLParser($lex);
$parser->encode();
$parsed = $parser->tree;
$parser->decode();

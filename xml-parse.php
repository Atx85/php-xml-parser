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
  public function peek() {
    $temp = serialize($this->context);
    $tempCur = $this->cur;
    $p = $this->next();
    $this->context = unserialize($temp);
    $this->cur = $tempCur;
    return $p;
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
  public const OPEN_TAG = "open";
  public const CLOSE_TAG = "close";
  public const TAG_VALUE = "value";
  protected $next;
  private $index = 0;
 
  public function __construct($lexedContent) 
  {
    $this->lexed = $lexedContent;
    $this->tree = ["xml" => [], "root" => []];
  }
  protected function parseXMLInfo (&$lex) {
    $next = $lex->next();
    if ($next->value !== "<?") return;
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
$lex = new Lex(file_get_contents("./example.xml"));
$parser = new XMLParser($lex);
$parser->encode();
$parsed = $parser->tree;
echo $parser->decode();

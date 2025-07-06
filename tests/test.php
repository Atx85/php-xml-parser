use PHPUnit\Framework\TestCase;

final class SOAPParserFullCoverageTest extends TestCase
{
    public function testLexWhitespaceTrimmingAndTextToken()
    {
        $lex = new Lex("   \n\t  <message>HelloWorld</message>");
        $token = $lex->next(); // <
        $this->assertEquals(Type::SYMBOL, $token->type);
        $this->assertEquals("<", $token->value);

        $token = $lex->next(); // message
        $this->assertEquals(Type::TEXT, $token->type);
        $this->assertEquals("message", $token->value);
    }

    public function testLexHandlesAllSymbolPaths()
    {
        $lex = new Lex('< ? /> = > "<?xml version=\"1.0\"?>"');
        $expectedSymbols = ["<", "?", "/>", "=", ">", "1.0"];
        $tokens = [];

        while (($token = $lex->next()) !== false) {
            $tokens[] = $token->value;
        }

        $this->assertContains("<?", $tokens);
        $this->assertContains("/>", $tokens);
        $this->assertContains("=", $tokens);
        $this->assertContains("1.0", $tokens);
    }

    public function testPeekPreservesLexState()
    {
        $lex = new Lex("foo");
        $peeked = $lex->peek();
        $next = $lex->next();
        $this->assertEquals($peeked->value, $next->value);
    }

    public function testXMLParserEncodesSOAPEnvelopeWithParamsAndValue()
    {
        $xml = <<<XML
<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetData id="42" scope="full">Result</GetData>
  </soap:Body>
</soap:Envelope>
XML;

        $lex = new Lex($xml);
        $parser = new XMLParser($lex);
        $parser->encode();
        $tree = $parser->tree["root"];

        $this->assertEquals("soap:Envelope", $tree[0]["name"]);
        $this->assertEquals("soap:Body", $tree[0]["children"][0]["name"]);
        $this->assertEquals("GetData", $tree[0]["children"][0]["children"][0]["name"]);

        $tag = $tree[0]["children"][0]["children"][0];
        $this->assertEquals("42", $tag["params"]["id"]);
        $this->assertEquals("full", $tag["params"]["scope"]);
        $this->assertEquals("Result", $tag["value"]);
    }

    public function testXMLParserHandlesEmptyTagAndValueOnly()
    {
        $xml = '<ping>123</ping>';
        $lex = new Lex($xml);
        $parser = new XMLParser($lex);
        $parser->encode();

        $tag = $parser->tree["root"][0];
        $this->assertEquals("ping", $tag["name"]);
        $this->assertEquals("123", $tag["value"]);
    }

    public function testXMLParserHandlesUnclosedXMLInfo()
    {
        $lex = new Lex('<?xml version="1.0"'); // no ?>
        $parser = new XMLParser($lex);
        $parser->encode();

        // Should not parse any xml meta
        $this->assertEmpty($parser->tree["xml"]);
    }

    public function testParseCloseTagBranchExecution()
    {
        $lex = new Lex('</response>');
        $parser = new XMLParser($lex);
        $parser->encode();

        $tag = $parser->tree["root"][0];
        $this->assertEquals("response", $tag["name"]);
        $this->assertEquals(XMLParser::CLOSE_TAG, $tag["type"]);
    }

    public function testDecodeProducesValidSOAPXML()
    {
        $xml = <<<XML
<soap:Envelope>
  <soap:Body>
    <Echo msg="Hi">Hi</Echo>
  </soap:Body>
</soap:Envelope>
XML;

        $lex = new Lex($xml);
        $parser = new XMLParser($lex);
        $parser->encode();

        ob_start();
        $parser->decode();
        $output = ob_get_clean();

        $this->assertStringContainsString('<Echo msg="Hi">Hi</Echo>', $output);
        $this->assertStringContainsString('<soap:Envelope>', $output);
        $this->assertStringContainsString('</soap:Envelope>', $output);
    }
}

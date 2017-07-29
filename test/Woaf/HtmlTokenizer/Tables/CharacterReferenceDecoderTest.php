<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 29/07/2017
 * Time: 11:09
 */

namespace Woaf\HtmlTokenizer\Tables;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlStream;
use Woaf\HtmlTokenizer\ParseError;

class CharacterReferenceDecoderTest extends TestCase
{

    public function testNotANamedEntity()
    {
        $decoder = new CharacterReferenceDecoder(new Logger("CharacterReferenceDecoderTest", [new StreamHandler(STDOUT)]));
        $this->assertEquals(["&", []], $decoder->consumeCharRef(new HtmlStream("&foo;", "UTF-8")));
    }

    public function testInvalidNumericEntity()
    {
        $decoder = new CharacterReferenceDecoder(new Logger("CharacterReferenceDecoderTest", [new StreamHandler(STDOUT)]));
        $decoded = $decoder->consumeCharRef(new HtmlStream("#x3ffff;", "UTF-8"));
        $this->assertEquals([json_decode('"\uD8BF\uDFFF"'), [new ParseError()]], $decoded);
    }

    public function testJustAHash()
    {
        $decoder = new CharacterReferenceDecoder(new Logger("CharacterReferenceDecoderTest", [new StreamHandler(STDOUT)]));
        $decoded = $decoder->consumeCharRef(new HtmlStream("#", "UTF-8"));
        $this->assertEquals(['&#', []], $decoded);
    }
}
<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:35
 */

namespace Woaf\HtmlTokenizer;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;

class HtmlTokenizerTest extends TestCase
{
    
    private function getTokenizer() {
        return new HtmlTokenizer(new Logger("HtmlTokenizerTest", [new StreamHandler(STDOUT)]));
    }
    
    public function testBasicElement() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<div class="foo">LOL</div>');
        $this->assertEquals([
            new HtmlStartTagToken("div", false, ["class" => "foo"]),
            new HtmlCharToken("LOL"),
            new HtmlEndTagToken("div", false, [])
        ], $tokens->getTokens());
    }

    public function testBasicHtml() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText(file_get_contents("basic.html"));
        $this->assertEquals([
            new HtmlStartTagToken("div", false, ["class" => "foo"]),
            new HtmlCharToken("LOL"),
            new HtmlEndTagToken("div", false, [])
        ], $tokens->getTokens());
    }

    private static function mb_decode_entity($entity) {
        return mb_decode_numericentity($entity, [ 0x0, 0x10ffff, 0, 0x10ffff ]);
    }

    public function testNullInDATA() {
        $tokenizer = $this->getTokenizer();
        $tokens = $tokenizer->parseText(self::mb_decode_entity("&#x0000;"));
        $this->assertEquals([
            new HtmlCharToken(json_decode('"\u0000"')),
        ], $tokens->getTokens());
    }

    public function testNullInRCDATA() {
        $tokenizer = $this->getTokenizer();
        $tokenizer->pushState(HtmlTokenizer::$STATE_RCDATA, null);
        $tokens = $tokenizer->parseText(self::mb_decode_entity("&#x0000;"));
        $this->assertEquals([
            new HtmlCharToken(json_decode('"\uFFFD"')),
        ], $tokens->getTokens());
    }

    public function testVoidElementWithPermittedSlash() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<br/>');
        $this->assertEquals([
            new HtmlStartTagToken("br", true, []),
        ], $tokens->getTokens());
    }

}

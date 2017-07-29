<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:35
 */

namespace Woaf\HtmlTokenizer;

use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlAttrEndToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlAttrStartToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlAttrValueToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCloseTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlOpenTagEndToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlOpenTagStartToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;

class HtmlTokenizerTest extends TestCase
{
    public function testBasicElement() {
        $parser = new HtmlTokenizer();
        $tokens = $parser->parseText('<div class="foo">LOL</div>');
        $this->assertEquals([
            new HtmlStartTagToken("div", false, ["class" => "foo"]),
            new HtmlCharToken("LOL"),
            new HtmlEndTagToken("div", false, [])
        ], $tokens->getTokens());
    }

    public function testBasicHtml() {
        $parser = new HtmlTokenizer();
        $tokens = $parser->parseText(file_get_contents("basic.html"));
        $this->assertEquals([
            new HtmlStartTagToken("div", false, ["class" => "foo"]),
            new HtmlCharToken("LOL"),
            new HtmlEndTagToken("div", false, [])
        ], $tokens->getTokens());
    }

    private static function mb_decode_entity($entity) {
        return mb_decode_numericentity($entity, [ 0x0, 0xffff, 0, 0xffff ]);
    }

    public function testNullInDATA() {
        $tokenizer = new HtmlTokenizer();
        $tokens = $tokenizer->parseText(self::mb_decode_entity("&#x0000;"));
        $this->assertEquals([
            new HtmlCharToken(json_decode('"\u0000"')),
        ], $tokens->getTokens());
    }

    public function testNullInRCDATA() {
        $tokenizer = new HtmlTokenizer();
        $tokenizer->pushState(HtmlTokenizer::$STATE_RCDATA, null);
        $tokens = $tokenizer->parseText(self::mb_decode_entity("&#x0000;"));
        $this->assertEquals([
            new HtmlCharToken(json_decode('"\uFFFD"')),
        ], $tokens->getTokens());
    }

}

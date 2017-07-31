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
use Monolog\Processor\IntrospectionProcessor;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCommentToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;
use Woaf\HtmlTokenizer\Tables\State;

class HtmlTokenizerTest extends TestCase
{
    
    private function getTokenizer() {
        return new HtmlTokenizer(new Logger("HtmlTokenizerTest", [new StreamHandler(STDOUT)], [new IntrospectionProcessor()]));
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
        $tokenizer->pushState(State::$STATE_RCDATA, null);
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

    private static function decodeString($string) {
        return preg_replace_callback('/\\\u([0-9A-Fa-f]{4})/', function ($matches) { return self::mb_decode_entity("&#x" . $matches[1] . ";"); }, $string);
    }

    public function testNullInScriptHtmlComment() {
        $parser = $this->getTokenizer();
        $parser->pushState(State::$STATE_SCRIPT_DATA, null);
        $tokens = $parser->parseText(self::decodeString('<!--test\u0000--><!--test-\u0000--><!--test--\u0000-->'));
        $this->assertEquals([
            new HtmlCharToken(self::decodeString('<!--test\uFFFD--><!--test-\uFFFD--><!--test--\uFFFD-->')),
        ], $tokens->getTokens());
        $this->assertEquals([
            new ParseError(),
            new ParseError(),
            new ParseError()
        ], $tokens->getErrors());
    }

    public function testUnfinishedCommentWithDash()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText(self::decodeString('<!---<'));
        $this->assertEquals([
            new HtmlCommentToken("-<")
        ], $tokens->getTokens());
        $this->assertEquals([
            new ParseError()
        ], $tokens->getErrors());
    }

    public function testUnfinishedSimpleComment()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText(self::decodeString('<!-- A'));
        $this->assertEquals([
            new HtmlCommentToken(" A")
        ], $tokens->getTokens());
        $this->assertEquals([
            new ParseError()
        ], $tokens->getErrors());
    }

    public function testDoctypeOnlyPublicId()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML Transitional 4.01//EN">');
        $this->assertEquals([
            new HtmlDocTypeToken("html",'-//W3C//DTD HTML Transitional 4.01//EN', null, false)
        ], $tokens->getTokens());
        $this->assertEquals([], $tokens->getErrors());
    }

    public function testDoctypeOnlySystemId()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<!DOCTYPE html SYSTEM "-//W3C//DTD HTML Transitional 4.01//EN">');
        $this->assertEquals([
            new HtmlDocTypeToken("html", null, '-//W3C//DTD HTML Transitional 4.01//EN', false)
        ], $tokens->getTokens());
        $this->assertEquals([], $tokens->getErrors());
    }
}

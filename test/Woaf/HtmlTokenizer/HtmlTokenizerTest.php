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
use Woaf\HtmlTokenizer\Tables\ParseErrors;
use Woaf\HtmlTokenizer\Tables\State;

class HtmlTokenizerTest extends TestCase
{
    
    private function getTokenizer() {
        return new HtmlTokenizer(new Logger("HtmlTokenizerTest", [new StreamHandler(STDOUT)], [new IntrospectionProcessor()]));
    }
    
    public function testBasicElement() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<div Class="foo">LOL</div>');
        $this->assertEquals([
            new HtmlStartTagToken("div", false, ["class" => "foo"]),
            new HtmlCharToken("LOL"),
            new HtmlEndTagToken("div", false, [])
        ], $tokens->getTokens());
    }

    public function testNewline() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText("\n");
        $this->assertEquals([
            new HtmlCharToken("\n"),
        ], $tokens->getTokens());
    }

    public function testBasicHtml() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText(file_get_contents(__DIR__ . "/basic.html"));
        $this->assertEquals([
            new HtmlDocTypeToken("html", null, null, false),
            new HtmlCharToken("\n"),
            new HtmlStartTagToken("html", false, ["lang" => "en"]),
            new HtmlCharToken("\n"),
            new HtmlStartTagToken("head", false, []),
            new HtmlCharToken("\n    "),
            new HtmlStartTagToken("meta", false, ["charset" => "UTF-8"]),
            new HtmlCharToken("\n    "),
            new HtmlStartTagToken("title", false, []),
            new HtmlCharToken("Title"),
            new HtmlEndTagToken("title", false, []),
            new HtmlCharToken("\n"),
            new HtmlEndTagToken("head", false, []),
            new HtmlCharToken("\n"),
            new HtmlStartTagToken("body", false, []),
            new HtmlCharToken("OH HAI!"),
            new HtmlStartTagToken("br", true, []),
            new HtmlCharToken("\n"),
            new HtmlEndTagToken("body", false, []),
            new HtmlCharToken("\n"),
            new HtmlEndTagToken("html", false, []),
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
            ParseErrors::getUnexpectedNullCharacter(),
            ParseErrors::getUnexpectedNullCharacter(),
            ParseErrors::getUnexpectedNullCharacter(),
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
            ParseErrors::getEofInComment(),
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
            ParseErrors::getEofInComment(),
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

    public function testSingleCharUnterminatedDoctype()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<!DOCTYPE ?');
        $this->assertEquals([
            new HtmlDocTypeToken('?',null, null, true)
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getEofInDoctype()], $tokens->getErrors());
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

    public function testNullElement()
    {
        $text = "<>";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlCharToken("<>")
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getInvalidFirstCharacterOfTagName()], $tokens->getErrors());
    }

    public function testNullCloseElement()
    {
        $text = "</>";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getMissingEndTagName()], $tokens->getErrors());
    }

    public function testUnClosedEnd()
    {
        $text = "</";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlCharToken("</")
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getEofBeforeTagName()], $tokens->getErrors());
    }

    public function testUnquotedEmptyValue()
    {
        $text = '<a a =>';
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlStartTagToken("a", false, ['a' => ''])
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getMissingAttributeValue()], $tokens->getErrors());
    }

    public function testDoctypePublicCaseSensitivity()
    {
        $text = "<!dOcTyPe hTmL pUbLiC \"aBc\" \"xYz\">";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlDocTypeToken("html", "aBc", "xYz", false)], $tokens->getTokens());
        $this->assertEquals([], $tokens->getErrors());
    }
}

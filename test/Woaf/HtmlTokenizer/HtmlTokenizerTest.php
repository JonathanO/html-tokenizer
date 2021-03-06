<?php


namespace Woaf\HtmlTokenizer;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCommentToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEofToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;
use Woaf\HtmlTokenizer\Tables\ParseErrors;
use Woaf\HtmlTokenizer\Tables\State;

class HtmlTokenizerTest extends TestCase
{

    private static function getLogger() {
        $logLevel = getenv("LOGLEVEL");
        if ($logLevel === false) {
            $logLevel = "DEBUG";
        }
        $level = constant("Monolog\Logger::$logLevel");
        new Logger("CharacterReferenceDecoderTest", [new StreamHandler(STDOUT, $level)]);
    }


    private function getTokenizer() {
        return new TokenStreamingTokenizer(new Logger("HtmlTokenizerTest", [new StreamHandler(STDOUT, self::getLogger())], [new IntrospectionProcessor()]));
    }
    
    public function testBasicElement() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<div Class="foo">LOL</div>');
        $this->assertEquals([
            new HtmlStartTagToken("div", false, ["class" => "foo"]),
            new HtmlCharToken("LOL"),
            new HtmlEndTagToken("div", false, []),
            new HtmlEofToken()
        ], $tokens->getTokens());
    }

    public function testNewline() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText("\n");
        $this->assertEquals([
            new HtmlCharToken("\n"),
            new HtmlEofToken()
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
            new HtmlEofToken()
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
            new HtmlEofToken()
        ], $tokens->getTokens());
    }

    public function testNullInRCDATA() {
        $tokenizer = $this->getTokenizer();
        $tokenizer->pushState(State::$STATE_RCDATA, null);
        $tokens = $tokenizer->parseText(self::mb_decode_entity("&#x0000;"));
        $this->assertEquals([
            new HtmlCharToken(json_decode('"\uFFFD"')),
            new HtmlEofToken()
        ], $tokens->getTokens());
    }

    public function testVoidElementWithPermittedSlash() {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<br/>');
        $this->assertEquals([
            new HtmlStartTagToken("br", true, []),
            new HtmlEofToken()
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
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([
            ParseErrors::getUnexpectedNullCharacter(1, 9),
            ParseErrors::getUnexpectedNullCharacter(1, 22),
            ParseErrors::getUnexpectedNullCharacter(1, 36),
        ], $tokens->getErrors());
    }

    public function testUnfinishedCommentWithDash()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText(self::decodeString('<!---<'));
        $this->assertEquals([
            new HtmlCommentToken("-<"),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([
            ParseErrors::getEofInComment(1, 7),
        ], $tokens->getErrors());
    }

    public function testUnfinishedSimpleComment()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText(self::decodeString('<!-- A'));
        $this->assertEquals([
            new HtmlCommentToken(" A"),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([
            ParseErrors::getEofInComment(1, 7),
        ], $tokens->getErrors());
    }

    public function testDoctypeOnlyPublicId()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML Transitional 4.01//EN">');
        $this->assertEquals([
            new HtmlDocTypeToken("html",'-//W3C//DTD HTML Transitional 4.01//EN', null, false),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([], $tokens->getErrors());
    }

    public function testSingleCharUnterminatedDoctype()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<!DOCTYPE ?');
        $this->assertEquals([
            new HtmlDocTypeToken('?',null, null, true),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getEofInDoctype(1, 12)], $tokens->getErrors());
    }

    public function testDoctypeOnlySystemId()
    {
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText('<!DOCTYPE html SYSTEM "-//W3C//DTD HTML Transitional 4.01//EN">');
        $this->assertEquals([
            new HtmlDocTypeToken("html", null, '-//W3C//DTD HTML Transitional 4.01//EN', false),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([], $tokens->getErrors());
    }

    public function testNullElement()
    {
        $text = "<>";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlCharToken("<>"),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getInvalidFirstCharacterOfTagName(1, 2)], $tokens->getErrors());
    }

    public function testNullCloseElement()
    {
        $text = "</>";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getMissingEndTagName(1, 3)], $tokens->getErrors());
    }

    public function testUnClosedEnd()
    {
        $text = "</";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlCharToken("</"),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getEofBeforeTagName(1, 3)], $tokens->getErrors());
    }

    public function testUnquotedEmptyValue()
    {
        $text = '<a a =>';
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlStartTagToken("a", false, ['a' => '']),
            new HtmlEofToken()
        ], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getMissingAttributeValue(1, 7)], $tokens->getErrors());
    }

    public function testDoctypePublicCaseSensitivity()
    {
        $text = "<!dOcTyPe hTmL pUbLiC \"aBc\" \"xYz\">";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlDocTypeToken("html", "aBc", "xYz", false),
            new HtmlEofToken()], $tokens->getTokens());
        $this->assertEquals([], $tokens->getErrors());
    }

    public function testCDATAEof()
    {
        $text = "foo&bar";
        $parser = $this->getTokenizer();
        $parser->pushState(State::$STATE_CDATA_SECTION, null);
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlCharToken("foo&bar"),
            new HtmlEofToken()], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getEofInCdata(1, 8)], $tokens->getErrors());
    }

    public function testDOCTYPENLEOF()
    {
        $text = "<!DOCTYPE \n";
        $parser = $this->getTokenizer();
        $tokens = $parser->parseText($text);
        $this->assertEquals([
            new HtmlDocTypeToken(null, null, null, true),
            new HtmlEofToken()], $tokens->getTokens());
        $this->assertEquals([ParseErrors::getEofInDoctype(2, 1)], $tokens->getErrors());
    }

}

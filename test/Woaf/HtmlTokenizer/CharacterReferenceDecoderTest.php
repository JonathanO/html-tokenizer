<?php


namespace Woaf\HtmlTokenizer;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlStream;
use Woaf\HtmlTokenizer\HtmlParseError;
use Woaf\HtmlTokenizer\Tables\ParseErrors;

class CharacterReferenceDecoderTest extends TestCase
{

    private static function mb_decode_entity($entity) {
        return mb_decode_numericentity($entity, [ 0x0, 0x10ffff, 0, 0x10ffff ]);
    }

    private static function getLogger() {
        $logLevel = getenv("LOGLEVEL");
        if ($logLevel === false) {
            $logLevel = "DEBUG";
        }
        $level = constant("Monolog\Logger::$logLevel");
        new Logger("CharacterReferenceDecoderTest", [new StreamHandler(STDOUT, $level)]);
    }

    public function testNotANamedEntity()
    {
        $decoder = new CharacterReferenceDecoder();
        $this->assertEquals(["&", []], $decoder->consumeCharRef(new HtmlStream("&foo;", "UTF-8")));
    }

    public function testInvalidNumericEntity()
    {
        $decoder = new CharacterReferenceDecoder(self::getLogger());
        $decoded = $decoder->consumeCharRef(new HtmlStream("#x3ffff;", "UTF-8"));
        $this->assertEquals([json_decode('"\uD8BF\uDFFF"'), [ParseErrors::getNoncharacterCharacterReference()]], $decoded);
    }

    public function testJustAHash()
    {
        $decoder = new CharacterReferenceDecoder(self::getLogger());
        $decoded = $decoder->consumeCharRef(new HtmlStream("#", "UTF-8"));
        $this->assertEquals(['&#', [ParseErrors::getAbsenceOfDigitsInNumericCharacterReference()]], $decoded);
    }

    public function testNull() {
        $decoder = new CharacterReferenceDecoder(self::getLogger());
        $decoded = $decoder->consumeCharRef(new HtmlStream("#0000;", "UTF-8"));
        $this->assertEquals([self::mb_decode_entity("&#xFFFD;"), [ParseErrors::getNullCharacterReference()]], $decoded);
    }

    public function testLoltasticEntity() {
        $decoder = new CharacterReferenceDecoder(self::getLogger());
        $decoded = $decoder->consumeCharRef(new HtmlStream("#x10000000000000041;", "UTF-8"));
        $this->assertEquals([self::mb_decode_entity("&#xFFFD;"), [ParseErrors::getCharacterReferenceOutsideUnicodeRange()]], $decoded);
    }
}
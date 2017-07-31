<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 04/05/2017
 * Time: 17:34
 */

namespace Woaf\HtmlTokenizer;


use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\Tables\ParseErrors;

class HtmlStreamTest extends TestCase
{
    public function testRead() {
        $buf = new HtmlStream("twas b", "UTF-8");
        $errors = [];
        $this->assertEquals("t", $buf->read($errors));
        $this->assertEquals("w", $buf->read($errors));
        $this->assertEquals("a", $buf->read($errors));
        $this->assertEquals("s", $buf->read($errors));
        $this->assertEquals(" ", $buf->read($errors));
        $this->assertEquals("b", $buf->peek());
        $this->assertEquals("b", $buf->peek());
        $this->assertEquals("b", $buf->read($errors));
        $this->assertEquals(null, $buf->read($errors));
        $this->assertEmpty($errors);
    }

    public function testReadOnly() {
        $buf = new HtmlStream("twas", "UTF-8");
        $errors = [];
        $this->assertEquals("t", $buf->readOnly('t', $errors));
        $this->assertEquals("w", $buf->readOnly('w', $errors));
        $this->assertEquals(null, $buf->readOnly('w', $errors));
        $this->assertEquals("a", $buf->readOnly(['a','s'], $errors));
        $this->assertEquals("s", $buf->readOnly(['a','s'], $errors));
        $this->assertEquals(null, $buf->readOnly(' ', $errors));
        $this->assertEmpty($errors);
    }

    public function testConsume() {
        $buf = new HtmlStream("twas ", "UTF-8");
        $errors = [];
        $eof = true;
        $this->assertEquals("t", $buf->consume(['t'], $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("was", $buf->consume(['t', 'w', 'a', 's'], $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals(" ", $buf->consume([' '], $errors, $eof));
        $this->assertTrue($eof);
        $this->assertEquals("", $buf->consume([' '], $errors, $eof));
        $this->assertTrue($eof);
        $this->assertEmpty($errors);
    }

    public function testConsumeUntil() {
        $buf = new HtmlStream("twas ", "UTF-8");
        $errors = [];
        $eof = true;
        $this->assertEquals("", $buf->consumeUntil('t', $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("twa", $buf->consumeUntil(['z', 's'], $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("s", $buf->read($errors));
        $this->assertEquals(" ", $buf->read($errors));
        $this->assertEquals("", $buf->consumeUntil(' ', $errors, $eof));
        $this->assertTrue($eof);
        $this->assertEmpty($errors);
    }

    public function testIsNext() {
        $buf = new HtmlStream("twas ", "UTF-8");
        $errors = [];
        $this->assertTrue($buf->isNext("t"));
        $this->assertTrue($buf->isNext(["w", "t"]));
        $this->assertEquals("t", $buf->read($errors));
        $this->assertFalse($buf->isNext("t"));
        $this->assertTrue($buf->isNext("w"));
        $this->assertTrue($buf->isNext(["w", "t"]));
        $this->assertEmpty($errors);
    }

    public function testReadCr() {
        $buf = new HtmlStream("t\r\rw\r\ns", "UTF-8");
        $errors = [];
        $this->assertEquals("t", $buf->read($errors));
        $this->assertEquals("\n", $buf->read($errors));
        $this->assertEquals("\n", $buf->read($errors));
        $this->assertEquals("w", $buf->read($errors));
        $this->assertEquals("\n", $buf->read($errors));
        $this->assertEquals("s", $buf->read($errors));
        $this->assertEquals(null, $buf->read($errors));
        $this->assertEmpty($errors);
    }

    public function testReadOnlyCr() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $errors = [];
        $this->assertEquals("t", $buf->readOnly('t', $errors));
        $this->assertEquals("w", $buf->readOnly("w\n", $errors));
        $this->assertEquals(null, $buf->readOnly("\r", $errors));
        $this->assertEquals("\n", $buf->readOnly("w\n", $errors));
        $this->assertEquals("\n", $buf->readOnly("w\n", $errors));
        $this->assertEquals("w", $buf->readOnly("w\n", $errors));
        $this->assertEquals("\n", $buf->readOnly("w\n", $errors));
        $this->assertEquals(null, $buf->readOnly("w\n", $errors));
        $this->assertEquals("s", $buf->readOnly('s', $errors));
        $this->assertEmpty($errors);
    }

    public function testConsumeCr() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $errors = [];
        $eof = true;
        $this->assertEquals("t", $buf->consume(['t'], $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals(null, $buf->consume(["\r"], $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("w\n\nw\ns", $buf->consume(["w", "\n", "s"], $errors, $eof));
        $this->assertTrue($eof);
        $this->assertEmpty($errors);
    }

    public function testConsumeUntilCrDoesntStop() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $errors = [];
        $eof = true;
        $this->assertEquals("tw\n\nw\ns", $buf->consumeUntil("\r", $errors, $eof));
        $this->assertTrue($eof);
        $this->assertEmpty($errors);
    }

    public function testConsumeUntilConvertsCrStopsAtNL() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $errors = [];
        $eof = true;
        $this->assertEquals("tw", $buf->consumeUntil("\n", $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("\n\n", $buf->consumeUntil("w", $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("w", $buf->consumeUntil("\n", $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("\n", $buf->consumeUntil("s", $errors, $eof));
        $this->assertFalse($eof);
        $this->assertEquals("s", $buf->consumeUntil("a", $errors, $eof));
        $this->assertTrue($eof);
        $this->assertEmpty($errors);
    }


    public function testIsNextCr() {
        $buf = new HtmlStream("\rw\r\ns", "UTF-8");
        $errors = [];
        $this->assertFalse($buf->isNext("\r"));
        $this->assertTrue($buf->isNext("\n"));
        $this->assertEquals("\nw", $buf->read($errors, 2));
        $this->assertFalse($buf->isNext("\r"));
        $this->assertTrue($buf->isNext("\n"));
        $this->assertEquals("\n", $buf->read($errors));
        $this->assertFalse($buf->isNext("\n"));
        $this->assertTrue($buf->isNext("s"));
        $this->assertEmpty($errors);
    }

    public function testControlCharInStream() {
        $badChar = json_decode('"\u0003"');
        $buf = new HtmlStream($badChar, "UTF-8");
        $errors = [];
        $this->assertEquals($badChar, $buf->read($errors));
        $this->assertEquals([ParseErrors::getControlCharacterInInputStream()], $errors);
    }

    public function testControlCharInStreamConsumeUntil() {
        $badChar = json_decode('"\u0003"');
        $buf = new HtmlStream($badChar, "UTF-8");
        $errors = [];
        $this->assertEquals($badChar, $buf->consumeUntil("a", $errors));
        $this->assertEquals([ParseErrors::getControlCharacterInInputStream()], $errors);
    }

    public function testSpecificallyEvilCharInStream() {
        $badChar = json_decode('"\u000B"');
        $buf = new HtmlStream($badChar, "UTF-8");
        $errors = [];
        $this->assertEquals($badChar, $buf->read($errors));
        $this->assertEquals([ParseErrors::getControlCharacterInInputStream()], $errors);
    }

    private static function mb_decode_entity($entity) {
        return mb_decode_numericentity($entity, [ 0x0, 0x10ffff, 0, 0x10ffff ], "UTF-8");
    }

    public function testValidUnicodeChar() {
        $badChar = json_decode('"\uDB3F\uDFFD"');
        $buf = new HtmlStream($badChar, "UTF-8");
        $errors = [];
        $read = $buf->read($errors, 2);
        $this->assertEquals([], $errors);
        $this->assertEquals($badChar, $read);
    }

    public function testCornerCaseUnicodeChar() {
        $badChar = self::mb_decode_entity('&#xDFFF;');
        $buf = new HtmlStream($badChar, "UTF-8");
        $errors = [];
        $read = $buf->read($errors);
        $this->assertEquals($badChar, $read);
        $this->assertEquals([ParseErrors::getControlCharacterInInputStream()], $errors);
    }

}
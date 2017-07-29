<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 04/05/2017
 * Time: 17:34
 */

namespace Woaf\HtmlTokenizer;


use PHPUnit\Framework\TestCase;

class HtmlStreamTest extends TestCase
{
    public function testRead() {
        $buf = new HtmlStream("twas b", "UTF-8");
        $this->assertEquals("t", $buf->read());
        $this->assertEquals("w", $buf->read());
        $this->assertEquals("a", $buf->read());
        $this->assertEquals("s", $buf->read());
        $this->assertEquals(" ", $buf->read());
        $this->assertEquals("b", $buf->peek());
        $this->assertEquals("b", $buf->peek());
        $this->assertEquals("b", $buf->read());
        $this->assertEquals(null, $buf->read());
    }

    public function testReadOnly() {
        $buf = new HtmlStream("twas", "UTF-8");
        $this->assertEquals("t", $buf->readOnly('t'));
        $this->assertEquals("w", $buf->readOnly('w'));
        $this->assertEquals(null, $buf->readOnly('w'));
        $this->assertEquals("a", $buf->readOnly(['a','s']));
        $this->assertEquals("s", $buf->readOnly(['a','s']));
        $this->assertEquals(null, $buf->readOnly(' '));
    }

    public function testConsume() {
        $buf = new HtmlStream("twas ", "UTF-8");
        $eof = true;
        $this->assertEquals("t", $buf->consume('t', $eof));
        $this->assertFalse($eof);
        $this->assertEquals("was", $buf->consume(['t', 'w', 'a', 's'], $eof));
        $this->assertFalse($eof);
        $this->assertEquals(" ", $buf->consume(' ', $eof));
        $this->assertTrue($eof);
        $this->assertEquals("", $buf->consume(' ', $eof));
        $this->assertTrue($eof);
    }

    public function testConsumeUntil() {
        $buf = new HtmlStream("twas ", "UTF-8");
        $eof = true;
        $this->assertEquals("", $buf->consumeUntil('t', $eof));
        $this->assertFalse($eof);
        $this->assertEquals("twa", $buf->consumeUntil(['z', 's'], $eof));
        $this->assertFalse($eof);
        $this->assertEquals("s", $buf->read());
        $this->assertEquals(" ", $buf->read());
        $this->assertEquals("", $buf->consumeUntil(' ', $eof));
        $this->assertTrue($eof);
    }

    public function testIsNext() {
        $buf = new HtmlStream("twas ", "UTF-8");
        $this->assertTrue($buf->isNext("t"));
        $this->assertTrue($buf->isNext(["w", "t"]));
        $this->assertEquals("t", $buf->read());
        $this->assertFalse($buf->isNext("t"));
        $this->assertTrue($buf->isNext("w"));
        $this->assertTrue($buf->isNext(["w", "t"]));
    }

    public function testReadCr() {
        $buf = new HtmlStream("t\r\rw\r\ns", "UTF-8");
        $this->assertEquals("t", $buf->read());
        $this->assertEquals("\n", $buf->read());
        $this->assertEquals("\n", $buf->read());
        $this->assertEquals("w", $buf->read());
        $this->assertEquals("\n", $buf->read());
        $this->assertEquals("s", $buf->read());
        $this->assertEquals(null, $buf->read());
    }

    public function testReadOnlyCr() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $this->assertEquals("t", $buf->readOnly('t'));
        $this->assertEquals("w", $buf->readOnly("w\n"));
        $this->assertEquals(null, $buf->readOnly("\r"));
        $this->assertEquals("\n", $buf->readOnly("w\n"));
        $this->assertEquals("\n", $buf->readOnly("w\n"));
        $this->assertEquals("w", $buf->readOnly("w\n"));
        $this->assertEquals("\n", $buf->readOnly("w\n"));
        $this->assertEquals(null, $buf->readOnly("w\n"));
        $this->assertEquals("s", $buf->readOnly('s'));
    }

    public function testConsumeCr() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $eof = true;
        $this->assertEquals("t", $buf->consume('t', $eof));
        $this->assertFalse($eof);
        $this->assertEquals(null, $buf->consume(["\r"], $eof));
        $this->assertFalse($eof);
        $this->assertEquals("w\n\nw\ns", $buf->consume(["w", "\n", "s"], $eof));
        $this->assertTrue($eof);
    }

    public function testConsumeUntilCrDoesntStop() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $eof = true;
        $this->assertEquals("tw\n\nw\ns", $buf->consumeUntil("\r", $eof));
        $this->assertTrue($eof);
    }

    public function testConsumeUntilConvertsCrStopsAtNL() {
        $buf = new HtmlStream("tw\r\rw\r\ns", "UTF-8");
        $eof = true;
        $this->assertEquals("tw", $buf->consumeUntil("\n", $eof));
        $this->assertFalse($eof);
        $this->assertEquals("\n\n", $buf->consumeUntil("w", $eof));
        $this->assertFalse($eof);
        $this->assertEquals("w", $buf->consumeUntil("\n", $eof));
        $this->assertFalse($eof);
        $this->assertEquals("\n", $buf->consumeUntil("s", $eof));
        $this->assertFalse($eof);
        $this->assertEquals("s", $buf->consumeUntil("a", $eof));
        $this->assertTrue($eof);
    }


    public function testIsNextCr() {
        $buf = new HtmlStream("\rw\r\ns", "UTF-8");
        $this->assertFalse($buf->isNext("\r"));
        $this->assertTrue($buf->isNext("\n"));
        $this->assertEquals("\nw", $buf->read(2));
        $this->assertFalse($buf->isNext("\r"));
        $this->assertTrue($buf->isNext("\n"));
        $this->assertEquals("\n", $buf->read());
        $this->assertFalse($buf->isNext("\n"));
        $this->assertTrue($buf->isNext("s"));
    }
}
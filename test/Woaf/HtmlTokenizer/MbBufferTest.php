<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 04/05/2017
 * Time: 17:34
 */

namespace Woaf\HtmlTokenizer;


use PHPUnit\Framework\TestCase;

class MbBufferTest extends TestCase
{
    public function testRead() {
        $buf = new MbBuffer("twas b", "UTF-8");
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
        $buf = new MbBuffer("twas", "UTF-8");
        $this->assertEquals("t", $buf->readOnly('t'));
        $this->assertEquals("w", $buf->readOnly('w'));
        $this->assertEquals(null, $buf->readOnly('w'));
        $this->assertEquals("a", $buf->readOnly(['a','s']));
        $this->assertEquals("s", $buf->readOnly(['a','s']));
        $this->assertEquals(null, $buf->readOnly(' '));
    }

    public function testConsume() {
        $buf = new MbBuffer("twas ", "UTF-8");
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
        $buf = new MbBuffer("twas ", "UTF-8");
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
        $buf = new MbBuffer("twas ", "UTF-8");
        $this->assertTrue($buf->isNext("t"));
        $this->assertTrue($buf->isNext(["w", "t"]));
        $this->assertEquals("t", $buf->read());
        $this->assertFalse($buf->isNext("t"));
        $this->assertTrue($buf->isNext("w"));
        $this->assertTrue($buf->isNext(["w", "t"]));
    }
}
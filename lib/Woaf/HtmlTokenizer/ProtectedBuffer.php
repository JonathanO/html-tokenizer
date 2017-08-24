<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 20/08/2017
 * Time: 17:22
 */

namespace Woaf\HtmlTokenizer;


class ProtectedBuffer
{

    private $buffer = null;

    private $readOnly = false;

    protected function assertWriteable() {
        $this->assertInitialized();
        assert(!$this->readOnly, "Buffer is read only");
    }

    protected function assertInitialized() {
        assert($this->buffer !== null, "Buffer not initialized!");
    }

    protected function assertNotInitialized() {
        assert($this->buffer === null, "Buffer already initialized!");
        assert(!$this->readOnly, "Buffer is read only");
    }

    public function append($str) {
        $this->assertWriteable();
        $this->buffer .= $str;
    }

    public function init() {
        $this->assertNotInitialized();
        $this->buffer = "";
    }

    protected function clear() {
        $this->assertInitialized();
        $this->readOnly = false;
        $this->buffer = null;
    }

    public function getValue() {
        $this->assertInitialized();
        return $this->getValueOrNull();
    }

    public function getValueOrNull() {
        $this->readOnly = true;
        return $this->buffer;
    }

}
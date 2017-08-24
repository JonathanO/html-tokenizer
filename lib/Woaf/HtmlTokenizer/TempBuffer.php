<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 20/08/2017
 * Time: 17:22
 */

namespace Woaf\HtmlTokenizer;


class TempBuffer
{

    private $buffer;

    private function guard() {
        assert($this->buffer !== null, "Buffer not initialized!");
    }

    public function append($str) {
        $this->guard();
        $this->buffer .= $str;
    }

    public function init() {
        $this->buffer = "";
    }

    public function useValue() {
        $this->guard();
        $ret = $this->buffer;
        $this->buffer = null;
        return $ret;
    }

}
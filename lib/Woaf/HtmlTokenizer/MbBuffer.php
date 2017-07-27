<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 28/04/2017
 * Time: 12:42
 */

namespace Woaf\HtmlTokenizer;

class MbBuffer
{

    private $internalEncoding;
    private $buffer;
    private $cur = 0;
    private $curBytes = 0;
    private $bufLen = 0;

    public function __construct($buf, $encoding)
    {
        assert(is_string($buf));
        $this->internalEncoding = mb_internal_encoding();
        $this->buffer = mb_convert_encoding($buf, $this->internalEncoding, $encoding);
        $this->bufLen = mb_strlen($buf, $this->internalEncoding);
    }

/**    public function seek($n) {
        assert(is_int($n));
        $pos = $this->cur + $n;
        assert($this->isInBuffer($pos));

    }*/

    public function peek($len = 1) {
        $seekIn = substr($this->buffer, $this->curBytes, 4 * $len);
        if ($seekIn === false || $seekIn === "") {
            return null;
        }
        $peeked = mb_substr($seekIn, 0, $len, $this->internalEncoding);
        if ($peeked === false || $peeked === "") {
            return null;
        }
        return $peeked;
    }

    public function isNext($matching) {
        $next = $this->peek();
        if (is_array($matching)) {
            return in_array($next, $matching);
        }
        return $next === $matching;
    }

    public function read($len = 1) {
        $read = $this->peek($len);
        if ($read === null) {
            return null;
        }
        if ($len == 1) {
            $this->cur++;
        } else {
            $this->cur += mb_strlen($read, $this->internalEncoding);
        }
        $this->curBytes += strlen($read);
        return $read;
    }

    public function readOnly($matching) {
        if (!is_array($matching)) {
            $matching = preg_split("//u", $matching);
        }
        $char = $this->peek();
        if (in_array($char, $matching)) {
            return $this->read();
        }
        return null;
    }

    public function consume($matching, &$eof = false) {
        $eof = false;
        if (is_array($matching)) {
            $matching = join("", $matching);
        }
        return $this->pConsume('[' . preg_quote($matching) . ']+', $eof);
    }

    public function consumeUntil($matching, &$eof = false) {
        $eof = false;
        if (!is_array($matching)) {
            $matching = preg_split("//u", $matching);
        }
        $matcher = array_flip($matching);
        $buf = "";
        while (true) {
            $char = $this->peek();
            if ($char === null) {
                $eof = true;
                break;
            }
            if (isset($matcher[$char])) {
                break;
            }
            $buf .= $this->read();
        }
        return $buf;
    }

    public function pConsume($matching, &$eof = false) {
        $eof = false;
        $data = "";
        if (preg_match('/^' . $matching . '/', substr($this->buffer, $this->curBytes), $matches)) {
            $data = $matches[0];
            $this->cur += mb_strlen($data, $this->internalEncoding);
            $this->curBytes += strlen($data);
        }
        if ($this->cur >= $this->bufLen) {
            $eof = true;
        }
        return $data;
    }

}
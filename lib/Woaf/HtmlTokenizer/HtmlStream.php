<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 28/04/2017
 * Time: 12:42
 */

namespace Woaf\HtmlTokenizer;

class HtmlStream
{

    private $internalEncoding;
    private $inputEncoding;
    private $buffer;
    private $cur = 0;
    private $curBytes = 0;
    private $bufLen = 0;

    private $markCur;
    private $markCurBytes;

    private $confidence;

    const CONFIDENCE_CERTAIN = "certain";
    const CONFIDENCE_TENTATIVE = "tentative";
    const CONFIDENCE_IRRELEVANT = "irrelevant";

    public function mark() {
        $this->markCur = $this->cur;
        $this->markCurBytes = $this->curBytes;
    }

    public function reset() {
        $this->cur = $this->markCur;
        $this->curBytes = $this->markCurBytes;
    }

    public function save() {
        return [$this->cur, $this->curBytes];
    }

    public function load($save) {
        list($this->cur, $this->curBytes) = $save;
    }

    private function preProcessBuffer($buf)
    {
        $enc = mb_regex_encoding();
        mb_regex_encoding($this->internalEncoding);
        $buf = mb_ereg_replace("\r\n?", "\n", $buf);
        mb_regex_encoding($enc);
        return $buf;
    }

    private function setEncodingFromBOM($buf) {
        $two = substr($buf, 0, 2);
        if ($two == "\xfe\xff") {
            $this->inputEncoding = "UTF-16BE";
            return true;
        } elseif ($two == "\xff\xfe") {
            $this->inputEncoding = "UTF-16LE";
            return true;
        } elseif (substr($buf, 0, 3) == "\xef\xbb\xef") {
            $this->inputEncoding = "UTF-8";
            return true;
        }
        return false;
    }

    private function prescan($buf) {

    }

    public function __construct($buf, $forcedEncoding = null, $transportLayerEncoding = null)
    {
        assert(is_string($buf));
        $this->internalEncoding = mb_internal_encoding();
        if ($forcedEncoding != null) {
            $this->confidence = self::CONFIDENCE_CERTAIN;
            $encoding = $forcedEncoding;
        } elseif (!$this->setEncodingFromBOM($buf)) {
            $encoding = $this->inputEncoding;
        } elseif ($transportLayerEncoding != null) {
            $this->confidence = self::CONFIDENCE_CERTAIN;
            $encoding = $transportLayerEncoding;
        } else {
            $this->confidence = self::CONFIDENCE_TENTATIVE;
            $this->inputEncoding = $encoding = "UTF-8";
        }
        $this->buffer = $this->preProcessBuffer(mb_convert_encoding($buf, $this->internalEncoding, $encoding));
        $this->bufLen = mb_strlen($this->buffer, $this->internalEncoding);
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
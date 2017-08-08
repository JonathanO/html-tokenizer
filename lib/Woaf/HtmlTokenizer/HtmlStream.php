<?php


namespace Woaf\HtmlTokenizer;

use Woaf\HtmlTokenizer\HtmlTokens\Builder\ErrorReceiver;
use Woaf\HtmlTokenizer\Tables\ParseErrors;

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

    private $lastCur;
    private $lastCurBytes;

    private $furthestConsumed = 0;

    private $confidence;

    const CONFIDENCE_CERTAIN = "certain";
    const CONFIDENCE_TENTATIVE = "tentative";

    private static $NON_CHARS = [0xFFFE => true, 0xFFFF => true, 0x1FFFE => true, 0x1FFFF => true, 0x2FFFE => true, 0x2FFFF => true, 0x3FFFE => true, 0x3FFFF => true, 0x4FFFE => true, 0x4FFFF => true, 0x5FFFE => true, 0x5FFFF => true, 0x6FFFE => true, 0x6FFFF => true, 0x7FFFE => true, 0x7FFFF => true, 0x8FFFE => true, 0x8FFFF => true, 0x9FFFE => true, 0x9FFFF => true, 0xAFFFE => true, 0xAFFFF => true, 0xBFFFE => true, 0xBFFFF => true, 0xCFFFE => true, 0xCFFFF => true, 0xDFFFE => true, 0xDFFFF => true, 0xEFFFE => true, 0xEFFFF => true, 0xFFFFE => true, 0xFFFFF => true, 0x10FFFE => true, 0x10FFFF => true];
    private static $WHITESPACE = [0x0009 => true, 0x000A => true, 0x000C => true, 0x000D => true, 0x020 => true];
    private $bufLenBytes;

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

    private function loadAndUpdateLast($save) {
        $this->updateLast();
        $this->furthestConsumed = max($save[0], $this->furthestConsumed);
        list($this->cur, $this->curBytes) = $save;
    }

    private function updateLast() {
        $this->lastCur = $this->cur;
        $this->lastCurBytes = $this->curBytes;
    }

    public function unconsume() {
        $this->cur = $this->lastCur;
        $this->curBytes = $this->lastCurBytes;
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


    public function __construct($buf, $forcedEncoding = null, $transportLayerEncoding = null)
    {
        assert(is_string($buf));
        $this->internalEncoding = "UTF-8";
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
        $this->buffer = mb_convert_encoding($buf, $this->internalEncoding, $encoding);
        $this->buffer = $buf;
        $this->bufLen = mb_strlen($this->buffer, $this->internalEncoding);
        $this->bufLenBytes = strlen($this->buffer);
    }

    private function readChar($pos)
    {
        // This UTTERLY relies on the buffer being well formed UTF-8, which hopefully it is if mb_convert_encoding did its job.
        // TODO: maybe actually check errors like wat.
        if ($pos >= $this->bufLenBytes) {
            return null;
        }
        $chr = $this->buffer[$pos];
        $chrs = [];
        $chrs[] = ord($this->buffer[$pos]);
        $width = 1;
        if ($chrs[0] >= 0b11000000) {
            $chr .= $this->buffer[$pos + 1];
            $chrs[] = ord($this->buffer[$pos + 1]);
            $width = 2;
            if ($chrs[0] >= 0b11100000) {
                $chr .= $this->buffer[$pos + 2];
                $chrs[] = ord($this->buffer[$pos + 2]);
                $width = 3;
                if ($chrs[0] >= 0b11110000) {
                    $width = 4;
                    $chrs[] = ord($this->buffer[$pos + 3]);
                    $chr .= $this->buffer[$pos + 3];
                }
            }
        }
        $codepoint = null;
        switch ($width) {
            case 1:
                $codepoint = $chrs[0] & 0b01111111;
                break;
            case 2:
                $codepoint = (($chrs[0] & 0b00011111) << 6) | ($chrs[1] & 0b00111111);
                break;
            case 3:
                $codepoint = (((($chrs[0] & 0b00001111) << 6) | ($chrs[1] & 0b00111111)) << 6) | ($chrs[2] & 0b00111111);
                break;
            case 4:
                $codepoint = (((((($chrs[0] & 0b00000111) << 6) | ($chrs[1] & 0b00111111)) << 6) | ($chrs[2] & 0b00111111)) << 6) | ($chrs[3] & 0b00111111);;
                break;
            default:
                throw new \Exception("Wat");
        }
        $error = null;
        if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
            $error = ParseErrors::getSurrogateInInputStream();
        } elseif (isset(self::$NON_CHARS[$codepoint]) || ($codepoint >= 0xFDD0 && $codepoint <= 0xFDEF) || $codepoint > 0x10FFFF) {
            $error = ParseErrors::getNoncharacterInInputStream();
        } elseif (!isset(self::$WHITESPACE[$codepoint]) && (($codepoint > 0x0000 && $codepoint <= 0x001F) || ($codepoint >= 0x007F && $codepoint <= 0x009F))) {
            // not whitespace, not null, is otherwise a control char
            $error = ParseErrors::getControlCharacterInInputStream();
        }
        return [$chr, $width, $codepoint, $error];
    }

    private function peekInternal($len = 1, ErrorReceiver $receiver = null) {
        $chrs = 0;
        $read = null;
        $lastWasCR = false;
        $chr = null;
        $codepoints = [];
        for ($i = $this->curBytes; $chrs < $len; $i += $width) {
            list ($chr, $width, $codepoint, $error) = $this->readChar($i);
            if ($chr === null) {
                break;
            }
            $chrs++;
            if ($receiver && $error && $chrs + $this->cur > $this->furthestConsumed) {
                $receiver->error($error);
            }
            if ($chr == "\r") {
                $lastWasCR = true;
                $read .= "\n";
                $codepoints[] = 0x000A;
            } else {
                if (!($chr == "\n" && $lastWasCR)) {
                    $read .= $chr;
                    $codepoints[] = $codepoint;
                }
                $lastWasCR = false;
            }
        }
        if ($chr !== null) {
            if ($lastWasCR) {
                // Read ahead an extra char
                list ($chr, $width) = $this->readChar($i);
                if ($chr == "\n") {
                    // Skip that for next time.
                    $i += $width;
                    $chrs++;
                }
            }
        }

        $ptr = [$this->cur + $chrs, $i];

        return [$ptr, $read, $codepoints];
    }

    public function peek($len = 1) {
        return $this->peekInternal($len)[1];
    }

    public function isNext($matching) {
        $next = $this->peek();
        if (is_array($matching)) {
            return in_array($next, $matching);
        }
        return $next === $matching;
    }

    public function read(ErrorReceiver $receiver, $len = 1) {
        list($ptr, $read) = $this->peekInternal($len, $receiver);
        $this->loadAndUpdateLast($ptr);
        return $read;
    }

    public function readAlpha() {
        return $this->pConsume("[a-zA-Z]+");
    }

    public function readAlnum() {
        return $this->pConsume("[a-zA-Z0-9]+");
    }

    public function readNum() {
        return $this->pConsume("[0-9]+");
    }

    public function readHex() {
        return $this->pConsume("[a-fA-F0-9]+");
    }

    public function discardWhitespace() {
        return $this->pConsume("[ \n\t\f\r]+");
    }

    public function consumeUntil($matching, ErrorReceiver $receiver, &$eof = false) {
        $eof = false;
        if (!is_array($matching)) {
            $matching = preg_split("//u", $matching);
        }
        $matcher = array_flip($matching);
        $buf = "";
        while (true) {
            list($ptr, $char) = $this->peekInternal(1, $receiver);
            if ($char === null) {
                $eof = true;
                break;
            }
            if (isset($matcher[$char])) {
                break;
            }
            $this->loadAndUpdateLast($ptr);
            $buf .= $char;
        }
        return $buf;
    }

    private function pConsume($matching) {
        $data = "";
        $this->updateLast();
        if (preg_match('/^' . $matching . '/', substr($this->buffer, $this->curBytes), $matches)) {
            $data = $matches[0];
            $this->cur += mb_strlen($data, $this->internalEncoding);
            $this->furthestConsumed = max($this->cur, $this->furthestConsumed);
            $this->curBytes += strlen($data);
            $data = mb_ereg_replace("\r\n?", "\n", $data);
        }
        return $data;
    }

}
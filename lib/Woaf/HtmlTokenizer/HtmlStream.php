<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 28/04/2017
 * Time: 12:42
 */

namespace Woaf\HtmlTokenizer;

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

    private $confidence;

    const CONFIDENCE_CERTAIN = "certain";
    const CONFIDENCE_TENTATIVE = "tentative";
    const CONFIDENCE_IRRELEVANT = "irrelevant";

    private static $BAD_RANGES = [
        [0x0001, 0x0008],
        [0x000E, 0x001F],
        [0x007F, 0x009F],
        [0xD800, 0xDFFF], // surrogates
        [0xFDD0, 0xFDEF],
    ];
    
    private static $BAD_CHARS = [0x000B => true, 0xFFFE => true, 0xFFFF => true, 0x1FFFE => true, 0x1FFFF => true, 0x2FFFE => true, 0x2FFFF => true, 0x3FFFE => true, 0x3FFFF => true, 0x4FFFE => true, 0x4FFFF => true, 0x5FFFE => true, 0x5FFFF => true, 0x6FFFE => true, 0x6FFFF => true, 0x7FFFE => true, 0x7FFFF => true, 0x8FFFE => true, 0x8FFFF => true, 0x9FFFE => true, 0x9FFFF => true, 0xAFFFE => true, 0xAFFFF => true, 0xBFFFE => true, 0xBFFFF => true, 0xCFFFE => true, 0xCFFFF => true, 0xDFFFE => true, 0xDFFFF => true, 0xEFFFE => true, 0xEFFFF => true, 0xFFFFE => true, 0xFFFFF => true, 0x10FFFE => true, 0x10FFFF];
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

    private function readChar($pos, &$errors) {
        // This UTTERLY relies on the buffer being well formed UTF-8, which hopefully it is if mb_convert_encoding did its job.
        // TODO: maybe actually check errors like wat.
        if ($pos >= $this->bufLenBytes) {
            return null;
        }
        $chr = $this->buffer[$pos];
        $chrs = [];
        $chrs[] = ord($chr);
        $width = 1;
        if ($chrs[0] >= 0b11000000) {
            $chr .= $this->buffer[$pos+1];
            $chrs[] = ord($chr);
            $width = 2;
            if ($chrs[0] >= 0b11100000) {
                $chr .= $this->buffer[$pos+2];
                $chrs[] = ord($chr);
                $width = 3;
                if ($chrs[0] >= 0b11110000) {
                    $width = 4;
                    $chrs[] = ord($chr);
                    $chr .= $this->buffer[$pos+3];
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
                $codepoint = ((($chrs[0] & 0b00001111) << 6) | ($chrs[1] & 0b00111111) << 6) | ($chrs[2] & 0b00111111);
                break;
            case 4:
                $codepoint = (((($chrs[0] & 0b00000111) << 6) | ($chrs[1] & 0b00111111) << 6) | ($chrs[2] & 0b00111111) << 6) | ($chrs[3] & 0b00111111);;
                break;
            default:
                throw new \Exception("Wat");
        }
        if (isset(self::$BAD_CHARS[$codepoint])) {
            $errors[] = ParseErrors::getControlCharacterInInputStream();
        }
        foreach (self::$BAD_RANGES as $range) {
            if ($codepoint <= $range[1]) {
                if ($codepoint >= $range[0]) {
                    $errors[] = ParseErrors::getControlCharacterInInputStream();
                }
                break;
            }
        }

        return [$chr, $width, $codepoint];
    }

    private function peekInternal($len = 1, &$errors) {
        $chrs = 0;
        $read = null;
        $lastWasCR = false;
        $chr = null;
        for ($i = $this->curBytes; $chrs < $len; $i += $width) {
            list ($chr, $width) = $this->readChar($i, $errors);
            if ($chr === null) {
                break;
            }
            $chrs++;
            if ($chr == "\r") {
                $lastWasCR = true;
                $read .= "\n";
            } else {
                if (!($chr == "\n" && $lastWasCR)) {
                    $read .= $chr;
                }
                $lastWasCR = false;
            }
        }
        if ($chr !== null) {
            if ($lastWasCR) {
                // Read ahead an extra char
                list ($chr, $width) = $this->readChar($i, $noop);
                if ($chr == "\n") {
                    // Skip that for next time.
                    $i += $width;
                    $chrs++;
                }
            }
        }

        $ptr = [$this->cur + $chrs, $i];

        return [$ptr, $read];
    }

    public function peek($len = 1) {
        return $this->peekInternal($len, $noop)[1];
    }

    public function isNext($matching) {
        $next = $this->peek();
        if (is_array($matching)) {
            return in_array($next, $matching);
        }
        return $next === $matching;
    }

    public function read(array &$errors, $len = 1) {
        list($ptr, $read) = $this->peekInternal($len, $errors);
        $this->load($ptr);
        return $read;
    }

    public function readOnly($matching, array &$errors) {
        if (!is_array($matching)) {
            $matching = preg_split("//u", $matching);
        }
        $char = $this->peek();
        if (in_array($char, $matching)) {
            return $this->read($errors);
        }
        return null;
    }

    public function consume(array $matching, array &$errors, &$eof = false) {
        $eof = false;
        $matchStr = "";
        foreach ($matching as $match) {
            $matchStr .= $match;
            if ($match == "\n" || $match == '\n') {
                $matchStr .= "\r";
            }
        }
        return $this->pConsume('[' . preg_quote($matchStr) . ']+', $errors, $eof);
    }

    public function consumeUntil($matching, array &$errors, &$eof = false) {
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
            $buf .= $this->read($errors);
        }
        return $buf;
    }

    public function pConsume($matching, array &$errors, &$eof = false) {
        // TODO errors...
        $eof = false;
        $data = "";
        if (preg_match('/^' . $matching . '/', substr($this->buffer, $this->curBytes), $matches)) {
            $data = $matches[0];
            $this->cur += mb_strlen($data, $this->internalEncoding);
            $this->curBytes += strlen($data);
            $data = mb_ereg_replace("\r\n?", "\n", $data);
        }
        if ($this->cur >= $this->bufLen) {
            $eof = true;
        }
        return $data;
    }

}
<?php


namespace Woaf\HtmlTokenizer;


use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\ErrorReceiver;
use Woaf\HtmlTokenizer\Tables\ParseErrors;
use Woaf\HtmlTokenizer\Tables\NamedEntity;

class CharacterReferenceDecoder implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    private static $mappings = [
            ['0x00', 'U+FFFD', 'REPLACEMENT CHARACTER'],
            ['0x80', 'U+20AC', 'EURO SIGN (€)'],
            ['0x82', 'U+201A', 'SINGLE LOW-9 QUOTATION MARK (‚)'],
            ['0x83', 'U+0192', 'LATIN SMALL LETTER F WITH HOOK (ƒ)'],
            ['0x84', 'U+201E', 'DOUBLE LOW-9 QUOTATION MARK („)'],
            ['0x85', 'U+2026', 'HORIZONTAL ELLIPSIS (…)'],
            ['0x86', 'U+2020', 'DAGGER (†)'],
            ['0x87', 'U+2021', 'DOUBLE DAGGER (‡)'],
            ['0x88', 'U+02C6', 'MODIFIER LETTER CIRCUMFLEX ACCENT (ˆ)'],
            ['0x89', 'U+2030', 'PER MILLE SIGN (‰)'],
            ['0x8A', 'U+0160', 'LATIN CAPITAL LETTER S WITH CARON (Š)'],
            ['0x8B', 'U+2039', 'SINGLE LEFT-POINTING ANGLE QUOTATION MARK (‹)'],
            ['0x8C', 'U+0152', 'LATIN CAPITAL LIGATURE OE (Œ)'],
            ['0x8E', 'U+017D', 'LATIN CAPITAL LETTER Z WITH CARON (Ž)'],
            ['0x91', 'U+2018', 'LEFT SINGLE QUOTATION MARK (‘)'],
            ['0x92', 'U+2019', 'RIGHT SINGLE QUOTATION MARK (’)'],
            ['0x93', 'U+201C', 'LEFT DOUBLE QUOTATION MARK (“)'],
            ['0x94', 'U+201D', 'RIGHT DOUBLE QUOTATION MARK (”)'],
            ['0x95', 'U+2022', 'BULLET (•)'],
            ['0x96', 'U+2013', 'EN DASH (–)'],
            ['0x97', 'U+2014', 'EM DASH (—)'],
            ['0x98', 'U+02DC', 'SMALL TILDE (˜)'],
            ['0x99', 'U+2122', 'TRADE MARK SIGN (™)'],
            ['0x9A', 'U+0161', 'LATIN SMALL LETTER S WITH CARON (š)'],
            ['0x9B', 'U+203A', 'SINGLE RIGHT-POINTING ANGLE QUOTATION MARK (›)'],
            ['0x9C', 'U+0153', 'LATIN SMALL LIGATURE OE (œ)'],
            ['0x9E', 'U+017E', 'LATIN SMALL LETTER Z WITH CARON (ž)'],
            ['0x9F', 'U+0178', 'LATIN CAPITAL LETTER Y WITH DIAERESIS (Ÿ)'],
    ];

    private static $parseErrorRanges = [
        [0x0001, 0x0008],
        [0x000D, 0x001F],
        [0x007F, 0x009F],
        [0xFDD0, 0xFDEF],
    ];

    private static $parseErrorsValues = [
        0x000B, 0xFFFE, 0xFFFF, 0x1FFFE, 0x1FFFF, 0x2FFFE, 0x2FFFF, 0x3FFFE, 0x3FFFF, 0x4FFFE, 0x4FFFF, 0x5FFFE, 0x5FFFF, 0x6FFFE, 0x6FFFF, 0x7FFFE, 0x7FFFF, 0x8FFFE, 0x8FFFF, 0x9FFFE, 0x9FFFF, 0xAFFFE, 0xAFFFF, 0xBFFFE, 0xBFFFF, 0xCFFFE, 0xCFFFF, 0xDFFFE, 0xDFFFF, 0xEFFFE, 0xEFFFF, 0xFFFFE, 0xFFFFF, 0x10FFFE, 0x10FFFF,
    ];

    private $parseErrorsLookup;

    private $lookup = null;

    private function buildLookups()
    {
        $this->parseErrorsLookup = array_flip(self::$parseErrorsValues);
        foreach (self::$mappings as $mapping) {
            $codepoint = substr($mapping[1], 2);
            $this->lookup[hexdec(substr($mapping[0], 2))] = [mb_decode_numericentity("&#x" . $codepoint . ";", [ 0x0, 0x10ffff, 0, 0x10ffff ]), $codepoint, $mapping[2]];
        }
    }

    public function consumeNumericEntity(HtmlStream $buffer, ErrorReceiver $receiver) {
        $buffer->read($receiver);
        $next = $buffer->read($receiver);
        $number = null;
        if ($next == "x" || $next == "X") {
            $hex = $buffer->readHex();
            if ($hex === "") {
                $receiver->error(ParseErrors::getAbsenceOfDigitsInNumericCharacterReference());
                if ($this->logger) $this->logger->debug("Failed to consume any hex digits in hex numeric char ref");
                return "&#$next";
            }
            if ($this->logger) $this->logger->debug("Consumed hex char ref $hex");
            $number = hexdec($hex);
        } else {
            $buffer->unconsume();
            $number = $buffer->readNum();
            if ($number === "") {
                $receiver->error(ParseErrors::getAbsenceOfDigitsInNumericCharacterReference());
                if ($this->logger) $this->logger->debug("Failed to consume any decimal digits in decimal numeric char ref");
                return "&#";
            }
            $number = ltrim($number, "0");
            if ($number == "") {
                $number = "0";
            }
            if ($this->logger) $this->logger->debug("Consumed decimal char ref $number");
        }
        if ($buffer->read($receiver) != ";") {
            $buffer->unconsume();
            $receiver->error(ParseErrors::getMissingSemicolonAfterCharacterReference());
        }
        if (($number >= 0xD800 && $number <= 0xDFFF) || $number > 0x10FFFF) {
            if ($number > 0x10FFFF) {
                $receiver->error(ParseErrors::getCharacterReferenceOutsideUnicodeRange());
            } else {
                $receiver->error(ParseErrors::getSurrogateCharacterReference());
            }
            $remapping = $this->lookup[0];
            if ($this->logger) $this->logger->debug("Found reference $number in bad range, remapping to {$remapping[1]} ({$remapping[2]}): {$remapping[0]}");
            return $remapping[0];
        }
        if (isset($this->lookup[$number])) {
            $remapping = $this->lookup[$number];
            if ($this->logger) $this->logger->debug("Found disallowed reference $number, remapping to {$remapping[1]} ({$remapping[2]}): {$remapping[0]}");
            if ($number == 0) {
                $receiver->error(ParseErrors::getNullCharacterReference());
            } else {
                $receiver->error(ParseErrors::getControlCharacterReference());
            }
            return $remapping[0];
        } else {
            if (isset($this->parseErrorsLookup[$number])) {
                if ($this->logger) $this->logger->debug("Found bad codepoint $number, using anyway");
                if ($number == 0x000B) {
                    $receiver->error(ParseErrors::getControlCharacterReference());
                } else {
                    $receiver->error(ParseErrors::getNoncharacterCharacterReference());
                }
            } else {
                foreach (self::$parseErrorRanges as $range) {
                    if ($number >= $range[0] && $number <= $range[1]) {
                        if ($this->logger) $this->logger->debug("Found codepoint $number in bad range ({$range[0]} - {$range[1]}), using anyway");
                        if ($number >= 0xFDD0) {
                            $receiver->error(ParseErrors::getNoncharacterCharacterReference());
                        } else {
                            $receiver->error(ParseErrors::getControlCharacterReference());
                        }
                        break;
                    } elseif ($number <= $range[1]) {
                        // Entries are ordered, we can break out early if under the upper bound.
                        break;
                    }
                }
            }
            $char = mb_decode_numericentity("&#" . $number . ";", [0x0, 0x10ffff, 0, 0x10ffff]);
            if ($this->logger) $this->logger->debug("Decoding codepoint $number to $char");
            return $char;
        }
    }

    public function consumeNamedEntity(HtmlStream $buffer, $inAttribute, ErrorReceiver $receiver) {
        $cur = NamedEntity::$TABLE;
        $candidate = null;
        $lastWasSemicolon = false;
        $start = $buffer->save();
        $buffer->mark();
        $consumed = 0;
        for ($chr = $buffer->peek(); $chr != null && isset($cur[0][$chr]); $chr = $buffer->peek()) {
            $buffer->read($receiver);
            $consumed++;
            $cur = $cur[0][$chr];
            if ($cur[1] != null) {
                $candidate = $cur[1];
                $buffer->mark();
                $lastWasSemicolon = ($chr == ";");
            }
        }
        $buffer->reset(); // Unconsume non-matched chars
        if ($candidate != null) {
            if (!$lastWasSemicolon) {
                if ($inAttribute) {
                    $next = $buffer->peek();
                    if ($next == "=" || preg_match('/[A-Za-z0-9]/', $next)) {
                        //if ($next == "=") {
                            //$receiver->error(ParseErrors::getMissingSemicolonAfterCharacterReference());
                        //}
                        $buffer->load($start); // Unconsume everything. Sigh.
                        return "&";
                    } else {
                        $receiver->error(ParseErrors::getMissingSemicolonAfterCharacterReference());
                    }
                } else {
                    $receiver->error(ParseErrors::getMissingSemicolonAfterCharacterReference());
                }
            }
            return $candidate;
        } else {
            if ($consumed > 0) {
                $buffer->readAlnum();
                if ($buffer->peek() == ";") {
                    $receiver->error(ParseErrors::getUnknownNamedCharacterReference());
                }
                $buffer->reset();
            }
            return "&";
        }
    }

    public function consumeCharRef(HtmlStream $buffer, ErrorReceiver $receiver, $additionalAllowedChar = null, $inAttribute = false) {
        $peeked = $buffer->peek();
        if ($peeked === null) {
            return "&";
        }
        if ($additionalAllowedChar !== null && $peeked == $additionalAllowedChar) {
            return "&";
        }
        switch ($peeked) {
            case "\t":
            case "\n":
            case "\r":
            case " ":
            case "<":
            case "&":
                return "&";
            case "#":
                return $this->consumeNumericEntity($buffer, $receiver);
            default:
                return $this->consumeNamedEntity($buffer, $inAttribute, $receiver);
        }
    }


    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->buildLookups();
    }
}
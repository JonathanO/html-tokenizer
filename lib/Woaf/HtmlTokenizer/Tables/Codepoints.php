<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 29/08/2017
 * Time: 10:23
 */

namespace Woaf\HtmlTokenizer\Tables;


class Codepoints
{

    public static function isNull($codepoint) {
        return $codepoint == 0;
    }

    public static function isOutOfRange($codepoint) {
        return $codepoint > 0x10FFFF || $codepoint < 0;
    }

    public static function isSurrogate($codepoint) {
        return $codepoint >= 0xD800 && $codepoint <= 0xDFFF;
    }

    public static function isScalar($codepoint) {
        return !self::isSurrogate($codepoint);
    }
    
    public static function isNonCharacter($codepoint) {
        return
            ($codepoint >= 0xFDD0 && $codepoint <= 0xFDEF) ||
                $codepoint == 0xFFFE ||
                $codepoint == 0xFFFF ||
                $codepoint == 0x1FFFE ||
                $codepoint == 0x1FFFF ||
                $codepoint == 0x2FFFE ||
                $codepoint == 0x2FFFF ||
                $codepoint == 0x3FFFE ||
                $codepoint == 0x3FFFF ||
                $codepoint == 0x4FFFE ||
                $codepoint == 0x4FFFF ||
                $codepoint == 0x5FFFE ||
                $codepoint == 0x5FFFF ||
                $codepoint == 0x6FFFE ||
                $codepoint == 0x6FFFF ||
                $codepoint == 0x7FFFE ||
                $codepoint == 0x7FFFF ||
                $codepoint == 0x8FFFE ||
                $codepoint == 0x8FFFF ||
                $codepoint == 0x9FFFE ||
                $codepoint == 0x9FFFF ||
                $codepoint == 0xAFFFE ||
                $codepoint == 0xAFFFF ||
                $codepoint == 0xBFFFE ||
                $codepoint == 0xBFFFF ||
                $codepoint == 0xCFFFE ||
                $codepoint == 0xCFFFF ||
                $codepoint == 0xDFFFE ||
                $codepoint == 0xDFFFF ||
                $codepoint == 0xEFFFE ||
                $codepoint == 0xEFFFF ||
                $codepoint == 0xFFFFE ||
                $codepoint == 0xFFFFF ||
                $codepoint == 0x10FFFE ||
                $codepoint == 0x10FFFF;
        
    }

    public static function isAscii($codepoint) {
        return $codepoint >= 0x0000 && $codepoint <= 0x007F;
    }

    public static function isAsciiTabOrNewline($codepoint) {
        return $codepoint == 0x0009 || $codepoint == 0x000A || $codepoint == 0x000D;
    }

    public static function isAsciiWhitespace($codepoint) {
        return self::isAsciiTabOrNewline($codepoint) || $codepoint == 0x000C || self::isSpace($codepoint);
    }

    public static function isC0Control($codepoint) {
        return $codepoint >= 0x0000 && $codepoint <= 0x001F;
    }

    public static function isSpace($codepoint) {
        return $codepoint == 0x0020;
    }

    public static function isC0ControlOrSpace($codepoint) {
        return self::isC0Control($codepoint) || self::isSpace($codepoint);
    }

    public static function isControl($codepoint) {
        return self::isC0Control($codepoint) || ($codepoint >= 0x007F && $codepoint <= 0x009F);
    }

}
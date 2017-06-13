<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 26/04/2017
 * Time: 15:02
 */

namespace Woaf\HtmlTokenizer;

use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlToken;

class HtmlTokenizer
{
    const WHITESPACE = [" ", "\n", "\t", "\r"];


    private static $STATE_DATA = 0;
    private static $STATE_TAG_OPEN = 1;
    private static $STATE_RCDATA = 2;
    private static $STATE_RAWTEXT = 3;
    private static $STATE_SCRIPT_DATA = 4;
    private static $STATE_PLAIN_TEXT = 5;
    private static $STATE_RCDATA_LT_SIGN = 6;
    private static $STATE_RAWTEXT_LT_SIGN = 7;
    private static $STATE_SCRIPT_DATA_LT_SIGN = 8;
    private static $STATE_MARKUP_DECLARATION_OPEN = 9;
    private static $STATE_END_TAG_OPEN = 10;
    private static $STATE_BOGUS_COMMENT = 11;
    private static $STATE_TAG_NAME = 12;
    private static $STATE_BEFORE_ATTRIBUTE_NAME = 13;
    private static $STATE_SELF_CLOSING_START_TAG = 14;
    private static $STATE_RCDATA_END_TAG_OPEN = 15;
    private static $STATE_RCDATA_END_TAG_OPEN_STATE = 16;
    private static $STATE_RCDATA_END_TAG_NAME = 17;
    private static $STATE_RAWTEXT_LT = 18;
    private static $STATE_RAWTEXT_END_TAG_OPEN = 19;
    private static $STATE_RAWTEXT_END_TAG_OPEN_STATE = 20;
    private static $STATE_RAWTEXT_END_TAG_NAME = 21;
    private static $STATE_SCRIPT_DATA_LT = 22;
    private static $STATE_SCRIPT_DATA_END_TAG_OPEN = 23;
    private static $STATE_SCRIPT_DATA_ESCAPE_START = 24;
    private static $STATE_SCRIPT_DATA_END_TAG_NAME = 25;
    private static $STATE_SCRIPT_DATA_ESCAPED_DASH_DASH = 26;
    private static $STATE_SCRIPT_DATA_ESCAPED = 27;
    private static $STATE_SCRIPT_DATA_START_DASH = 28;
    private static $STATE_SCRIPT_DATA_ESCAPED_DASH = 29;
    private static $STATE_SCRIPT_DATA_ESCAPED_LT_SIGN = 30;
    private static $STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN = 31;
    private static $STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START = 32;
    private static $STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME = 33;
    private static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED = 34;
    private static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH = 35;
    private static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN = 36;
    private static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH_DASH = 37;
    private static $STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END = 38;
    private static $STATE_ATTRIBUTE_NAME = 39;
    private static $STATE_AFTER_ATTRIBUTE_NAME = 40;
    private static $STATE_BEFORE_ATTRIBUTE_VALUE = 41;
    private static $STATE_ATTRIBUTE_VALUE_UNQUOTED = 42;
    private static $STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED = 43;
    private static $STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED = 44;
    private static $STATE_AFTER_ATTRIBUTE_QUOTED = 45;

    /**
     * @var int[]
     */
    private $stack = [];

    public function enter() {
        array_push($this->stack, $this->stack[0]);
    }

    public function leave() {
        if (array_pop($this->stack) === null) {
            throw new \Exception("TODO: Parse error");
        }
    }

    public function getState() {
        return $this->stack[0];
    }


    private function setState($state) {
        $this->stack[0] = $state;
    }

    private $voidElements;
    private $rawTextElements;
    private $escapableRawTextElements;
    private $foreignElements;

    public function __construct()
    {
        $this->voidElements = array_flip(["area", "base", "br", "col", "embed", "hr", "img", "input", "keygen", "link", "meta", "param", "source", "track", "wbr"]);
        $this->rawTextElements = array_flip(["script", "style"]);
        $this->escapableRawTextElements = array_flip(["textarea", "title"]);
        $this->foreignElements = array_flip(["svg", "mathml"]); // TODO: fix namespace usage
        $this->stack[] = self::$STATE_DATA;
    }

    protected function andWhitespace($match) {
        if (!is_array($match)) {
            $a = self::WHITESPACE;
            $a[] = $match;
            return $a;
        }
        return array_merge($match, self::WHITESPACE);
    }

    protected function consumeData(MbBuffer $buffer, $ltState) {
        $token = null;
        $data = $buffer->consumeUntil("<", $eof); // TODO what about null replacement and entities?
        if ($data !== "") {
            $token = new HtmlCharToken($data);
        }
        if (!$eof) {
            if (!$buffer->readOnly("<")) {
                throw new \Exception("TODO: Parse error");
            }
            $this->setState($ltState);
        }
        return $token;
    }

    private $lastStartTagName = null;

    /**
     * @param $data
     * @return HtmlToken[]
     * @throws \Exception
     */
    public function parseText($data)
    {
        $tokens = [];
        $buffer = new MbBuffer($data, "UTF-8");
        while (($char = $buffer->peek()) !== null) {
            switch ($this->getState()) {
                case self::$STATE_DATA:
                    $tok = $this->consumeData($buffer, self::$STATE_TAG_OPEN);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_RCDATA:
                    $tok = $this->consumeData($buffer, self::$STATE_RCDATA_LT_SIGN);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_RAWTEXT:
                    $tok = $this->consumeData($buffer, self::$STATE_RAWTEXT_LT_SIGN);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_SCRIPT_DATA:
                    $tok = $this->consumeData($buffer, self::$STATE_SCRIPT_DATA_LT_SIGN);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_PLAIN_TEXT:
                    $data = "";
                    while (($read = $buffer->read()) !== null) {
                        $data .= $read;
                    }
                    $tokens[] = new HtmlCharToken($data);
                    break;
                case self::$STATE_TAG_OPEN:
                    $next = $buffer->peek();
                    switch ($next) {
                        case "!":
                            $buffer->read();
                            $this->setState(self::$STATE_MARKUP_DECLARATION_OPEN);
                            break;
                        case "/":
                            $buffer->read();
                            $this->setState(self::$STATE_END_TAG_OPEN);
                            break;
                        case "?":
                            $buffer->read();
                            $this->setState(self::$STATE_BOGUS_COMMENT);
                            break;
                        default:
                            if (preg_match("/[a-zA-Z]/u", $next)) {
                                $this->setState(self::$STATE_TAG_NAME);
                            } else {
                                // TODO: Warn parse error
                                $tokens[] = new HtmlCharToken("<");
                                $this->setState(self::$STATE_DATA);
                            }
                    }
                    break;
                case self::$STATE_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->setState(self::$STATE_TAG_NAME);
                    } else {
                        switch ($next) {
                            case ">":
                                // TODO: Warn parse error
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                // TODO: Warn parse error
                                $this->setState(self::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case self::$STATE_TAG_NAME:
                    // TODO: This really should have better error handling.
                    $name = mb_convert_case($buffer->consumeUntil(" \t\n\r/>"), MB_CASE_LOWER);
                    $this->lastStartTagName = $name;
                    switch ($buffer->read()) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\r":
                            $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                            break;
                        case "/":
                            $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                            break;
                        case ">":
                            $this->setState(self::$STATE_DATA);
                            break;
                        default:
                            // TODO!
                    }
                    break;
                case self::$STATE_RCDATA_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read();
                        $this->setState(self::$STATE_RCDATA_END_TAG_OPEN);
                    } else {
                        $tokens[] = new HtmlCharToken("<");
                        $this->setState(self::$STATE_RCDATA);
                    }
                    break;
                case self::$STATE_RCDATA_END_TAG_OPEN_STATE:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->setState(self::$STATE_RCDATA_END_TAG_NAME);
                    } else {
                        $tokens[] = new HtmlCharToken("</");
                        $this->setState(self::$STATE_RCDATA);
                    }
                    break;
                case self::$STATE_RCDATA_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+");
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                        $this->setState(self::$STATE_RCDATA);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\r":
                                $buffer->read();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                                $this->setState(self::$STATE_RCDATA);
                        }
                    }
                    break;
                case self::$STATE_RAWTEXT_LT:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read();
                        $this->setState(self::$STATE_RAWTEXT_END_TAG_OPEN);
                    } else {
                        $tokens[] = new HtmlCharToken("<");
                        $this->setState(self::$STATE_RAWTEXT);
                    }
                    break;
                case self::$STATE_RAWTEXT_END_TAG_OPEN_STATE:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->setState(self::$STATE_RAWTEXT_END_TAG_NAME);
                    } else {
                        $tokens[] = new HtmlCharToken("</");
                        $this->setState(self::$STATE_RAWTEXT);
                    }
                    break;
                case self::$STATE_RAWTEXT_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+");
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                        $this->setState(self::$STATE_RAWTEXT);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\r":
                                $buffer->read();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                                $this->setState(self::$STATE_RAWTEXT);
                        }
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_LT:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read();
                        $this->setState(self::$STATE_SCRIPT_DATA_END_TAG_OPEN);
                    } elseif ($next === "!") {
                        $buffer->read();
                        $this->setState(self::$STATE_SCRIPT_DATA_ESCAPE_START);
                        $tokens[] = new HtmlCharToken("<!");
                    } else {
                        $tokens[] = new HtmlCharToken("<");
                        $this->setState(self::$STATE_SCRIPT_DATA);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->setState(self::$STATE_SCRIPT_DATA_END_TAG_NAME);
                    } else {
                        $tokens[] = new HtmlCharToken("</");
                        $this->setState(self::$STATE_SCRIPT_DATA);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+");
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                        $this->setState(self::$STATE_SCRIPT_DATA);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\r":
                                $buffer->read();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                                $this->setState(self::$STATE_SCRIPT_DATA);
                        }
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_ESCAPE_START:
                    if ($buffer->readOnly("-") == "-") {
                        $tokens[] = new HtmlCharToken("-");
                        $this->setState(self::$STATE_SCRIPT_DATA_START_DASH);
                    } else {
                        $this->setState(self::$STATE_SCRIPT_DATA);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_START_DASH:
                    if ($buffer->readOnly("-") == "-") {
                        $tokens[] = new HtmlCharToken("-");
                        $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                    } else {
                        $this->setState(self::$STATE_SCRIPT_DATA);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_ESCAPED:
                    $data = $buffer->consumeUntil("-<"); // TODO: Handle nulls
                    switch ($buffer->peek()) {
                        case "-":
                            $data .= $buffer->read();
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_DASH);
                            break;
                        case "<":
                            $buffer->read();
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case null:
                            // TODO: Warn, parse error.
                            $this->setState(self::$STATE_DATA);
                            break;
                    }
                    if ($data != "") {
                        $tokens[] = new HtmlCharToken($data);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_ESCAPED_DASH:
                    switch ($buffer->peek()) {
                        case "-":
                            $buffer->read();
                            $tokens[] = new HtmlCharToken("-");
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                            break;
                        case "<":
                            $buffer->read();
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case null:
                            // TODO: Warn, parse error.
                            $this->setState(self::$STATE_DATA);
                            break;
                        default:
                            $tokens[] = new HtmlCharToken($buffer->read());
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH:
                    switch ($buffer->peek()) {
                        case "-":
                            $buffer->read();
                            $tokens[] = new HtmlCharToken("-");
                            break;
                        case "<":
                            $buffer->read();
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case ">":
                            $buffer->read();
                            $tokens[] = new HtmlCharToken(">");
                            $this->setState(self::$STATE_SCRIPT_DATA);
                            break;
                        case null:
                            // TODO: Warn, parse error.
                            $this->setState(self::$STATE_DATA);
                            break;
                        default:
                            $tokens[] = new HtmlCharToken($buffer->read());
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read();
                        $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN);
                    } else if (preg_match("/[a-zA-Z]/u", $next)) {
                        $tokens[] = new HtmlCharToken("<");
                        $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START);
                    } else {
                        $tokens[] = new HtmlCharToken("<");
                        $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME);
                    } else {
                        $tokens[] = new HtmlCharToken("</");
                        $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+");
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                        $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\r":
                                $buffer->read();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                                $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                        }
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+");
                    switch ($buffer->peek()) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\r":
                        case "/":
                        case ">":
                            $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                            if ($name == "script") {
                                $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                            } else {
                                $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                            }
                            $tempBuffer .= $buffer->read();
                            break;
                        default:
                            // Valid if peek returns null too. I think? TODO
                            $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    $tokens[] = new HtmlCharToken($tempBuffer);
                    break;
                case self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED:
                    $data = $buffer->consumeUntil("-<"); // TODO: Handle nulls
                    switch ($buffer->peek()) {
                        case "-":
                            $data .= $buffer->read();
                            $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH);
                            break;
                        case "<":
                            $data .= $buffer->read();
                            $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN);
                            break;
                        case null:
                            // TODO: Warn, parse error.
                            $this->setState(self::$STATE_DATA);
                            break;
                    }
                    if ($data != "") {
                        $tokens[] = new HtmlCharToken($data);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH:
                    $char = $buffer->read();
                    if ($char == null) {
                        // TODO: Warn, parse error.
                        $this->setState(self::$STATE_DATA);
                        break;
                    } else {
                        $tokens[] = new HtmlCharToken($char);
                        switch ($char) {
                            case "-":
                                $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH_DASH);
                                break;
                            case "<":
                                $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN);
                                break;
                            // TODO handle \0
                            default:
                                $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                        }
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH_DASH:
                    $char = $buffer->read();
                    if ($char == null) {
                        // TODO: Warn, parse error.
                        $this->setState(self::$STATE_DATA);
                        break;
                    } else {
                        $tokens[] = new HtmlCharToken($char);
                        switch ($char) {
                            case "-":
                                break;
                            case "<":
                                $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN);
                                break;
                            case ">":
                                $this->setState(self::$STATE_SCRIPT_DATA);
                                break;
                                // TODO handle \0
                            default:
                                $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                        }
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read();
                        $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END);
                    } else {
                        $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+");
                    switch ($buffer->peek()) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\r":
                        case "/":
                        case ">":
                            $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                            if ($name == "script") {
                                $this->setState(self::$STATE_SCRIPT_DATA_ESCAPED);
                            } else {
                                $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                            }
                            $tempBuffer .= $buffer->read();
                            break;
                        default:
                            // Valid if peek returns null too. I think? TODO
                            $this->setState(self::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    $tokens[] = new HtmlCharToken($tempBuffer);
                    break;
                case self::$STATE_BEFORE_ATTRIBUTE_NAME:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\r":
                            case " ":
                                $buffer->read();
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                // TODO:: Emmit tag!
                                $this->setState(self::$STATE_DATA);
                                break;
                            // TODO \0
                            case "\"":
                            case "'":
                            case "=":
                            case "<":
                                // TODO parse error
                            default:
                                $this->setState(self::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                case self::$STATE_ATTRIBUTE_NAME:
                    $attributeName = $buffer->consumeUntil("\t\n\r /=>"); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    switch ($buffer->read()) {
                        case "\t":
                        case "\n":
                        case "\r":
                        case " ":
                            $this->setState(self::$STATE_AFTER_ATTRIBUTE_NAME);
                            break;
                        case "/":
                            $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                            break;
                        case "=":
                            $this->setState(self::$STATE_BEFORE_ATTRIBUTE_VALUE);
                            break;
                        case ">":
                            // TODO emmit current tag!
                            $this->setState(self::$STATE_DATA);
                            break;
                        default: // This ought to be EOF only
                            // TODO: parse error
                            $this->setState(self::$STATE_DATA);
                    }
                    break;
                case self::$STATE_AFTER_ATTRIBUTE_NAME:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\r":
                            case " ":
                                $buffer->read();
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case "=":
                                $buffer->read();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_VALUE);
                                break;
                            case ">":
                                $buffer->read();
                                // TODO:: Emmit tag!
                                $this->setState(self::$STATE_DATA);
                                break;
                            // TODO \0
                            case "\"":
                            case "'":
                            case "<":
                                // TODO parse error
                            default:
                                $this->setState(self::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case self::$STATE_BEFORE_ATTRIBUTE_VALUE:
                    $next = $buffer->peek();
                    if ($next == null) {
                        // TODO: PARSE ERROR!
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\r":
                            case " ":
                                $buffer->read();
                                break;
                            case "\"":
                                $buffer->read();
                                $this->setState(self::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $buffer->read();
                                $this->setState(self::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED);
                                break;
                            // TODO \0
                            case "<":
                            case "=":
                            case "`":
                                // TODO parse error
                            default:
                                $this->setState(self::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
                        }
                    }
                    break;
                case self::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED:
                    $attributeValue = $buffer->consumeUntil("\""); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    switch ($buffer->read()) {
                        case "\"":
                            $this->setState(self::$STATE_AFTER_ATTRIBUTE_QUOTED);
                            break;
                            // TODO CHAR REFERENCE STATE!!
                        default: // This ought to be EOF only
                            // TODO: parse error
                            $this->setState(self::$STATE_DATA);
                    }
                    break;
                case self::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED:
                    $attributeValue = $buffer->consumeUntil("'"); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    switch ($buffer->read()) {
                        case "'":
                            $this->setState(self::$STATE_AFTER_ATTRIBUTE_QUOTED);
                            break;
                        // TODO CHAR REFERENCE STATE!!
                        default: // This ought to be EOF only
                            // TODO: parse error
                            $this->setState(self::$STATE_DATA);
                    }
                    break;
                case self::$STATE_ATTRIBUTE_VALUE_UNQUOTED:
                    $attributeValue = $buffer->consumeUntil("\t\n\r >"); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    switch ($buffer->read()) {
                        case "\t":
                        case "\n":
                        case "\r":
                        case " ":
                            $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                            break;
                        case ">":
                            $this->setState(self::$STATE_DATA);
                            // TODO Emmit tag!
                            break;
                        // TODO CHAR REFERENCE STATE!!
                        default: // This ought to be EOF only
                            // TODO: parse error
                            $this->setState(self::$STATE_DATA);
                    }
                    break;
                case self::$STATE_AFTER_ATTRIBUTE_QUOTED:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\r":
                            case " ":
                                $buffer->read();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                // TODO:: Emmit tag!
                                $this->setState(self::$STATE_DATA);
                                break;
                            // TODO \0
                            default:
                                // TODO Parse error!
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                default:
                    throw new \Exception("TODO: Parse error invalid state: " . $this->getState());
            }
        }
        return $tokens;
    }

    protected function parseOpenTagClosing(MbBuffer $buffer) {
        $andClose = $buffer->readOnly("/");
        if (!$buffer->readOnly(">")) {
            throw new \Exception("TODO: Parse error, expected > got " . $buffer->peek());
        }
        return $andClose;
    }

    protected function readAttrName(MbBuffer $buffer) {
        $attrName = $buffer->consumeUntil($this->andWhitespace("="), $eof);
        // We insist that the entire attr name and the = and the quote is in this node.
        if ($eof) {
            throw new \Exception("TODO: Parse error");
        }
        $buffer->consume(self::WHITESPACE);
        if (!$buffer->readOnly("=")) {
            throw new \Exception("TODO: Parse error, expected = got " . $buffer->peek());
        }
        $buffer->consume(self::WHITESPACE);
        if (!$buffer->readOnly('"')) {
            throw new \Exception("TODO: Parse error");
        }
        return $attrName;
    }

    protected function parseCloseTag(MbBuffer $buffer) {
        if (!$buffer->readOnly("/")) {
            throw new \Exception("TODO: Parse error");
        }
        $tagName = $buffer->consumeUntil($this->andWhitespace(">"), $eof);
        if ($eof) {
            throw new \Exception("TODO: Parse error");
        }
        $buffer->consume(self::WHITESPACE, $eof);
        if (!$buffer->readOnly(">")) {
            throw new \Exception("TODO: Parse error");
        }
        return $tagName;
    }

    protected function readStartTagName(MbBuffer $buffer) {
        $tagName = $buffer->consumeUntil($this->andWhitespace(["/", ">"]), $eof);
        if ($eof) {
            throw new \Exception("TODO: Parse error");
        }
        return $tagName;
    }


    protected function isVoidElement($tagName) {
        return isset($this->voidElements[$tagName]);
    }

}


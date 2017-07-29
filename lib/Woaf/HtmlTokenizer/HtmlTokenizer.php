<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 26/04/2017
 * Time: 15:02
 */

namespace Woaf\HtmlTokenizer;

use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlTagTokenBuilder;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCommentToken;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;
use Woaf\HtmlTokenizer\Tables\CharacterReferenceDecoder;

class HtmlTokenizer
{
    use LoggerAwareTrait;

    const WHITESPACE = [" ", "\n", "\t", "\f"];

    public static $STATE_DATA = 1;
    public static $STATE_RCDATA = 2;
    public static $STATE_RAWTEXT = 3;
    public static $STATE_SCRIPT_DATA = 4;
    public static $STATE_PLAINTEXT = 5;
    public static $STATE_RCDATA_LT_SIGN = 6;
    public static $STATE_SCRIPT_DATA_LT_SIGN = 8;
    public static $STATE_MARKUP_DECLARATION_OPEN = 9;
    public static $STATE_END_TAG_OPEN = 10;
    public static $STATE_BOGUS_COMMENT = 11;
    public static $STATE_TAG_NAME = 12;
    public static $STATE_BEFORE_ATTRIBUTE_NAME = 13;
    public static $STATE_SELF_CLOSING_START_TAG = 14;
    public static $STATE_RCDATA_END_TAG_OPEN = 15;
    public static $STATE_TAG_OPEN = 16;
    public static $STATE_RCDATA_END_TAG_NAME = 17;
    public static $STATE_RAWTEXT_LT_SIGN = 18;
    public static $STATE_RAWTEXT_END_TAG_OPEN = 19;
    public static $STATE_RAWTEXT_END_TAG_NAME = 21;
    public static $STATE_SCRIPT_DATA_END_TAG_OPEN = 23;
    public static $STATE_SCRIPT_DATA_ESCAPE_START = 24;
    public static $STATE_SCRIPT_DATA_END_TAG_NAME = 25;
    public static $STATE_SCRIPT_DATA_ESCAPED_DASH_DASH = 26;
    public static $STATE_SCRIPT_DATA_ESCAPED = 27;
    public static $STATE_SCRIPT_DATA_START_DASH = 28;
    public static $STATE_SCRIPT_DATA_ESCAPED_DASH = 29;
    public static $STATE_SCRIPT_DATA_ESCAPED_LT_SIGN = 30;
    public static $STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN = 31;
    public static $STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START = 32;
    public static $STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME = 33;
    public static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED = 34;
    public static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH = 35;
    public static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN = 36;
    public static $STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH_DASH = 37;
    public static $STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END = 38;
    public static $STATE_ATTRIBUTE_NAME = 39;
    public static $STATE_AFTER_ATTRIBUTE_NAME = 40;
    public static $STATE_BEFORE_ATTRIBUTE_VALUE = 41;
    public static $STATE_ATTRIBUTE_VALUE_UNQUOTED = 42;
    public static $STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED = 43;
    public static $STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED = 44;
    public static $STATE_AFTER_ATTRIBUTE_VALUE_QUOTED = 45;
    public static $STATE_COMMENT_START = 46;
    public static $STATE_COMMENT_START_DASH = 47;
    public static $STATE_COMMENT = 48;
    public static $STATE_COMMENT_END = 49;
    public static $STATE_COMMENT_END_DASH = 50;
    public static $STATE_COMMENT_END_BANG = 52;
    public static $STATE_DOCTYPE = 53;
    public static $STATE_CDATA_SECTION = 54;

    private $FFFDReplacementCharacter;

    private $entityReplacementTable;

    /**
     * @var int[]
     */
    private $stack = [];

    /**
     * @var HtmlTagTokenBuilder
     */
    private $currentTokenBuilder = null;

    public function enter() {
        array_push($this->stack, $this->stack[0]);
    }

    public function leave() {
        if (array_pop($this->stack) === null) {
            throw new \Exception("TODO: Parse error");
        }
    }
    
    public function pushState($state, $lastStartTagName) {
        $this->setState($state);
        $this->lastStartTagName = $lastStartTagName;
    }

    public function getState() {
        return $this->stack[0];
    }


    private function setState($state) {
        if ($this->logger) {
            $this->logger->debug("State change {$this->getState()} => {$state}");
        }
        $this->stack[0] = $state;
    }

    private $voidElements;
    private $rawTextElements;
    private $escapableRawTextElements;
    private $foreignElements;

    public function __construct(LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->setLogger($logger);
        }
        $this->entityReplacementTable = new CharacterReferenceDecoder($logger);
        $this->voidElements = array_flip(["area", "base", "br", "col", "embed", "hr", "img", "input", "keygen", "link", "meta", "param", "source", "track", "wbr"]);
        $this->rawTextElements = array_flip(["script", "style"]);
        $this->escapableRawTextElements = array_flip(["textarea", "title"]);
        $this->foreignElements = array_flip(["svg", "mathml"]); // TODO: fix namespace usage
        $this->stack[] = self::$STATE_DATA;
        $this->FFFDReplacementCharacter = mb_decode_numericentity("&#xFFFD;", [ 0x0, 0xffff, 0, 0xffff ]);
    }

    protected function andWhitespace($match) {
        if (!is_array($match)) {
            $a = self::WHITESPACE;
            $a[] = $match;
            return $a;
        }
        return array_merge($match, self::WHITESPACE);
    }

    protected function consumeData(HtmlStream $buffer, $ltState, array &$errors, $doNullReplacement = true) {
        $token = null;
        $lastRead = null;
        $data = "";
        $eof = false;
        while (true) {
            $data .= $buffer->consumeUntil("&<\0", $eof);
            $lastRead = $buffer->read();
            if ($lastRead == "&") {
                list($decoded, $decodeErrors) = $this->entityReplacementTable->consumeCharRef($buffer);
                $data .= $decoded;
                $errors = array_merge($errors, $decodeErrors);
            } elseif ($lastRead == "\0") {
                $errors[] = new ParseError();
                if ($doNullReplacement) {
                    $data .= $this->FFFDReplacementCharacter;
                } else {
                    $data .= $lastRead;
                }
            } else {
                break;
            }
        }
        if ($data !== "") {
            $token = new HtmlCharToken($data);
        }
        if (!$eof) {
            if ($lastRead != "<") {
                throw new \Exception("TODO: Parse error");
            }
            $this->setState($ltState);
        }
        return $token;
    }



    private $lastStartTagName = null;
    private $comment = "";

    /**
     * @param $data
     * @return TokenizerResult
     * @throws \Exception
     */
    public function parseText($data)
    {
        $tokens = [];
        $errors = [];
        $buffer = new HtmlStream($data, "UTF-8");
        $eof = false;
        while (!$eof) {
            switch ($this->getState()) {
                case self::$STATE_DATA:
                    if ($buffer->peek() === null) {
                        $eof = true;
                        break;
                    }
                    $tok = $this->consumeData($buffer, self::$STATE_TAG_OPEN, $errors, false);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_RCDATA:
                    if ($buffer->peek() === null) {
                        $eof = true;
                        break;
                    }
                    $tok = $this->consumeData($buffer, self::$STATE_RCDATA_LT_SIGN, $errors);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_RAWTEXT:
                    if ($buffer->peek() === null) {
                        $eof = true;
                        break;
                    }
                    $tok = $this->consumeData($buffer, self::$STATE_RAWTEXT_LT_SIGN, $errors);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_SCRIPT_DATA:
                    if ($buffer->peek() === null) {
                        $eof = true;
                        break;
                    }
                    $tok = $this->consumeData($buffer, self::$STATE_SCRIPT_DATA_LT_SIGN, $errors);
                    if ($tok != null) {
                        $tokens[] = $tok;
                    }
                    break;
                case self::$STATE_PLAINTEXT:
                    $data = "";
                    while (true) {
                        $eof = false;
                        $data .= $buffer->consumeUntil("\0", $eof);
                        if ($eof) {
                            break;
                        }
                        $buffer->read();
                        $errors[] = new ParseError();
                        $data .= $this->FFFDReplacementCharacter;
                    }
                    if ($data !== "") {
                        $tokens[] = new HtmlCharToken($data);
                    }
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
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_BOGUS_COMMENT);
                            break;
                        default:
                            if (preg_match("/[a-zA-Z]/u", $next)) {
                                $this->currentTokenBuilder = HtmlStartTagToken::builder();
                                $this->setState(self::$STATE_TAG_NAME);
                            } else {
                                $errors[] = new ParseError();
                                $tokens[] = new HtmlCharToken("<");
                                $this->setState(self::$STATE_DATA);
                            }
                    }
                    break;
                case self::$STATE_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->setState(self::$STATE_TAG_NAME);
                    } else {
                        switch ($next) {
                            case ">":
                                $errors[] = new ParseError();
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                $errors[] = new ParseError();
                                $this->setState(self::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case self::$STATE_TAG_NAME:
                    // TODO: This really should have better error handling.
                    $name = mb_convert_case($buffer->consumeUntil(" \t\n\f/>"), MB_CASE_LOWER);
                    $this->lastStartTagName = $name;
                    $this->currentTokenBuilder->setName($name);
                    switch ($buffer->read()) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\f":
                            $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                            break;
                        case "/":
                            $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                            break;
                        case ">":
                            $tokens[] = $this->currentTokenBuilder->build();
                            $this->setState(self::$STATE_DATA);
                            break;
                        default:
                            // should just be the EOF case
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_DATA);
                            break;
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
                case self::$STATE_RCDATA_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
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
                            case "\f":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $tokens[] = $this->currentTokenBuilder->build();
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                                $this->setState(self::$STATE_RCDATA);
                        }
                    }
                    break;
                case self::$STATE_RAWTEXT_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read();
                        $this->setState(self::$STATE_RAWTEXT_END_TAG_OPEN);
                    } else {
                        $tokens[] = new HtmlCharToken("<");
                        $this->setState(self::$STATE_RAWTEXT);
                    }
                    break;
                case self::$STATE_RAWTEXT_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
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
                            case "\f":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $tokens[] = $this->currentTokenBuilder->build();
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $tokens[] = new HtmlCharToken("</" . $tempBuffer);
                                $this->setState(self::$STATE_RAWTEXT);
                        }
                    }
                    break;
                case self::$STATE_SCRIPT_DATA_LT_SIGN:
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
                        $this->currentTokenBuilder = HtmlEndTagToken::builder()();
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
                            case "\f":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $this->currentTokenBuilder->setName($name);
                                $tokens[] = $this->currentTokenBuilder->build();
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
                            $errors[] = new ParseError();
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
                            $errors[] = new ParseError();
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
                            $errors[] = new ParseError();
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
                        $this->currentTokenBuilder = HtmlEndTagToken::builder()();
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
                            case "\f":
                                $buffer->read();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $tokens[] = $this->currentTokenBuilder->build();
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
                        case "\f":
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
                            $errors[] = new ParseError();
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
                        $errors[] = new ParseError();
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
                        $errors[] = new ParseError();
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
                        case "\f":
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
                        $errors[] = new ParseError();
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                $buffer->read();
                                break;
                            case "/":
                                $buffer->read();
                                $this->setState(self::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read();
                                $tokens[] = $this->currentTokenBuilder->build();
                                $this->setState(self::$STATE_DATA);
                                break;
                            // TODO \0
                            case "\"":
                            case "'":
                            case "=":
                            case "<":
                                $errors[] = new ParseError();
                            default:
                                $this->setState(self::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case self::$STATE_ATTRIBUTE_NAME:
                    $attributeName = $buffer->consumeUntil("\t\n\f /=>"); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    $this->currentTokenBuilder->addAttributeName($attributeName);
                    switch ($buffer->read()) {
                        case "\t":
                        case "\n":
                        case "\f":
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
                            $tokens[] = $this->currentTokenBuilder->build();
                            $this->setState(self::$STATE_DATA);
                            break;
                        default: // This ought to be EOF only
                            $errors[] = new ParseError();
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
                            case "\f":
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
                                $tokens[] = $this->currentTokenBuilder->build();
                                $this->setState(self::$STATE_DATA);
                                break;
                            // TODO \0
                            case "\"":
                            case "'":
                            case "<":
                                $errors[] = new ParseError();
                            default:
                                $this->setState(self::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case self::$STATE_BEFORE_ATTRIBUTE_VALUE:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $errors[] = new ParseError();
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
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
                                $errors[] = new ParseError();
                            default:
                                $this->setState(self::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
                        }
                    }
                    break;
                case self::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED:
                    $attributeValue = $buffer->consumeUntil("\""); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    $this->currentTokenBuilder->addAttributeValue($attributeValue);
                    switch ($buffer->read()) {
                        case "\"":
                            $this->setState(self::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED);
                            break;
                            // TODO CHAR REFERENCE STATE!!
                        default: // This ought to be EOF only
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_DATA);
                    }
                    break;
                case self::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED:
                    $attributeValue = $buffer->consumeUntil("'"); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    $this->currentTokenBuilder->addAttributeValue($attributeValue);
                    switch ($buffer->read()) {
                        case "'":
                            $this->setState(self::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED);
                            break;
                        // TODO CHAR REFERENCE STATE!!
                        default: // This ought to be EOF only
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_DATA);
                    }
                    break;
                case self::$STATE_ATTRIBUTE_VALUE_UNQUOTED:
                    $attributeValue = $buffer->consumeUntil("\t\n\f >"); // TODO \0 HANDLING!
                    // TODO: parse errors in consuming name...
                    $this->currentTokenBuilder->addAttributeValue($attributeValue);
                    switch ($buffer->read()) {
                        case "\t":
                        case "\n":
                        case "\f":
                        case " ":
                            $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                            break;
                        case ">":
                            $this->setState(self::$STATE_DATA);
                            $tokens[] = $this->currentTokenBuilder->build();
                            break;
                        // TODO CHAR REFERENCE STATE!!
                        default: // This ought to be EOF only
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_DATA);
                    }
                    break;
                case self::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
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
                                $tokens[] = $this->currentTokenBuilder->build();
                                $this->setState(self::$STATE_DATA);
                                break;
                            // TODO \0
                            default:
                                $errors[] = new ParseError();
                                $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case self::$STATE_SELF_CLOSING_START_TAG:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $errors[] = new ParseError();
                        $this->setState(self::$STATE_DATA);
                    } else {
                        if ($next == ">") {
                            $tokens[] = $this->currentTokenBuilder->isSelfClosing(true)->build();
                            $this->setState(self::$STATE_DATA);
                        } else {
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case self::$STATE_BOGUS_COMMENT:
                    $read = $buffer->consumeUntil(">", $eof);
                    $buffer->readOnly(">"); // consume and discard the >
                    $tokens[] = new HtmlCommentToken($read);
                    $this->setState(self::$STATE_DATA);
                    break;
                case self::$STATE_MARKUP_DECLARATION_OPEN:
                    if ($buffer->peek(2) == "--") {
                        $buffer->read(2);
                        $this->comment = "";
                        $this->setState(self::$STATE_COMMENT_START);
                    } else {
                        $peeked = $buffer->peek(7);
                        if (strtoupper($peeked) == "DOCTYPE") {
                            $buffer->read(7);
                            $this->setState(self::$STATE_DOCTYPE);
                        } elseif ($peeked == "[CDATA[") {// TODO check stack!
                            $buffer->read(7);
                            $this->setState(self::$STATE_CDATA_SECTION);
                        } else {
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case self::$STATE_COMMENT_START:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $errors[] = new ParseError();
                        $tokens[] = new HtmlCommentToken($this->comment);
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "-":
                                $buffer->read();
                                $this->setState(self::$STATE_COMMENT_START_DASH);
                                break;
                            case ">":
                                $buffer->read();
                                $errors[] = new ParseError();
                                $tokens[] = new HtmlCommentToken($this->comment);
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                $this->setState(self::$STATE_COMMENT);
                        }
                    }
                    break;
                case self::$STATE_COMMENT_START_DASH:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $errors[] = new ParseError();
                        $tokens[] = new HtmlCommentToken($this->comment);
                        $this->setState(self::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "-":
                                $buffer->read();
                                $this->setState(self::$STATE_COMMENT_END);
                                break;
                            case ">":
                                $buffer->read();
                                $errors[] = new ParseError();
                                $tokens[] = new HtmlCommentToken($this->comment);
                                $this->setState(self::$STATE_DATA);
                                break;
                            default:
                                $this->setState(self::$STATE_COMMENT);
                        }
                    }
                    break;
                case self::$STATE_COMMENT:
                    $this->comment .= $buffer->consumeUntil("-", $eof);
                    if ($eof) {
                        $errors[] = new ParseError();
                        $tokens[] = new HtmlCommentToken($this->comment);
                        $this->setState(self::$STATE_DATA);
                    } elseif ($buffer->peek() == "-") {
                        $this->setState(self::$STATE_COMMENT_END_DASH);
                    }
                    break;
                case self::$STATE_COMMENT_END_DASH:
                    $read = $buffer->read();
                    if ($read === null) {
                        $errors[] = new ParseError();
                        $tokens[] = new HtmlCommentToken($this->comment);
                        $this->setState(self::$STATE_DATA);
                    } else {
                        if ($read == "-") {
                            $this->setState(self::$STATE_COMMENT_END);
                        } elseif ($read == "\0") {
                            $errors[] = new ParseError();
                            $this->comment .= "-" . $this->FFFDReplacementCharacter;
                            $this->setState(self::$STATE_COMMENT);
                        } else {
                            $this->comment .= "-";
                            $this->setState(self::$STATE_COMMENT);
                        }
                    }
                    break;
                case self::$STATE_COMMENT_END:
                    $read = $buffer->read();
                    if ($read === null) {
                        $errors[] = new ParseError();
                        $tokens[] = new HtmlCommentToken($this->comment);
                        $this->setState(self::$STATE_DATA);
                    } else {
                        if ($read == ">") {
                            $tokens[] = new HtmlCommentToken($this->comment);
                            $this->setState(self::$STATE_DATA);
                        } elseif ($read == "\0") {
                            $errors[] = new ParseError();
                            $this->comment .= "--" . $this->FFFDReplacementCharacter;
                            $this->setState(self::$STATE_COMMENT);
                        } elseif ($read == "!") {
                            $errors[] = new ParseError();
                            $this->setState(self::$STATE_COMMENT_END_BANG);
                        } elseif ($read == "-") {
                            $errors[] = new ParseError();
                            $this->comment .= "-";
                        } else {
                            $this->comment .= "--" . $read;
                            $this->setState(self::$STATE_COMMENT);
                        }
                    }
                    break;
                case self::$STATE_COMMENT_END_BANG:
                    $read = $buffer->read();
                    if ($read === null) {
                        $errors[] = new ParseError();
                        $tokens[] = new HtmlCommentToken($this->comment);
                        $this->setState(self::$STATE_DATA);
                    } else {
                        if ($read == "-") {
                            $this->comment .= "--!";
                            $this->setState(self::$STATE_COMMENT_END_DASH);
                        } elseif ($read == ">") {
                            $tokens[] = new HtmlCommentToken($this->comment);
                            $this->setState(self::$STATE_DATA);
                        } elseif ($read == "\0") {
                            $errors[] = new ParseError();
                            $this->comment .= "--!" . $this->FFFDReplacementCharacter;
                            $this->setState(self::$STATE_COMMENT);
                        } else {
                            $this->comment .= "--!" . $read;
                            $this->setState(self::$STATE_COMMENT);
                        }
                    }
                    break;
                default:
                    throw new \Exception("TODO: Parse error invalid state: " . $this->getState());
            }
        }

        return new TokenizerResult($this->compressCharTokens($tokens), $errors, null); // TODO handle this bettererer  by keeping state instead maybe.
    }

    private function compressCharTokens($tokens) {
        $newTokens = [];
        $str = null;
        foreach($tokens as $token) {
            if ($token instanceof HtmlCharToken) {
                if ($str === null) {
                    $str = "";
                }
                $str .= $token->getData();
            } else {
                if ($str !== null) {
                    $newTokens[] = new HtmlCharToken($str);
                    $str = null;
                }
                $newTokens[] = $token;
            }
        }
        if ($str !== null) {
            $newTokens[] = new HtmlCharToken($str);
            $str = null;
        }
        return $newTokens;
    }

    protected function parseOpenTagClosing(HtmlStream $buffer) {
        $andClose = $buffer->readOnly("/");
        if (!$buffer->readOnly(">")) {
            throw new \Exception("TODO: Parse error, expected > got " . $buffer->peek());
        }
        return $andClose;
    }

    protected function readAttrName(HtmlStream $buffer) {
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

    protected function parseCloseTag(HtmlStream $buffer) {
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

    protected function readStartTagName(HtmlStream $buffer) {
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


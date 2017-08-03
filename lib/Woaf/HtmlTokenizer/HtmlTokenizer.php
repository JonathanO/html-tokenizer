<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 26/04/2017
 * Time: 15:02
 */

namespace Woaf\HtmlTokenizer;

use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlDocTypeTokenBuilder;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlTagTokenBuilder;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCommentToken;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlToken;
use Woaf\HtmlTokenizer\Tables\CharacterReferenceDecoder;
use Woaf\HtmlTokenizer\Tables\ParseErrors;
use Woaf\HtmlTokenizer\Tables\State;

/**
 * @property LoggerInterface logger
 */
class HtmlTokenizer
{
    use LoggerAwareTrait;

    const WHITESPACE = [" ", "\n", "\t", "\f"];

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

    /**
     * @var HtmlDocTypeTokenBuilder
     */
    private $currentDoctypeBuilder = null;

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
            $curState = $this->getState();
            $curName = State::toName($curState);
            $newName = State::toName($state);
            $this->logger->debug("State change {$curName}({$curState}) => {$newName}({$state})");
        }
        $this->stack[0] = $state;
    }

    private function emit(HtmlToken $token, array &$tokens) {
        if ($this->logger) $this->logger->debug("Emitting token " . $token);
        $tokens[] = $token;
    }

    private function parseError(ParseError $error, array &$errors) {
        if ($this->logger) $this->logger->debug("Encountered parse error " . $error);
        $errors[] = $error;
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
        $this->stack[] = State::$STATE_DATA;
        $this->FFFDReplacementCharacter = mb_decode_numericentity("&#xFFFD;", [ 0x0, 0x10ffff, 0, 0x10ffff ]);
    }

    protected function andWhitespace($match) {
        if (!is_array($match)) {
            $a = self::WHITESPACE;
            $a[] = $match;
            return $a;
        }
        return array_merge($match, self::WHITESPACE);
    }

    private function getBasicStateSwitcher($newState, callable $andThen = null) {
        return function($read, &$data) use ($newState, $andThen) {
            $this->setState($newState);
            if ($andThen != null) {
                $andThen($read, $data);
            }
            return false;
        };
    }

    private function getParseErrorAndContinue(ParseError $error, array &$errors) {
        return function($read, &$data) use (&$errors, $error) {
            $this->parseError($error, $errors);
            $data .= $read;
            return true;
        };
    }

    protected function consumeDataWithEntityReplacement(HtmlStream $buffer, $ltState, array &$errors, array &$tokens, $doNullReplacement, &$eof) {

        $andEmit = function($read, &$data) use (&$tokens) {
            if ($data !== "") {
                $this->emit(new HtmlCharToken($data), $tokens);
            }
        };

        $actions = [
              "&" => $this->getEntityReplacer($errors, $buffer),
              "<" => $this->getBasicStateSwitcher($ltState, $andEmit),
        ];
        if ($doNullReplacement) {
          $actions["\0"] = $this->getNullReplacer($errors);
        }

        $this->consume($buffer,
            $actions,
            function($read, &$data) use (&$eof, &$tokens, $andEmit) {
                $eof = true;
                $andEmit(null, $data);
            },
            $errors
        );
    }

    private function getEntityReplacer(array &$errors, $buffer, $additionalAllowedChar = null, $inAttribute = false)
    {
        return function ($read, &$data) use (&$errors, $buffer, $additionalAllowedChar, $inAttribute) {
            list($decoded, $decodeErrors) = $this->entityReplacementTable->consumeCharRef($buffer, $additionalAllowedChar, $inAttribute);
            $data .= $decoded;
            $errors = array_merge($errors, $decodeErrors);
            return true;
        };
    }

    private function getNullReplacer(array &$errors)
    {
        return function($read, &$data) use (&$errors)
        {
            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
            $data .= $this->FFFDReplacementCharacter;
            return true;
        };
    }

    /**
     * @param HtmlStream $buffer
     * @param array $states map of char found to [new state, emit?]
     * @param array $errors
     * @param $eof
     * @throws \Exception
     */
    protected function consumeDataNoEntityReplacement(HtmlStream $buffer, array $states, array &$errors, array &$tokens, &$eof) {

        $andEmit = function($read, &$data) use (&$tokens) {
            if ($data !== "") {
                $this->emit(new HtmlCharToken($data), $tokens);
            }
        };

        $actions = array_map(function($v) use ($andEmit) {
            return function($read, &$data) use ($v, $andEmit) {
                $this->setState($v[0]);
                if (isset($v[1]) && $v[1]) {
                    $data .= $read;
                }
                $andEmit($read, $data);
                return false;
            };
        }, $states);
        $actions["\0"] = $this->getNullReplacer($errors);

        $this->consume($buffer,
                $actions,
                function($read, &$data) use (&$tokens, &$eof, $andEmit) {
                    $eof = true;
                    $andEmit(null, $data);
                },
                $errors
            );
    }

    private function consume(HtmlStream $buffer, array $actions, callable $onEof, array &$errors) {
        $data = "";
        $eof = false;
        while (true) {
            $data .= $buffer->consumeUntil(array_keys($actions), $errors, $eof);
            $read = $buffer->read($errors);
            if (isset($actions[$read])) {
                if (!$actions[$read]($read, $data)) {
                    break;
                }
            } else {
                break;
            }
        }
        if ($eof) {
            $onEof(null, $data);
        }
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
        $done = false;
        while (!$done) {
            switch ($this->getState()) {
                case State::$STATE_DATA:
                    $this->consumeDataWithEntityReplacement($buffer, State::$STATE_TAG_OPEN, $errors, $tokens, false, $done);
                    break;
                case State::$STATE_RCDATA:
                    $this->consumeDataWithEntityReplacement($buffer, State::$STATE_RCDATA_LT_SIGN, $errors, $tokens, true, $done);
                    break;
                case State::$STATE_RAWTEXT:
                    $this->consumeDataNoEntityReplacement($buffer, ["<" => [State::$STATE_RAWTEXT_LT_SIGN]], $errors, $tokens, $done);
                    break;
                case State::$STATE_SCRIPT_DATA:
                    $this->consumeDataNoEntityReplacement($buffer, ["<" => [State::$STATE_SCRIPT_DATA_LT_SIGN]], $errors, $tokens, $done);
                    break;
                case State::$STATE_PLAINTEXT:
                    $this->consumeDataNoEntityReplacement($buffer, [], $errors, $tokens, $done);
                    break;
                case State::$STATE_TAG_OPEN:
                    $next = $buffer->peek();
                    switch ($next) {
                        case "!":
                            $buffer->read($errors);
                            $this->setState(State::$STATE_MARKUP_DECLARATION_OPEN);
                            break;
                        case "/":
                            $buffer->read($errors);
                            $this->setState(State::$STATE_END_TAG_OPEN);
                            break;
                        case "?":
                            $this->parseError(ParseErrors::getUnexpectedQuestionMarkInsteadOfTagName(), $errors);
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                            break;
                        default:
                            if (preg_match("/[a-zA-Z]/u", $next)) {
                                $this->currentTokenBuilder = HtmlStartTagToken::builder();
                                $this->setState(State::$STATE_TAG_NAME);
                            } else {
                                if ($next === null) {
                                    $this->parseError(ParseErrors::getEofBeforeTagName(), $errors);
                                } else {
                                    $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName(), $errors);
                                }
                                $this->emit(new HtmlCharToken("<"), $tokens);
                                $this->setState(State::$STATE_DATA);
                            }
                    }
                    break;
                case State::$STATE_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->setState(State::$STATE_TAG_NAME);
                    } else {
                        if ($next === null) {
                            $this->emit(new HtmlCharToken("</"), $tokens);
                            $this->parseError(ParseErrors::getEofBeforeTagName(), $errors);
                            $this->setState(State::$STATE_DATA);
                        } elseif ($next == ">") {
                            $buffer->read($errors);
                            $this->parseError(ParseErrors::getMissingEndTagName(), $errors);
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName(), $errors);
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_TAG_NAME:
                    $setTagName = function($read, &$data) {
                        $this->currentTokenBuilder->setName($data);
                    };
                    $beforeANameSwitcher = $this->getBasicStateSwitcher(State::$STATE_BEFORE_ATTRIBUTE_NAME, $setTagName);
                    $toLowerCase = function($read, &$data) {
                        $data .= strtolower($read);
                        return true;
                    };
                    $actions = [
                        "\t" => $beforeANameSwitcher,
                        "\n" => $beforeANameSwitcher,
                        "\f" => $beforeANameSwitcher,
                        " " => $beforeANameSwitcher,
                        "/" => $this->getBasicStateSwitcher(State::$STATE_SELF_CLOSING_START_TAG, $setTagName),
                        ">" => function($read, &$data) use (&$tokens, &$errors) {
                            $this->currentTokenBuilder->setName($data);
                            $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                            $this->setState(State::$STATE_DATA);
                            return false;
                        },
                        "\0" => $this->getNullReplacer($errors),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    $this->consume(
                        $buffer,
                        $actions,
                        function ($read, $data) use (&$errors) {
                            $this->currentTokenBuilder->setName($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $this->setState(State::$STATE_DATA);
                        },
                        $errors
                    );
                    break;
                case State::$STATE_RCDATA_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read($errors);
                        $this->setState(State::$STATE_RCDATA_END_TAG_OPEN);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $this->setState(State::$STATE_RCDATA);
                    }
                    break;
                case State::$STATE_RCDATA_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->setState(State::$STATE_RCDATA_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $this->setState(State::$STATE_RCDATA);
                    }
                    break;
                case State::$STATE_RCDATA_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                        $this->setState(State::$STATE_RCDATA);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $this->setState(State::$STATE_RCDATA);
                        }
                    }
                    break;
                case State::$STATE_RAWTEXT_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read($errors);
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_OPEN);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $this->setState(State::$STATE_RAWTEXT);
                    }
                    break;
                case State::$STATE_RAWTEXT_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $this->setState(State::$STATE_RAWTEXT);
                    }
                    break;
                case State::$STATE_RAWTEXT_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                        $this->setState(State::$STATE_RAWTEXT);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $this->setState(State::$STATE_RAWTEXT);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read($errors);
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_OPEN);
                    } elseif ($next === "!") {
                        $buffer->read($errors);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START);
                        $this->emit(new HtmlCharToken("<!"), $tokens);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder()();
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->currentTokenBuilder->setName($name);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $this->setState(State::$STATE_SCRIPT_DATA);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START:
                    if ($buffer->readOnly("-", $errors) == "-") {
                        $this->emit(new HtmlCharToken("-"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH);
                    } else {
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH:
                    if ($buffer->readOnly("-", $errors) == "-") {
                        $this->emit(new HtmlCharToken("-"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                    } else {
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED:
                    $this->consumeDataNoEntityReplacement(
                                $buffer,
                                [
                                    "<" => [State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN],
                                    "-" => [State::$STATE_SCRIPT_DATA_ESCAPED_DASH, true],
                                ],
                                $errors,
                                $tokens,
                                $eof
                    );
                    if ($eof) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                        $this->setState(State::$STATE_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH:
                    switch ($buffer->peek()) {
                        case "-":
                            $buffer->read($errors);
                            $this->emit(new HtmlCharToken("-"), $tokens);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                            break;
                        case "<":
                            $buffer->read($errors);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH:
                    switch ($buffer->peek()) {
                        case "-":
                            $buffer->read($errors);
                            $this->emit(new HtmlCharToken("-"), $tokens);
                            break;
                        case "<":
                            $buffer->read($errors);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case ">":
                            $buffer->read($errors);
                            $this->emit(new HtmlCharToken(">"), $tokens);
                            $this->setState(State::$STATE_SCRIPT_DATA);
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read($errors);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN);
                    } else if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN:
                    $next = $buffer->peek();
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder()();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    } else {
                        switch ($buffer->peek()) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                // Valid if peek returns null too. I think? TODO
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    switch ($buffer->peek()) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\f":
                        case "/":
                        case ">":
                            $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                            if ($name == "script") {
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                            } else {
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            }
                            $tempBuffer .= $buffer->read($errors);
                            break;
                        default:
                            // Valid if peek returns null too. I think? TODO
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    $this->emit(new HtmlCharToken($tempBuffer), $tokens);
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED:
                    $this->consumeDataNoEntityReplacement(
                        $buffer,
                        [
                            "<" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN],
                            "-" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH, true],
                        ],
                        $errors,
                        $tokens,
                        $eof
                    );
                    if ($eof) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                        $this->setState(State::$STATE_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH:
                    $char = $buffer->read($errors);
                    if ($char == null) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                        $this->setState(State::$STATE_DATA);
                        break;
                    } else {
                        if ($char == "\0") {
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                            $this->emit(new HtmlCharToken($this->FFFDReplacementCharacter), $tokens);
                        } else {
                            $this->emit(new HtmlCharToken($char), $tokens);
                        }
                        switch ($char) {
                            case "-":
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH_DASH);
                                break;
                            case "<":
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN);
                                break;
                            default:
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH_DASH:
                    $char = $buffer->read($errors);
                    if ($char == null) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                        $this->setState(State::$STATE_DATA);
                        break;
                    } else {
                        if ($char == "\0") {
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                            $this->emit(new HtmlCharToken($this->FFFDReplacementCharacter), $tokens);
                        } else {
                            $this->emit(new HtmlCharToken($char), $tokens);
                        }
                        switch ($char) {
                            case "-":
                                break;
                            case "<":
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN);
                                break;
                            case ">":
                                $this->setState(State::$STATE_SCRIPT_DATA);
                                break;
                            default:
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN:
                    $next = $buffer->peek();
                    if ($next === "/") {
                        $buffer->read($errors);
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END);
                    } else {
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    switch ($buffer->peek()) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\f":
                        case "/":
                        case ">":
                            $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                            if ($name == "script") {
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            } else {
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                            }
                            $tempBuffer .= $buffer->read($errors);
                            break;
                        default:
                            // Valid if peek returns null too. I think? TODO
                            $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    $this->emit(new HtmlCharToken($tempBuffer), $tokens);
                    break;
                case State::$STATE_BEFORE_ATTRIBUTE_NAME:
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag(), $errors);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                break;
                            case "/":
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            case "\"":
                            case "'":
                            case "<":
                                $this->parseError(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors);
                            default:
                                if ($next == "\0") {
                                    $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                                    $next = $this->FFFDReplacementCharacter;
                                } elseif ($next == "=") {
                                    $this->parseError(ParseErrors::getUnexpectedEqualsSignBeforeAttributeName(), $errors);
                                }
                                if ($next >= "A" && $next <= "Z") {
                                    $next = strtolower($next);
                                }
                                $this->currentTokenBuilder->startAttributeName($next);
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_ATTRIBUTE_NAME:
                    $addAttributeName = function($read, &$data) use (&$errors) {
                        $this->finishAttributeNameOrParseError($data, $errors);
                    };
                    $afterANameSwitcher = $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_NAME, $addAttributeName);
                    $toLowerCase = function($read, &$data) {
                        $data .= strtolower($read);
                        return true;
                    };
                    $actions = [
                        "\t" => $afterANameSwitcher,
                        "\n" => $afterANameSwitcher,
                        "\f" => $afterANameSwitcher,
                        " " => $afterANameSwitcher,
                        "/" => $this->getBasicStateSwitcher(State::$STATE_SELF_CLOSING_START_TAG, $addAttributeName),
                        "=" => $this->getBasicStateSwitcher(State::$STATE_BEFORE_ATTRIBUTE_VALUE, $addAttributeName),
                        ">" => function($read, &$data) use (&$tokens, &$errors) {
                            $this->finishAttributeNameOrParseError($data, $errors);
                            $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                            $this->setState(State::$STATE_DATA);
                            return false;
                        },
                        "\"" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors),
                        "'" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors),
                        "<" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors),
                        "\0" => $this->getNullReplacer($errors),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    $this->consume(
                        $buffer,
                        $actions,
                        function ($read, $data)  use (&$errors) {
                            $this->finishAttributeNameOrParseError($data, $errors);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $this->setState(State::$STATE_DATA);
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_NAME:
                    $buffer->consume(["\t", "\n", "\f", " "], $errors);
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "/":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case "=":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_VALUE);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            case "\"":
                            case "'":
                            case "<":
                                $buffer->read($errors);
                                $this->parseError(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors);
                                $this->currentTokenBuilder->startAttributeName($next);
                            default:
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_BEFORE_ATTRIBUTE_VALUE:
                    $buffer->consume(["\t", "\n", "\f", " "], $errors);
                    $buffer->mark();
                    $read = $buffer->read($errors);
                    if ($read == null) {
                        $this->parseError(ParseErrors::getEofInTag(), $errors);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($read) {
                            case "\"":
                                $this->setState(State::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED);
                                break;
                            case "&":
                                $buffer->reset(); // Unconsume the character
                                $this->setState(State::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
                                break;
                            case "'":
                                $this->setState(State::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED);
                                break;
                            case "\0":
                                $this->currentTokenBuilder->startAttributeValue($this->FFFDReplacementCharacter);
                                $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                                $this->setState(State::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
                                break;
                            case ">":
                                $this->setState(State::$STATE_DATA);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->parseError(ParseErrors::getMissingAttributeValue(), $errors);
                                break;
                            case "<":
                            case "=":
                            case "`":
                                $this->parseError(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors);
                            default:
                                $this->currentTokenBuilder->startAttributeValue($read);
                                $this->setState(State::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
                        }
                    }
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED:
                    $this->consume(
                        $buffer,
                        $actions = [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED, function($read, &$data) {
                                $this->finishAttributeValueOrDiscard($data);
                            }),
                            "&" => $this->getEntityReplacer($errors, $buffer, null, true),
                            "\0" => $this->getNullReplacer($errors),
                        ],
                        function ($read, $data) use (&$errors) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $this->setState(State::$STATE_DATA);
                        },
                        $errors
                    );
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED:
                    $this->consume(
                        $buffer,
                        $actions = [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED, function($read, &$data) {
                                $this->finishAttributeValueOrDiscard($data);
                            }),
                            "&" => $this->getEntityReplacer($errors, $buffer, null, true),
                            "\0" => $this->getNullReplacer($errors),
                        ],
                        function ($read, $data) use (&$errors) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $this->setState(State::$STATE_DATA);
                        },
                        $errors
                    );
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_UNQUOTED:
                    $switchBeforeAttrName = $this->getBasicStateSwitcher(State::$STATE_BEFORE_ATTRIBUTE_NAME, function($read, &$data) {
                        $this->finishAttributeValueOrDiscard($data);
                    });
                    $this->consume(
                        $buffer,
                        $actions = [
                            "\t" => $switchBeforeAttrName,
                            "\n" => $switchBeforeAttrName,
                            "\f" => $switchBeforeAttrName,
                            " " => $switchBeforeAttrName,
                            "&" => $this->getEntityReplacer($errors, $buffer, ">", true),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA,
                                function($read, &$data) use (&$tokens, &$errors) {
                                    $this->finishAttributeValueOrDiscard($data);
                                    $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                }
                            ),
                            "\0" => $this->getNullReplacer($errors),
                            "'" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "\"" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "<" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "=" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "`" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                        ],
                        function ($read, $data) use (&$errors) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $this->setState(State::$STATE_DATA);
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingWhitespaceBetweenAttributes(), $errors);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_SELF_CLOSING_START_TAG:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag(), $errors);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        if ($next == ">") {
                            $this->emit($this->currentTokenBuilder->isSelfClosing(true)->build($errors), $tokens);
                            $buffer->read($errors);
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->parseError(ParseErrors::getUnexpectedSolidusInTag(), $errors);
                            $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_BOGUS_COMMENT:
                    $switchAndEmit = $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens) {
                        $this->emit(new HtmlCommentToken($data), $tokens);
                    });
                    $this->consume($buffer,
                        [
                            ">" => $switchAndEmit,
                            "\0" => function($read, &$data) { $data .= $this->FFFDReplacementCharacter; return true; }
                        ],
                        $switchAndEmit,
                        $errors
                    );
                    break;
                case State::$STATE_MARKUP_DECLARATION_OPEN:
                    if ($buffer->peek(2) == "--") {
                        $buffer->read($errors, 2);
                        $this->comment = "";
                        $this->setState(State::$STATE_COMMENT_START);
                    } else {
                        $peeked = $buffer->peek(7);
                        if (strtoupper($peeked) == "DOCTYPE") {
                            $buffer->read($errors, 7);
                            $this->setState(State::$STATE_DOCTYPE);
                        } elseif ($peeked == "[CDATA[") {// TODO check stack!
                            $buffer->read($errors, 7);
                            $this->setState(State::$STATE_CDATA_SECTION);
                        } else {
                            $this->parseError(ParseErrors::getIncorrectlyOpenedComment(), $errors);
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_START:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "-":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_COMMENT_START_DASH);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment(), $errors);
                                $this->emit(new HtmlCommentToken($this->comment), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_START_DASH:
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "-":
                                $buffer->read($errors);
                                $this->setState(State::$STATE_COMMENT_END);
                                break;
                            case ">":
                                $buffer->read($errors);
                                $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment(), $errors);
                                $this->emit(new HtmlCommentToken($this->comment), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->comment .= "-";
                                $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT:
                    $this->consume($buffer,
                        [
                            "-" => $this->getBasicStateSwitcher(State::$STATE_COMMENT_END_DASH, function($read, &$data) { $this->comment .= $data; }),
                            "\0" => $this->getNullReplacer($errors)
                        ],
                        function ($read, &$data) use (&$tokens, &$errors) {
                            $this->comment .= $data;
                            $this->parseError(ParseErrors::getEofInComment(), $errors);
                            $this->emit(new HtmlCommentToken($this->comment), $tokens);
                            $this->setState(State::$STATE_DATA);
                        },
                        $errors
                    );
                    break;
                case State::$STATE_COMMENT_END_DASH:
                    $read = $buffer->read($errors);
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        if ($read == "-") {
                            $this->setState(State::$STATE_COMMENT_END);
                        } elseif ($read == "\0") {
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                            $this->comment .= "-" . $this->FFFDReplacementCharacter;
                            $this->setState(State::$STATE_COMMENT);
                        } else {
                            $this->comment .= "-" . $read;
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_END:
                    $read = $buffer->read($errors);
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        if ($read == ">") {
                            $this->emit(new HtmlCommentToken($this->comment), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } elseif ($read == "\0") {
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                            $this->comment .= "--" . $this->FFFDReplacementCharacter;
                            $this->setState(State::$STATE_COMMENT);
                        } elseif ($read == "!") {
                            $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment(), $errors);
                            $this->setState(State::$STATE_COMMENT_END_BANG);
                        } elseif ($read == "-") {
                            $this->parseError(ParseErrors::getIncorrectlyClosedComment(), $errors);
                            $this->comment .= "-";
                        } else {
                            $this->comment .= "--" . $read;
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_END_BANG:
                    $read = $buffer->read($errors);
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        if ($read == "-") {
                            $this->comment .= "--!";
                            $this->setState(State::$STATE_COMMENT_END_DASH);
                        } elseif ($read == ">") {
                            $this->emit(new HtmlCommentToken($this->comment), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } elseif ($read == "\0") {
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                            $this->comment .= "--!" . $this->FFFDReplacementCharacter;
                            $this->setState(State::$STATE_COMMENT);
                        } else {
                            $this->comment .= "--!" . $read;
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE:
                    $next = $buffer->peek();
                    switch ($next) {
                        case "\t":
                        case "\n":
                        case "\f":
                        case " ":
                            $buffer->read($errors);
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                        case null:
                            $buffer->read($errors);
                            $this->emit(HtmlDocTypeToken::builder()->isForceQuirks(true)->build(), $tokens);
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $this->parseError(ParseErrors::getMissingWhitespaceBeforeDoctypeName(), $errors);
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_NAME:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $this->currentDoctypeBuilder = HtmlDocTypeToken::builder();
                    $peeked = $buffer->peek();
                    if ($peeked == null || $peeked == ">") {
                        $buffer->read($errors);
                        if ($peeked == null) {
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        } else {
                            $this->parseError(ParseErrors::getMissingDoctypeName(), $errors);
                        }
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        $this->setState(State::$STATE_DOCTYPE_NAME);
                    }
                    break;
                case State::$STATE_DOCTYPE_NAME:
                    $addDoctypeName = function($read, &$data) {
                        $this->currentDoctypeBuilder->setName($data);
                    };
                    $afterDTNameSwitcher = $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_NAME, $addDoctypeName);
                    $toLowerCase = function($read, &$data) {
                        $data .= strtolower($read);
                        return true;
                    };
                    $actions = [
                        "\t" => $afterDTNameSwitcher,
                        "\n" => $afterDTNameSwitcher,
                        "\f" => $afterDTNameSwitcher,
                        " " => $afterDTNameSwitcher,
                        ">" => function($read, &$data) use (&$tokens) {
                            $this->currentDoctypeBuilder->setName($data);
                            $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                            $this->setState(State::$STATE_DATA);
                            return false;
                        },
                        "\0" => $this->getNullReplacer($errors),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    $this->consume(
                        $buffer,
                        $actions,
                        function ($read, $data) use (&$tokens, &$errors) {
                            $this->emit($this->currentDoctypeBuilder->setName($data)->isForceQuirks(true)->build(), $tokens);
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $this->setState(State::$STATE_DATA);
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_NAME:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $next = $buffer->peek();
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        if ($next == ">") {
                            $buffer->read($errors);
                            $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $next = strtoupper($buffer->peek(6));
                            if ($next == "PUBLIC") {
                                $buffer->read($errors, 6);
                                $this->setState(State::$STATE_AFTER_DOCTYPE_PUBLIC_KEYWORD);
                            } elseif ($next == "SYSTEM") {
                                $buffer->read($errors, 6);
                                $this->setState(State::$STATE_AFTER_DOCTYPE_SYSTEM_KEYWORD);
                            } else {
                                $this->parseError(ParseErrors::getInvalidCharacterSequenceAfterDoctypeName(), $errors);
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                            }
                        }
                    }
                    break;
                case State::$STATE_AFTER_DOCTYPE_PUBLIC_KEYWORD:
                    $read = $buffer->read($errors);
                    if ($read == null) {
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($read) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                $this->setState(State::$STATE_BEFORE_DOCTYPE_PUBLIC_IDENTIFIER);
                                break;
                            case "\"":
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypePublicKeyword(), $errors);
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypePublicKeyword(), $errors);
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getMissingDoctypePublicIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypePublicIdentifier(), $errors);
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_PUBLIC_IDENTIFIER:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\"":
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypePublicIdentifier(), $errors);
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED:
                    $handleError = function (ParseError $err) use (&$tokens, &$errors) {
                        return $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors, $err) {
                            $this->parseError($err, $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                        });
                    };
                    $this->consume(
                        $buffer,
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" => $handleError(ParseErrors::getAbruptDoctypePublicIdentifier())
                        ],
                        $handleError(ParseErrors::getEofInDoctype()),
                        $errors
                    );
                    break;
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED:
                    $handleError = function (ParseError $err) use (&$tokens, &$errors) {
                        return $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors, $err) {
                            $this->parseError($err, $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                        });
                    };
                    $this->consume(
                        $buffer,
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" => $handleError(ParseErrors::getAbruptDoctypePublicIdentifier())
                        ],
                        $handleError(ParseErrors::getEofInDoctype()),
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER:
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                $this->setState(State::$STATE_BETWEEN_DOCTYPE_PUBLIC_AND_SYSTEM_IDENTIFIERS);
                                break;
                            case ">":
                                $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            case "\"":
                                $this->parseError(ParseErrors::getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers(), $errors);
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->parseError(ParseErrors::getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers(), $errors);
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier(), $errors);
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BETWEEN_DOCTYPE_PUBLIC_AND_SYSTEM_IDENTIFIERS:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case ">":
                                $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            case "\"":
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier(), $errors);
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_AFTER_DOCTYPE_SYSTEM_KEYWORD:
                    $read = $buffer->read($errors);
                    if ($read == null) {
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($read) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                $this->setState(State::$STATE_BEFORE_DOCTYPE_SYSTEM_IDENTIFIER);
                                break;
                            case "\"":
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypeSystemKeyword(), $errors);
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypeSystemKeyword(), $errors);
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getMissingDoctypeSystemIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier(), $errors);
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_SYSTEM_IDENTIFIER:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        switch ($next) {
                            case "\"":
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier(), $errors);
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED:
                    $handleError = function (ParseError $err) use (&$tokens, &$errors) {
                        return $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors, $err) {
                            $this->parseError($err, $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build(), $tokens);
                        });
                    };

                    $this->consume(
                        $buffer,
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" => $handleError(ParseErrors::getAbruptDoctypeSystemIdentifier())
                        ],
                        $handleError(ParseErrors::getEofInDoctype()),
                        $errors
                    );
                    break;
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED:
                    $handleError = function (ParseError $err) use (&$tokens, &$errors) {
                        return $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors, $err) {
                            $this->parseError($err, $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build(), $tokens);
                        });
                    };
                    $this->consume(
                        $buffer,
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" =>  $handleError(ParseErrors::getAbruptDoctypeSystemIdentifier())
                        ],
                        $handleError(ParseErrors::getEofInDoctype()),
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                    } else {
                        if ($next == ">") {
                            $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->parseError(ParseErrors::getUnexpectedCharacterAfterDoctypeSystemIdentifier(), $errors);
                            $this->setState(State::$STATE_BOGUS_DOCTYPE);
                            break;
                        }
                    }
                    break;
                case State::$STATE_BOGUS_DOCTYPE:
                    $buffer->consumeUntil([">"], $errors);
                    $buffer->read($errors);
                    $this->setState(State::$STATE_DATA);
                    $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                    break;
                case State::$STATE_CDATA_SECTION:
                    $eof = false;
                    $read = $buffer->pConsume(".*]]>", $errors, $eof);
                    if (substr($read, -3) == "]]>") {
                        $read = substr($read, 0, -3);
                    }
                    $tokens[] = new HtmlCharToken($read);
                    $this->setState(State::$STATE_DATA);
                    break;
                default:
                    throw new \Exception("TODO: Parse error invalid state: " . $this->getState());
            }
        }

        return new TokenizerResult($this->compressCharTokens($tokens), $errors, null); // TODO handle this bettererer  by keeping state instead maybe.
    }

    private function finishAttributeNameOrParseError($name, &$errors) {
        try {
            $this->currentTokenBuilder->finishAttributeName($name);
        } catch (\Exception $e) {
            // TODO more specific excpetion
            $this->parseError(ParseErrors::getDuplicateAttribute(), $errors);
        }
    }

    private function finishAttributeValueOrDiscard($value) {
        try {
            $this->currentTokenBuilder->finishAttributeValue($value);
        } catch (\Exception $ignored) {
            // TODO more specific excpetion
        }
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
        $andClose = $buffer->readOnly("/", $errors);
        if (!$buffer->readOnly(">", $errors)) {
            throw new \Exception("TODO: Parse error, expected > got " . $buffer->peek());
        }
        return $andClose;
    }

    protected function readAttrName(HtmlStream $buffer) {
        $attrName = $buffer->consumeUntil($this->andWhitespace("="), $errors, $eof);
        // We insist that the entire attr name and the = and the quote is in this node.
        if ($eof) {
            throw new \Exception("TODO: Parse error");
        }
        $buffer->consume(self::WHITESPACE, $errors);
        if (!$buffer->readOnly("=", $errors)) {
            throw new \Exception("TODO: Parse error, expected = got " . $buffer->peek());
        }
        $buffer->consume(self::WHITESPACE, $errors);
        if (!$buffer->readOnly('"', $errors)) {
            throw new \Exception("TODO: Parse error");
        }
        return $attrName;
    }

    protected function parseCloseTag(HtmlStream $buffer) {
        if (!$buffer->readOnly("/", $errors)) {
            throw new \Exception("TODO: Parse error");
        }
        $tagName = $buffer->consumeUntil($this->andWhitespace(">"), $errors, $eof);
        if ($eof) {
            throw new \Exception("TODO: Parse error");
        }
        $buffer->consume(self::WHITESPACE, $errors, $eof);
        if (!$buffer->readOnly(">", $errors)) {
            throw new \Exception("TODO: Parse error");
        }
        return $tagName;
    }

    protected function readStartTagName(HtmlStream $buffer) {
        $tagName = $buffer->consumeUntil($this->andWhitespace(["/", ">"]), $errors, $eof);
        if ($eof) {
            throw new \Exception("TODO: Parse error");
        }
        return $tagName;
    }


    protected function isVoidElement($tagName) {
        return isset($this->voidElements[$tagName]);
    }

}


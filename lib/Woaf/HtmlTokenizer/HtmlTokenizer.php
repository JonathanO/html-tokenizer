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

    private function getBasicStateSwitcher($newState, callable $andThen = null, $doConsume = true) {
        return function($read, &$data, &$consume) use ($newState, $andThen, $doConsume) {
            $consume = $doConsume;
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
        } else {
            $actions["\0"] = $this->getParseErrorAndContinue(ParseErrors::getUnexpectedNullCharacter(), $errors);
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
                $consume = true;
                $continue = $actions[$read]($read, $data, $consume);
                if (!$consume) {
                    $buffer->unconsume();
                }
                if (!$continue) {
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
                    $next = $buffer->read($errors);
                    switch ($next) {
                        case "!":
                            $this->setState(State::$STATE_MARKUP_DECLARATION_OPEN);
                            break;
                        case "/":
                            $this->setState(State::$STATE_END_TAG_OPEN);
                            break;
                        case "?":
                            $this->parseError(ParseErrors::getUnexpectedQuestionMarkInsteadOfTagName(), $errors);
                            $buffer->unconsume();
                            $this->comment = "";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                            break;
                        default:
                            if (preg_match("/[a-zA-Z]/u", $next)) {
                                $this->currentTokenBuilder = HtmlStartTagToken::builder();
                                $buffer->unconsume();
                                $this->setState(State::$STATE_TAG_NAME);
                            } else {
                                if ($next === null) {
                                    $this->parseError(ParseErrors::getEofBeforeTagName(), $errors);
                                    $this->emit(new HtmlCharToken("<"), $tokens);
                                    $done = true;
                                } else {
                                    $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName(), $errors);
                                    $this->emit(new HtmlCharToken("<"), $tokens);
                                    $buffer->unconsume();
                                    $this->setState(State::$STATE_DATA);
                                }

                            }
                    }
                    break;
                case State::$STATE_END_TAG_OPEN:
                    $next = $buffer->read($errors);
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $buffer->unconsume();
                        $this->setState(State::$STATE_TAG_NAME);
                    } elseif ($next == ">") {
                        $this->parseError(ParseErrors::getMissingEndTagName(), $errors);
                        $this->setState(State::$STATE_DATA);
                    } elseif ($next == null) {
                        $this->parseError(ParseErrors::getEofBeforeTagName(), $errors);
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $done = true;
                    } else {
                        $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName(), $errors);
                        $buffer->unconsume();
                        $this->comment = "";
                        $this->setState(State::$STATE_BOGUS_COMMENT);
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
                            $this->setState(State::$STATE_DATA);
                            $this->currentTokenBuilder->setName($data);
                            $this->emit($this->currentTokenBuilder->build($errors), $tokens);
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
                        function ($read, $data) use (&$errors, &$done) {
                            $this->currentTokenBuilder->setName($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_RCDATA_LT_SIGN:
                    $next = $buffer->read($errors);
                    if ($next === "/") {
                        $this->setState(State::$STATE_RCDATA_END_TAG_OPEN);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $buffer->unconsume();
                        $this->setState(State::$STATE_RCDATA);
                    }
                    break;
                case State::$STATE_RCDATA_END_TAG_OPEN:
                    $next = $buffer->read($errors);
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $buffer->unconsume();
                        $this->setState(State::$STATE_RCDATA_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $buffer->unconsume();
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
                        switch ($buffer->read($errors)) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $this->currentTokenBuilder->setName($name);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $buffer->unconsume();
                                $this->setState(State::$STATE_RCDATA);
                        }
                    }
                    break;
                case State::$STATE_RAWTEXT_LT_SIGN:
                    $next = $buffer->read($errors);
                    if ($next === "/") {
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_OPEN);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $buffer->unconsume();
                        $this->setState(State::$STATE_RAWTEXT);
                    }
                    break;
                case State::$STATE_RAWTEXT_END_TAG_OPEN:
                    $next = $buffer->read($errors);
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $buffer->unconsume();
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $buffer->unconsume();
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
                        switch ($buffer->read($errors)) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $this->currentTokenBuilder->setName($name);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $buffer->unconsume();
                                $this->setState(State::$STATE_RAWTEXT);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_LT_SIGN:
                    $next = $buffer->read($errors);
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_OPEN);
                    } elseif ($next === "!") {
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START);
                        $this->emit(new HtmlCharToken("<!"), $tokens);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $buffer->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_END_TAG_OPEN:
                    $next = $buffer->read($errors);
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder()();
                        $buffer->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $buffer->unconsume();
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
                        switch ($buffer->read($errors)) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $this->currentTokenBuilder->setName($name);
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $this->currentTokenBuilder->setName($name);
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $buffer->unconsume();
                                $this->setState(State::$STATE_SCRIPT_DATA);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START:
                    if ($buffer->read($errors) == "-") {
                        $this->emit(new HtmlCharToken("-"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH);
                    } else {
                        $buffer->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH:
                    if ($buffer->read($errors) == "-") {
                        $this->emit(new HtmlCharToken("-"), $tokens);
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                    } else {
                        $buffer->unconsume();
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
                        $done = true;
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH:
                    switch ($read = $buffer->read($errors)) {
                        case "-":
                            $this->emit(new HtmlCharToken("-"), $tokens);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                            break;
                        case "<":
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case "\0":
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            $this->emit(new HtmlCharToken($this->FFFDReplacementCharacter), $tokens);
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                            $done = true;
                            break;
                        default:
                            $this->emit(new HtmlCharToken($read), $tokens);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH:
                    switch ($read = $buffer->read($errors)) {
                        case "-":
                            $this->emit(new HtmlCharToken("-"), $tokens);
                            break;
                        case "<":
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case ">":
                            $this->emit(new HtmlCharToken(">"), $tokens);
                            $this->setState(State::$STATE_SCRIPT_DATA);
                            break;
                        case "\0":
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter(), $errors);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            $this->emit(new HtmlCharToken($this->FFFDReplacementCharacter), $tokens);
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                            $done = true;
                            break;
                        default:
                            $this->emit(new HtmlCharToken($read), $tokens);
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN:
                    $next = $buffer->read($errors);
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN);
                    } else if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $buffer->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START);
                    } else {
                        $this->emit(new HtmlCharToken("<"), $tokens);
                        $buffer->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN:
                    $next = $buffer->read($errors);
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder()();
                        $buffer->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"), $tokens);
                        $buffer->unconsume();
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
                        switch ($buffer->read($errors)) {
                            case " ":
                            case "\t":
                            case "\n":
                            case "\f":
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer), $tokens);
                                $buffer->unconsume();
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    switch ($read = $buffer->read($errors)) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\f":
                        case "/":
                        case ">":
                            $name = strtolower($tempBuffer);
                            if ($name == "script") {
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                            } else {
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            }
                            $tempBuffer .= $read;
                            break;
                        default:
                            // Valid if peek returns null too. I think? TODO
                            $buffer->unconsume();
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    $this->emit(new HtmlCharToken($tempBuffer), $tokens);
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED:
                    $this->consumeDataNoEntityReplacement(
                        $buffer,
                        [
                            "-" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH, true],
                            "<" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN, true],
                        ],
                        $errors,
                        $tokens,
                        $eof
                    );
                    if ($eof) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                        $done = true;
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH:
                    $char = $buffer->read($errors);
                    if ($char == null) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText(), $errors);
                        $done = true;
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
                        $done = true;
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
                    $next = $buffer->read($errors);
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END);
                        $this->emit(new HtmlCharToken("/"), $tokens);
                    } else {
                        $buffer->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END:
                    $tempBuffer = $buffer->pConsume("[a-zA-Z]+", $errors);
                    switch ($read = $buffer->read($errors)) {
                        case " ":
                        case "\t":
                        case "\n":
                        case "\f":
                        case "/":
                        case ">":
                            $name = strtolower($tempBuffer);
                            if ($name == "script") {
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            } else {
                                $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                            }
                            $tempBuffer .= $read;
                            break;
                        default:
                            $buffer->unconsume();
                            $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    $this->emit(new HtmlCharToken($tempBuffer), $tokens);
                    break;
                case State::$STATE_BEFORE_ATTRIBUTE_NAME:
                    $next = $buffer->read($errors);
                    if ($next == null || $next == "/" || $next == ">") {
                        $buffer->unconsume();
                        $this->setState(State::$STATE_AFTER_ATTRIBUTE_NAME);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                break;
                            case "=":
                                $this->parseError(ParseErrors::getUnexpectedEqualsSignBeforeAttributeName(), $errors);
                                $this->currentTokenBuilder->startAttributeName("=");
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                                break;
                            default:
                                $this->currentTokenBuilder->startAttributeName("");
                                $buffer->unconsume();
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                                break;
                        }
                    }
                    break;
                case State::$STATE_ATTRIBUTE_NAME:
                    $addAttributeName = function($read, &$data) use (&$errors) {
                        $this->finishAttributeNameOrParseError($data, $errors);
                    };
                    $afterANameSwitcher = $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_NAME, $addAttributeName, false);
                    $toLowerCase = function($read, &$data) {
                        $data .= strtolower($read);
                        return true;
                    };
                    $actions = [
                        "\t" => $afterANameSwitcher,
                        "\n" => $afterANameSwitcher,
                        "\f" => $afterANameSwitcher,
                        " " => $afterANameSwitcher,
                        "/" => $afterANameSwitcher,
                        ">" => $afterANameSwitcher,
                        "=" => $this->getBasicStateSwitcher(State::$STATE_BEFORE_ATTRIBUTE_VALUE, $addAttributeName),
                        "\0" => $this->getNullReplacer($errors),
                        "\"" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors),
                        "'" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors),
                        "<" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName(), $errors),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    $this->consume(
                        $buffer,
                        $actions,
                        $afterANameSwitcher,
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_NAME:
                    $buffer->consume(["\t", "\n", "\f", " "], $errors);
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag(), $errors);
                        $done = true;
                    } else {
                        switch ($next) {
                            case "/":
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case "=":
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_VALUE);
                                break;
                            case ">":
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $buffer->unconsume();
                                $this->currentTokenBuilder->startAttributeName('');
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_BEFORE_ATTRIBUTE_VALUE:
                    $buffer->consume(["\t", "\n", "\f", " "], $errors);
                    switch ($read = $buffer->read($errors)) {
                        case "\"":
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED);
                            break;
                        case "'":
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED);
                            break;
                        case ">":
                            $this->parseError(ParseErrors::getMissingAttributeValue(), $errors);
                            $this->setState(State::$STATE_DATA);
                            $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                            break;
                        default:
                            $buffer->unconsume();
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
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
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $done = true;
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
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_UNQUOTED:
                    $switchBeforeAttrName = $this->getBasicStateSwitcher(State::$STATE_BEFORE_ATTRIBUTE_NAME, function($read, &$data) {
                        $this->finishAttributeValueOrDiscard($data);
                    }, false);
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
                            "\"" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "'" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "<" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "=" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                            "`" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue(), $errors),
                        ],
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag(), $errors);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED:
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag(), $errors);
                        $done = true;
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                                break;
                            case "/":
                                $this->setState(State::$STATE_SELF_CLOSING_START_TAG);
                                break;
                            case ">":
                                $this->emit($this->currentTokenBuilder->build($errors), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingWhitespaceBetweenAttributes(), $errors);
                                $buffer->unconsume();
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_SELF_CLOSING_START_TAG:
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag(), $errors);
                        $done = true;
                    } else {
                        if ($next == ">") {
                            $this->emit($this->currentTokenBuilder->isSelfClosing(true)->build($errors), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->parseError(ParseErrors::getUnexpectedSolidusInTag(), $errors);
                            $buffer->unconsume();
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
                        function ($read, $data) use (&$tokens, &$done) {
                            $this->emit(new HtmlCommentToken($this->comment . $data), $tokens);
                            $done = true;
                        },
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
                            //$this->setState(State::$STATE_CDATA_SECTION);
                            $this->parseError(ParseErrors::getCdataInHtmlContent(), $errors);
                            $this->comment = "[CDATA[";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        } else {
                            $this->parseError(ParseErrors::getIncorrectlyOpenedComment(), $errors);
                            $this->comment = "";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_START:
                    switch ($buffer->read($errors)) {
                        case "-":
                            $this->setState(State::$STATE_COMMENT_START_DASH);
                            break;
                        case ">":
                            $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment(), $errors);
                            $this->emit(new HtmlCommentToken($this->comment), $tokens);
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $buffer->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                    }
                    break;
                case State::$STATE_COMMENT_START_DASH:
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $done = true;
                    } else {
                        switch ($next) {
                            case "-":
                                $this->setState(State::$STATE_COMMENT_END);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment(), $errors);
                                $this->emit(new HtmlCommentToken($this->comment), $tokens);
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->comment .= "-";
                                $buffer->unconsume();
                                $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT:
                    $this->consume($buffer,
                        [
                            "<" => $this->getBasicStateSwitcher(State::$STATE_COMMENT_LT_SIGN, function($read, &$data) { $this->comment .= $data . $read; }),
                            "-" => $this->getBasicStateSwitcher(State::$STATE_COMMENT_END_DASH, function($read, &$data) { $this->comment .= $data; }),
                            "\0" => $this->getNullReplacer($errors)
                        ],
                        function ($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->comment .= $data;
                            $this->parseError(ParseErrors::getEofInComment(), $errors);
                            $this->emit(new HtmlCommentToken($this->comment), $tokens);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_COMMENT_LT_SIGN:
                    switch ($buffer->read($errors)) {
                        case "!":
                            $this->comment .= "!";
                            $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG);
                            break;
                        case "<":
                            $this->comment .= "<";
                            break;
                        default:
                            $buffer->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                            break;
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG:
                    if ($buffer->read($errors) == "-") {
                        $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG_DASH);
                    } else {
                        $buffer->unconsume();
                        $this->setState(State::$STATE_COMMENT);
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG_DASH:
                    if ($buffer->read($errors) == "-") {
                        $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG_DASH_DASH);
                    } else {
                        $buffer->unconsume();
                        $this->setState(State::$STATE_COMMENT_END_DASH);
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG_DASH_DASH:
                    $read = $buffer->read($errors);
                    if ($read == null || $read == "-") {
                        $buffer->unconsume();
                        $this->setState(State::$STATE_COMMENT_END);
                    } else {
                        $this->parseError(ParseErrors::getNestedComment(), $errors);
                        $buffer->unconsume();
                        $this->setState(State::$STATE_COMMENT_END);
                    }
                    break;
                case State::$STATE_COMMENT_END_DASH:
                    $read = $buffer->read($errors);
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $done = true;
                    } else {
                        if ($read == "-") {
                            $this->setState(State::$STATE_COMMENT_END);
                        } else {
                            $this->comment .= "-";
                            $buffer->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_END:
                    $read = $buffer->read($errors);
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $done = true;
                    } else {
                        if ($read == ">") {
                            $this->emit(new HtmlCommentToken($this->comment), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } elseif ($read == "!") {
                            $this->setState(State::$STATE_COMMENT_END_BANG);
                        } elseif ($read == "-") {
                            $this->comment .= "-";
                        } else {
                            $this->comment .= "--";
                            $buffer->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_END_BANG:
                    $read = $buffer->read($errors);
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment(), $errors);
                        $this->emit(new HtmlCommentToken($this->comment), $tokens);
                        $done = true;
                    } else {
                        if ($read == "-") {
                            $this->comment .= "--!";
                            $this->setState(State::$STATE_COMMENT_END_DASH);
                        } elseif ($read == ">") {
                            $this->parseError(ParseErrors::getIncorrectlyClosedComment(), $errors);
                            $this->emit(new HtmlCommentToken($this->comment), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->comment .= "--!";
                            $buffer->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE:
                    switch ($buffer->read($errors)) {
                        case "\t":
                        case "\n":
                        case "\f":
                        case " ":
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                        case ">":
                            $buffer->unconsume();
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                        case null:
                            $this->emit(HtmlDocTypeToken::builder()->isForceQuirks(true)->build(), $tokens);
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $done = true;
                            break;
                        default:
                            $this->parseError(ParseErrors::getMissingWhitespaceBeforeDoctypeName(), $errors);
                            $buffer->unconsume();
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_NAME:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $this->currentDoctypeBuilder = HtmlDocTypeToken::builder();
                    switch ($buffer->read($errors)) {
                        case ">":
                            $this->parseError(ParseErrors::getMissingDoctypeName(), $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                            $this->setState(State::$STATE_DATA);
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                            $done = true;
                            break;
                        default:
                            // Deviation from the spec, but I think it's equivalent.
                            $buffer->unconsume();
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
                        function ($read, $data) use (&$tokens, &$errors, &$done) {
                            $this->emit($this->currentDoctypeBuilder->setName($data)->isForceQuirks(true)->build(), $tokens);
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_NAME:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $first = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $done = true;
                    } else {
                        if ($next == ">") {
                            $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $next = strtoupper($buffer->peek(5));
                            if ($first . $next == "PUBLIC") {
                                $buffer->read($errors, 5);
                                $this->setState(State::$STATE_AFTER_DOCTYPE_PUBLIC_KEYWORD);
                            } elseif ($first . $next == "SYSTEM") {
                                $buffer->read($errors, 5);
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
                        $done = true;
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
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED:
                    $this->consume(
                        $buffer,
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED:
                    $this->consume(
                        $buffer,
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            })                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER:
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $this->setState(State::$STATE_DATA);
                        $done = true;
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
                        $done = true;
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
                        $done = true;
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
                        $done = true;
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
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED:
                    $this->consume(
                        $buffer,
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypeSystemIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED:
                    $this->consume(
                        $buffer,
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer($errors),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypeSystemIdentifier(), $errors);
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build(), $tokens);
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER:
                    $buffer->consume(self::WHITESPACE, $errors);
                    $next = $buffer->read($errors);
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype(), $errors);
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build(), $tokens);
                        $done = true;
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
                    $read = $buffer->read($errors);
                    $this->emit($this->currentDoctypeBuilder->build(), $tokens);
                    if ($read == null) {
                        $done = true;
                    } else {
                        $this->setState(State::$STATE_DATA);
                    }
                    break;
                case State::$STATE_CDATA_SECTION:
                    $consumed = $buffer->consumeUntil("]", $errors, $eof);
                    if ($eof) {
                        $this->parseError(ParseErrors::getEofInCdata(), $errors);
                        $done = true;
                    } else {
                        $tokens[] = new HtmlCharToken($consumed);
                        $this->setState(State::$STATE_CDATA_SECTION_BRACKET);
                    }
                    break;
                case State::$STATE_CDATA_SECTION_BRACKET:
                    if ($buffer->read($errors) == "]") {
                        $this->setState(State::$STATE_CDATA_SECTION_END);
                    } else {
                        $tokens[] = new HtmlCharToken("]");
                        $buffer->unconsume();
                        $this->setState(State::$STATE_CDATA_SECTION);
                    }
                    break;
                case State::$STATE_CDATA_SECTION_END:
                    switch ($buffer->read($errors)) {
                        case "]":
                            $tokens[] = new HtmlCharToken("]");
                            break;
                        case ">":
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $tokens[] = new HtmlCharToken("]]");
                            $buffer->unconsume();
                            $this->setState(State::$STATE_CDATA_SECTION);
                    }
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


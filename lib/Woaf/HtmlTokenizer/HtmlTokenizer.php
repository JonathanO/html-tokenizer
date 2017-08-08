<?php


namespace Woaf\HtmlTokenizer;

use Woaf\HtmlTokenizer\HtmlTokens\Builder\ErrorReceiver;
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
use Woaf\HtmlTokenizer\Tables\ParseErrors;
use Woaf\HtmlTokenizer\Tables\State;

/**
 * @property LoggerInterface logger
 */
class HtmlTokenizer
{
    use LoggerAwareTrait;

    private static $FFFDReplacementCharacter = 	"\xEF\xBF\xBD";

    private $entityReplacementTable;

    /**
     * @var int
     */
    private $curState;

    /**
     * @var TokenizerState
     */
    private $state;

    /**
     * @var HtmlTagTokenBuilder
     */
    private $currentTokenBuilder = null;

    /**
     * @var HtmlDocTypeTokenBuilder
     */
    private $currentDoctypeBuilder = null;

    /**
     * @var HtmlStream
     */
    private $stream;

    public function pushState($state, $lastStartTagName) {
        $this->setState($state);
        $this->lastStartTagName = $lastStartTagName;
    }

    public function getState() {
        return $this->state->getState();
    }


    private function setState($state) {
        if ($this->logger) {
            $curState = $this->getState();
            $curName = State::toName($curState);
            $newName = State::toName($state);
            $this->logger->debug("State change {$curName}({$curState}) => {$newName}({$state})");
        }
        $this->state->setState($state);
    }

    private function emit(HtmlToken $token) {
        if ($this->logger) $this->logger->debug("Emitting token " . $token);
        $this->receiver->consume($token, $this->state);
    }

    private function parseError(HtmlParseError $error) {
        if ($this->logger) $this->logger->debug("Encountered parse error " . $error);
        $this->receiver->error($error, $this->state);
    }


    public function __construct(HtmlStream $stream, TokenReceiver $receiver, LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->setLogger($logger);
        }
        $this->entityReplacementTable = new CharacterReferenceDecoder($logger);
        $this->state = new TokenizerState();
        $this->stream = $stream;
        $this->receiver = $receiver;
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

    private function getParseErrorAndContinue(HtmlParseError $error) {
        return function($read, &$data) use ($error) {
            $this->parseError($error);
            $data .= $read;
            return true;
        };
    }

    private function consumeDataWithEntityReplacement($ltState, $doNullReplacement, &$eof) {

        $andEmit = function($read, &$data) {
            if ($data !== "") {
                $this->emit(new HtmlCharToken($data));
            }
        };

        $actions = [
              "&" => $this->getEntityReplacer(),
              "<" => $this->getBasicStateSwitcher($ltState, $andEmit),
        ];
        if ($doNullReplacement) {
            $actions["\0"] = $this->getNullReplacer();
        } else {
            $actions["\0"] = $this->getParseErrorAndContinue(ParseErrors::getUnexpectedNullCharacter());
        }

        $this->consume(
            $actions,
            function($read, &$data) use (&$eof, $andEmit) {
                $eof = true;
                $andEmit(null, $data);
            }
        );
    }

    private function getEntityReplacer($additionalAllowedChar = null, $inAttribute = false)
    {
        return function ($read, &$data) use ($additionalAllowedChar, $inAttribute) {
            $data .= $this->entityReplacementTable->consumeCharRef($this->stream, $this->errorReceiver(), $additionalAllowedChar, $inAttribute);
            return true;
        };
    }

    private function getNullReplacer()
    {
        return function($read, &$data)
        {
            $this->parseError(ParseErrors::getUnexpectedNullCharacter());
            $data .= self::$FFFDReplacementCharacter;
            return true;
        };
    }

    /**
     * @param array $states map of char found to [new state, emit?]
     * @param $eof
     * @throws \Exception
     */
    private function consumeDataNoEntityReplacement(array $states, &$eof) {

        $andEmit = function($read, &$data) {
            if ($data !== "") {
                $this->emit(new HtmlCharToken($data));
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
        $actions["\0"] = $this->getNullReplacer();

        $this->consume(
                $actions,
                function($read, &$data) use (&$eof, $andEmit) {
                    $eof = true;
                    $andEmit(null, $data);
                }
            );
    }

    private function consume(array $actions, callable $onEof) {
        $data = "";
        $eof = false;
        while (true) {
            $data .= $this->stream->consumeUntil(array_keys($actions), $this->errorReceiver(), $eof);
            $read = $this->stream->read($this->errorReceiver());
            if (isset($actions[$read])) {
                $consume = true;
                $continue = $actions[$read]($read, $data, $consume);
                if (!$consume) {
                    $this->stream->unconsume();
                }
                if (!$continue) {
                    break;
                }
            } else {
                break;
            }
        }
        if ($eof) {
            $noop = null;
            $onEof(null, $data, $noop);
        }
    }

    private $lastStartTagName = null;
    private $comment = "";

    private function errorReceiver() {
        return new ErrorReceiver($this->state, $this->receiver);
    }

    /**
     * @var TokenReceiver
     */
    private $receiver;

    /**
     * @return TokenizerResult
     * @throws \Exception
     */
    public function parse()
    {
        $done = false;
        while (!$done) {
            switch ($this->getState()) {
                case State::$STATE_DATA:
                    $this->consumeDataWithEntityReplacement(State::$STATE_TAG_OPEN, false, $done);
                    break;
                case State::$STATE_RCDATA:
                    $this->consumeDataWithEntityReplacement(State::$STATE_RCDATA_LT_SIGN, true, $done);
                    break;
                case State::$STATE_RAWTEXT:
                    $this->consumeDataNoEntityReplacement(["<" => [State::$STATE_RAWTEXT_LT_SIGN]], $done);
                    break;
                case State::$STATE_SCRIPT_DATA:
                    $this->consumeDataNoEntityReplacement(["<" => [State::$STATE_SCRIPT_DATA_LT_SIGN]], $done);
                    break;
                case State::$STATE_PLAINTEXT:
                    $this->consumeDataNoEntityReplacement([], $done);
                    break;
                case State::$STATE_TAG_OPEN:
                    $next = $this->stream->read($this->errorReceiver());
                    switch ($next) {
                        case "!":
                            $this->setState(State::$STATE_MARKUP_DECLARATION_OPEN);
                            break;
                        case "/":
                            $this->setState(State::$STATE_END_TAG_OPEN);
                            break;
                        case "?":
                            $this->parseError(ParseErrors::getUnexpectedQuestionMarkInsteadOfTagName());
                            $this->stream->unconsume();
                            $this->comment = "";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                            break;
                        default:
                            if (preg_match("/[a-zA-Z]/u", $next)) {
                                $this->currentTokenBuilder = HtmlStartTagToken::builder();
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_TAG_NAME);
                            } else {
                                if ($next === null) {
                                    $this->parseError(ParseErrors::getEofBeforeTagName());
                                    $this->emit(new HtmlCharToken("<"));
                                    $done = true;
                                } else {
                                    $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName());
                                    $this->emit(new HtmlCharToken("<"));
                                    $this->stream->unconsume();
                                    $this->setState(State::$STATE_DATA);
                                }

                            }
                    }
                    break;
                case State::$STATE_END_TAG_OPEN:
                    $next = $this->stream->read($this->errorReceiver());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_TAG_NAME);
                    } elseif ($next == ">") {
                        $this->parseError(ParseErrors::getMissingEndTagName());
                        $this->setState(State::$STATE_DATA);
                    } elseif ($next == null) {
                        $this->parseError(ParseErrors::getEofBeforeTagName());
                        $this->emit(new HtmlCharToken("</"));
                        $done = true;
                    } else {
                        $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName());
                        $this->stream->unconsume();
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
                        ">" => function($read, &$data) use (&$tokens) {
                            $this->setState(State::$STATE_DATA);
                            $this->currentTokenBuilder->setName($data);
                            $this->emit($this->currentTokenBuilder->build(new ErrorReceiver($this->state, $this->receiver)));
                            return false;
                        },
                        "\0" => $this->getNullReplacer(),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    $this->consume(
                        $actions,
                        function ($read, $data) use (&$errors, &$done) {
                            $this->currentTokenBuilder->setName($data);
                            $this->parseError(ParseErrors::getEofInTag());
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_RCDATA_LT_SIGN:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next === "/") {
                        $this->setState(State::$STATE_RCDATA_END_TAG_OPEN);
                    } else {
                        $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RCDATA);
                    }
                    break;
                case State::$STATE_RCDATA_END_TAG_OPEN:
                    $next = $this->stream->read($this->errorReceiver());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RCDATA_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RCDATA);
                    }
                    break;
                case State::$STATE_RCDATA_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_RCDATA);
                    } else {
                        switch ($this->stream->read($this->errorReceiver())) {
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
                                $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_RCDATA);
                        }
                    }
                    break;
                case State::$STATE_RAWTEXT_LT_SIGN:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next === "/") {
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_OPEN);
                    } else {
                        $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RAWTEXT);
                    }
                    break;
                case State::$STATE_RAWTEXT_END_TAG_OPEN:
                    $next = $this->stream->read($this->errorReceiver());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RAWTEXT);
                    }
                    break;
                case State::$STATE_RAWTEXT_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_RAWTEXT);
                    } else {
                        switch ($this->stream->read($this->errorReceiver())) {
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
                                $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_RAWTEXT);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_LT_SIGN:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_OPEN);
                    } elseif ($next === "!") {
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START);
                        $this->emit(new HtmlCharToken("<!"));
                    } else {
                        $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_END_TAG_OPEN:
                    $next = $this->stream->read($this->errorReceiver());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    } else {
                        switch ($this->stream->read($this->errorReceiver())) {
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
                                $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_SCRIPT_DATA);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START:
                    if ($this->stream->read($this->errorReceiver()) == "-") {
                        $this->emit(new HtmlCharToken("-"));
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH:
                    if ($this->stream->read($this->errorReceiver()) == "-") {
                        $this->emit(new HtmlCharToken("-"));
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED:
                    $this->consumeDataNoEntityReplacement(
                                [
                                    "<" => [State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN],
                                    "-" => [State::$STATE_SCRIPT_DATA_ESCAPED_DASH, true],
                                ],
                                $eof
                    );
                    if ($eof) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText());
                        $done = true;
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH:
                    switch ($read = $this->stream->read($this->errorReceiver())) {
                        case "-":
                            $this->emit(new HtmlCharToken("-"));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                            break;
                        case "<":
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case "\0":
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter());
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText());
                            $done = true;
                            break;
                        default:
                            $this->emit(new HtmlCharToken($read));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH:
                    switch ($read = $this->stream->read($this->errorReceiver())) {
                        case "-":
                            $this->emit(new HtmlCharToken("-"));
                            break;
                        case "<":
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case ">":
                            $this->emit(new HtmlCharToken(">"));
                            $this->setState(State::$STATE_SCRIPT_DATA);
                            break;
                        case "\0":
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter());
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText());
                            $done = true;
                            break;
                        default:
                            $this->emit(new HtmlCharToken($read));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN);
                    } else if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START);
                    } else {
                        $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN:
                    $next = $this->stream->read($this->errorReceiver());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder();
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME);
                    } else {
                        $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    } else {
                        switch ($this->stream->read($this->errorReceiver())) {
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
                                $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START:
                    $tempBuffer = $this->stream->readAlpha();
                    switch ($read = $this->stream->read($this->errorReceiver())) {
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
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    $this->emit(new HtmlCharToken($tempBuffer));
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED:
                    $this->consumeDataNoEntityReplacement(
                        [
                            "-" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH, true],
                            "<" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN, true],
                        ],
                        $eof
                    );
                    if ($eof) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText());
                        $done = true;
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH:
                    $char = $this->stream->read($this->errorReceiver());
                    if ($char == null) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText());
                        $done = true;
                    } else {
                        if ($char == "\0") {
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter());
                            $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                        } else {
                            $this->emit(new HtmlCharToken($char));
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
                    $char = $this->stream->read($this->errorReceiver());
                    if ($char == null) {
                        $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText());
                        $done = true;
                    } else {
                        if ($char == "\0") {
                            $this->parseError(ParseErrors::getUnexpectedNullCharacter());
                            $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                        } else {
                            $this->emit(new HtmlCharToken($char));
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
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END);
                        $this->emit(new HtmlCharToken("/"));
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END:
                    $tempBuffer = $this->stream->readAlpha();
                    switch ($read = $this->stream->read($this->errorReceiver())) {
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
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    $this->emit(new HtmlCharToken($tempBuffer));
                    break;
                case State::$STATE_BEFORE_ATTRIBUTE_NAME:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null || $next == "/" || $next == ">") {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_AFTER_ATTRIBUTE_NAME);
                    } else {
                        switch ($next) {
                            case "\t":
                            case "\n":
                            case "\f":
                            case " ":
                                break;
                            case "=":
                                $this->parseError(ParseErrors::getUnexpectedEqualsSignBeforeAttributeName());
                                $this->currentTokenBuilder->startAttributeName("=");
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                                break;
                            default:
                                $this->currentTokenBuilder->startAttributeName("");
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                                break;
                        }
                    }
                    break;
                case State::$STATE_ATTRIBUTE_NAME:
                    $addAttributeName = function($read, &$data) {
                        $this->finishAttributeNameOrParseError($data);
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
                        "\0" => $this->getNullReplacer(),
                        "\"" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName()),
                        "'" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName()),
                        "<" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInAttributeName()),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    $this->consume(
                        $actions,
                        $afterANameSwitcher
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_NAME:
                    $this->stream->discardWhitespace();
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag());
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
                                $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->stream->unconsume();
                                $this->currentTokenBuilder->startAttributeName('');
                                $this->setState(State::$STATE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_BEFORE_ATTRIBUTE_VALUE:
                    $this->stream->discardWhitespace();
                    switch ($read = $this->stream->read($this->errorReceiver())) {
                        case "\"":
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED);
                            break;
                        case "'":
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED);
                            break;
                        case ">":
                            $this->parseError(ParseErrors::getMissingAttributeValue());
                            $this->setState(State::$STATE_DATA);
                            $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                            break;
                        default:
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
                    }
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED:
                    $this->consume(
                        $actions = [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED, function($read, &$data) {
                                $this->finishAttributeValueOrDiscard($data);
                            }),
                            "&" => $this->getEntityReplacer(null, true),
                            "\0" => $this->getNullReplacer(),
                        ],
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag());
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED:
                    $this->consume(
                        $actions = [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED, function($read, &$data) {
                                $this->finishAttributeValueOrDiscard($data);
                            }),
                            "&" => $this->getEntityReplacer(null, true),
                            "\0" => $this->getNullReplacer(),
                        ],
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag());
                            $done = true;
                        }
                        );
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_UNQUOTED:
                    $switchBeforeAttrName = $this->getBasicStateSwitcher(State::$STATE_BEFORE_ATTRIBUTE_NAME, function($read, &$data) {
                        $this->finishAttributeValueOrDiscard($data);
                    }, false);
                    $this->consume(
                        $actions = [
                            "\t" => $switchBeforeAttrName,
                            "\n" => $switchBeforeAttrName,
                            "\f" => $switchBeforeAttrName,
                            " " => $switchBeforeAttrName,
                            "&" => $this->getEntityReplacer( ">", true),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA,
                                function($read, &$data) use (&$tokens, &$errors) {
                                    $this->finishAttributeValueOrDiscard($data);
                                    $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                                }
                            ),
                            "\0" => $this->getNullReplacer(),
                            "\"" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue()),
                            "'" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue()),
                            "<" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue()),
                            "=" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue()),
                            "`" => $this->getParseErrorAndContinue(ParseErrors::getUnexpectedCharacterInUnquotedAttributeValue()),
                        ],
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            $this->parseError(ParseErrors::getEofInTag());
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag());
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
                                $this->emit($this->currentTokenBuilder->build($this->errorReceiver()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingWhitespaceBetweenAttributes());
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_SELF_CLOSING_START_TAG:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInTag());
                        $done = true;
                    } else {
                        if ($next == ">") {
                            $this->emit($this->currentTokenBuilder->isSelfClosing(true)->build($this->errorReceiver()));
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->parseError(ParseErrors::getUnexpectedSolidusInTag());
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_BOGUS_COMMENT:
                    $switchAndEmit = $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens) {
                        $this->emit(new HtmlCommentToken($this->comment . $data));
                    });
                    $this->consume(
                        [
                            ">" => $switchAndEmit,
                            "\0" => function($read, &$data) { $data .= self::$FFFDReplacementCharacter; return true; }
                        ],
                        function ($read, $data) use (&$done) {
                            $this->emit(new HtmlCommentToken($this->comment . $data));
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_MARKUP_DECLARATION_OPEN:
                    if ($this->stream->peek(2) == "--") {
                        $this->stream->read($this->errorReceiver(), 2);
                        $this->comment = "";
                        $this->setState(State::$STATE_COMMENT_START);
                    } else {
                        $peeked = $this->stream->peek(7);
                        if (strtoupper($peeked) == "DOCTYPE") {
                            $this->stream->read($this->errorReceiver(), 7);
                            $this->setState(State::$STATE_DOCTYPE);
                        } elseif ($peeked == "[CDATA[") {// TODO check stack!
                            $this->stream->read($this->errorReceiver(), 7);
                            //$this->setState(State::$STATE_CDATA_SECTION);
                            $this->parseError(ParseErrors::getCdataInHtmlContent());
                            $this->comment = "[CDATA[";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        } else {
                            $this->parseError(ParseErrors::getIncorrectlyOpenedComment());
                            $this->comment = "";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_START:
                    switch ($this->stream->read($this->errorReceiver())) {
                        case "-":
                            $this->setState(State::$STATE_COMMENT_START_DASH);
                            break;
                        case ">":
                            $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment());
                            $this->emit(new HtmlCommentToken($this->comment));
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                    }
                    break;
                case State::$STATE_COMMENT_START_DASH:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInComment());
                        $this->emit(new HtmlCommentToken($this->comment));
                        $done = true;
                    } else {
                        switch ($next) {
                            case "-":
                                $this->setState(State::$STATE_COMMENT_END);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment());
                                $this->emit(new HtmlCommentToken($this->comment));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->comment .= "-";
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT:
                    $this->consume(
                        [
                            "<" => $this->getBasicStateSwitcher(State::$STATE_COMMENT_LT_SIGN, function($read, &$data) { $this->comment .= $data . $read; }),
                            "-" => $this->getBasicStateSwitcher(State::$STATE_COMMENT_END_DASH, function($read, &$data) { $this->comment .= $data; }),
                            "\0" => $this->getNullReplacer()
                        ],
                        function ($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->comment .= $data;
                            $this->parseError(ParseErrors::getEofInComment());
                            $this->emit(new HtmlCommentToken($this->comment));
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_COMMENT_LT_SIGN:
                    switch ($this->stream->read($this->errorReceiver())) {
                        case "!":
                            $this->comment .= "!";
                            $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG);
                            break;
                        case "<":
                            $this->comment .= "<";
                            break;
                        default:
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                            break;
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG:
                    if ($this->stream->read($this->errorReceiver()) == "-") {
                        $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT);
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG_DASH:
                    if ($this->stream->read($this->errorReceiver()) == "-") {
                        $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG_DASH_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT_END_DASH);
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG_DASH_DASH:
                    $read = $this->stream->read($this->errorReceiver());
                    if ($read == null || $read == "-") {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT_END);
                    } else {
                        $this->parseError(ParseErrors::getNestedComment());
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT_END);
                    }
                    break;
                case State::$STATE_COMMENT_END_DASH:
                    $read = $this->stream->read($this->errorReceiver());
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment());
                        $this->emit(new HtmlCommentToken($this->comment));
                        $done = true;
                    } else {
                        if ($read == "-") {
                            $this->setState(State::$STATE_COMMENT_END);
                        } else {
                            $this->comment .= "-";
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_END:
                    $read = $this->stream->read($this->errorReceiver());
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment());
                        $this->emit(new HtmlCommentToken($this->comment));
                        $done = true;
                    } else {
                        if ($read == ">") {
                            $this->emit(new HtmlCommentToken($this->comment));
                            $this->setState(State::$STATE_DATA);
                        } elseif ($read == "!") {
                            $this->setState(State::$STATE_COMMENT_END_BANG);
                        } elseif ($read == "-") {
                            $this->comment .= "-";
                        } else {
                            $this->comment .= "--";
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_END_BANG:
                    $read = $this->stream->read($this->errorReceiver());
                    if ($read === null) {
                        $this->parseError(ParseErrors::getEofInComment());
                        $this->emit(new HtmlCommentToken($this->comment));
                        $done = true;
                    } else {
                        if ($read == "-") {
                            $this->comment .= "--!";
                            $this->setState(State::$STATE_COMMENT_END_DASH);
                        } elseif ($read == ">") {
                            $this->parseError(ParseErrors::getIncorrectlyClosedComment());
                            $this->emit(new HtmlCommentToken($this->comment));
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->comment .= "--!";
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE:
                    switch ($this->stream->read($this->errorReceiver())) {
                        case "\t":
                        case "\n":
                        case "\f":
                        case " ":
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                        case ">":
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                        case null:
                            $this->emit(HtmlDocTypeToken::builder()->isForceQuirks(true)->build());
                            $this->parseError(ParseErrors::getEofInDoctype());
                            $done = true;
                            break;
                        default:
                            $this->parseError(ParseErrors::getMissingWhitespaceBeforeDoctypeName());
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_NAME:
                    $this->stream->discardWhitespace();
                    $this->currentDoctypeBuilder = HtmlDocTypeToken::builder();
                    switch ($this->stream->read($this->errorReceiver())) {
                        case ">":
                            $this->parseError(ParseErrors::getMissingDoctypeName());
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                            $this->setState(State::$STATE_DATA);
                            break;
                        case null:
                            $this->parseError(ParseErrors::getEofInDoctype());
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                            $done = true;
                            break;
                        default:
                            // Deviation from the spec, but I think it's equivalent.
                            $this->stream->unconsume();
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
                            $this->emit($this->currentDoctypeBuilder->build());
                            $this->setState(State::$STATE_DATA);
                            return false;
                        },
                        "\0" => $this->getNullReplacer(),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    $this->consume(
                        $actions,
                        function ($read, $data) use (&$tokens, &$errors, &$done) {
                            $this->emit($this->currentDoctypeBuilder->setName($data)->isForceQuirks(true)->build());
                            $this->parseError(ParseErrors::getEofInDoctype());
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_NAME:
                    $this->stream->discardWhitespace();
                    $first = $this->stream->read($this->errorReceiver());
                    if ($first == null) {
                        $this->parseError(ParseErrors::getEofInDoctype());
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        $done = true;
                    } else {
                        if ($first == ">") {
                            $this->emit($this->currentDoctypeBuilder->build());
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $potentialKeyword = strtoupper($first . $this->stream->peek(5));
                            if ($potentialKeyword == "PUBLIC") {
                                $this->stream->read($this->errorReceiver(), 5);
                                $this->setState(State::$STATE_AFTER_DOCTYPE_PUBLIC_KEYWORD);
                            } elseif ($potentialKeyword == "SYSTEM") {
                                $this->stream->read($this->errorReceiver(), 5);
                                $this->setState(State::$STATE_AFTER_DOCTYPE_SYSTEM_KEYWORD);
                            } else {
                                $this->parseError(ParseErrors::getInvalidCharacterSequenceAfterDoctypeName());
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                            }
                        }
                    }
                    break;
                case State::$STATE_AFTER_DOCTYPE_PUBLIC_KEYWORD:
                    $read = $this->stream->read($this->errorReceiver());
                    if ($read == null) {
                        $this->parseError(ParseErrors::getEofInDoctype());
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypePublicKeyword());
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypePublicKeyword());
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getMissingDoctypePublicIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypePublicIdentifier());
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_PUBLIC_IDENTIFIER:
                    $this->stream->discardWhitespace();
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        $this->parseError(ParseErrors::getEofInDoctype());
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
                                $this->parseError(ParseErrors::getMissingDoctypePublicIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypePublicIdentifier());
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED:
                    $this->consume(
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype());
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED:
                    $this->consume(
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            })                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype());
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            $done = true;
                        }

                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER:
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype());
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                $this->emit($this->currentDoctypeBuilder->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            case "\"":
                                $this->parseError(ParseErrors::getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers());
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->parseError(ParseErrors::getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers());
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier());
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BETWEEN_DOCTYPE_PUBLIC_AND_SYSTEM_IDENTIFIERS:
                    $this->stream->discardWhitespace();
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        $this->parseError(ParseErrors::getEofInDoctype());
                        $done = true;
                    } else {
                        switch ($next) {
                            case ">":
                                $this->emit($this->currentDoctypeBuilder->build());
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
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier());
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_AFTER_DOCTYPE_SYSTEM_KEYWORD:
                    $read = $this->stream->read($this->errorReceiver());
                    if ($read == null) {
                        $this->parseError(ParseErrors::getEofInDoctype());
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypeSystemKeyword());
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypeSystemKeyword());
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                $this->parseError(ParseErrors::getMissingDoctypeSystemIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier());
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_SYSTEM_IDENTIFIER:
                    $this->stream->discardWhitespace();
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype());
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                $this->parseError(ParseErrors::getMissingDoctypeSystemIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier());
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED:
                    $this->consume(
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypeSystemIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype());
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED:
                    $this->consume(
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                $this->parseError(ParseErrors::getAbruptDoctypeSystemIdentifier());
                                $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->parseError(ParseErrors::getEofInDoctype());
                            $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER:
                    $this->stream->discardWhitespace();
                    $next = $this->stream->read($this->errorReceiver());
                    if ($next == null) {
                        $this->parseError(ParseErrors::getEofInDoctype());
                        $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        $done = true;
                    } else {
                        if ($next == ">") {
                            $this->emit($this->currentDoctypeBuilder->build());
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->parseError(ParseErrors::getUnexpectedCharacterAfterDoctypeSystemIdentifier());
                            $this->setState(State::$STATE_BOGUS_DOCTYPE);
                            break;
                        }
                    }
                    break;
                case State::$STATE_BOGUS_DOCTYPE:
                    $this->consume(
                        [
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens) {
                                $this->emit($this->currentDoctypeBuilder->build());
                            }),
                        ],
                        function($read, &$data) use (&$done, &$tokens) {
                            $this->emit($this->currentDoctypeBuilder->build());
                            $done = true;
                        }
                        );
                    break;
                case State::$STATE_CDATA_SECTION:
                    $consumed = $this->stream->consumeUntil("]", $this->errorReceiver(), $eof);
                    if ($consumed != "") {
                        $this->emit(new HtmlCharToken($consumed));
                    }
                    if ($eof) {
                        $this->parseError(ParseErrors::getEofInCdata());
                        $done = true;
                    } else {
                        $this->setState(State::$STATE_CDATA_SECTION_BRACKET);
                    }
                    break;
                case State::$STATE_CDATA_SECTION_BRACKET:
                    if ($this->stream->read($this->errorReceiver()) == "]") {
                        $this->setState(State::$STATE_CDATA_SECTION_END);
                    } else {
                        $this->emit(new HtmlCharToken("]"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_CDATA_SECTION);
                    }
                    break;
                case State::$STATE_CDATA_SECTION_END:
                    switch ($this->stream->read($this->errorReceiver())) {
                        case "]":
                            $this->emit(new HtmlCharToken("]"));
                            break;
                        case ">":
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $this->emit(new HtmlCharToken("]]"));
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_CDATA_SECTION);
                    }
                    break;
                default:
                    throw new \Exception("TODO: Parse error invalid state: " . $this->getState());
            }
        }
    }

    private function finishAttributeNameOrParseError($name) {
        try {
            $this->currentTokenBuilder->finishAttributeName($name);
        } catch (\Exception $e) {
            // TODO more specific excpetion
            $this->parseError(ParseErrors::getDuplicateAttribute());
        }
    }

    private function finishAttributeValueOrDiscard($value) {
        try {
            $this->currentTokenBuilder->finishAttributeValue($value);
        } catch (\Exception $ignored) {
            // TODO more specific excpetion
        }
    }

}


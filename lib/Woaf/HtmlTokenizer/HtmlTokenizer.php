<?php


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

    private $errorQueue = [];

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
        yield from $this->errorQueue;
        $this->errorQueue = [];
        yield $token;
    }

    private function parseError(HtmlParseError $error) {
        if ($this->logger) $this->logger->debug("Encountered parse error " . $error);
        yield from $this->errorQueue;
        $this->errorQueue = [];
        yield $error;
    }


    public function __construct(HtmlStream $stream, LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->setLogger($logger);
        }
        $this->entityReplacementTable = new CharacterReferenceDecoder($logger);
        $this->state = new TokenizerState();
        $this->stream = $stream;
    }

    private function getBasicStateSwitcher($newState, callable $andThen = null, $doConsume = true) {
        return function($read, &$data, &$consume, &$continue) use ($newState, $andThen, $doConsume) {
            $consume = $doConsume;
            $continue = false;
            $this->setState($newState);
            if ($andThen != null) {
                yield from $this->yieldOrFail($andThen($read, $data));
            }
        };
    }

    private function getParseErrorAndContinue(\Closure $errorFactory) {
        return function($read, &$data) use ($errorFactory) {
            yield from $this->parseError($errorFactory($this->stream->getLineAndColumn()));
            $data .= $read;
        };
    }

    private function consumeDataWithEntityReplacement($ltState, $doNullReplacement, &$eof) {

        $andEmit = function($read, &$data) {
            if ($data !== "") {
                yield from $this->emit(new HtmlCharToken($data));
            }
        };

        $actions = [
              "&" => $this->getEntityReplacer(),
              "<" => $this->getBasicStateSwitcher($ltState, $andEmit),
        ];
        if ($doNullReplacement) {
            $actions["\0"] = $this->getNullReplacer();
        } else {
            $actions["\0"] = $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedNullCharacter);
        }

        yield from $this->consume(
            $actions,
            function($read, &$data) use (&$eof, $andEmit) {
                $eof = true;
                yield from $andEmit(null, $data);
            }
        );
    }

    private function getEntityReplacer($additionalAllowedChar = null, $inAttribute = false)
    {
        return function ($read, &$data) use ($additionalAllowedChar, $inAttribute) {
            $data .= (yield from $this->entityReplacementTable->consumeCharRef($this->stream, $additionalAllowedChar, $inAttribute));
        };
    }

    private function getNullReplacer()
    {
        return function($read, &$data)
        {
            yield from $this->parseError(ParseErrors::getUnexpectedNullCharacter($this->stream->getLineAndColumn()));
            $data .= self::$FFFDReplacementCharacter;
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
                yield from $this->emit(new HtmlCharToken($data));
            }
        };

        $actions = array_map(function($v) use ($andEmit) {
            return function($read, &$data, &$consume, &$continue) use ($v, $andEmit) {
                $continue = false;
                $this->setState($v[0]);
                if (isset($v[1]) && $v[1]) {
                    $data .= $read;
                }
                yield from $andEmit($read, $data);
            };
        }, $states);
        $actions["\0"] = $this->getNullReplacer();

        yield from $this->consume(
                $actions,
                function($read, &$data) use (&$eof, $andEmit) {
                    $eof = true;
                    yield from $andEmit(null, $data);
                }
            );
    }

    private function consume(array $actions, callable $onEof) {
        $data = "";
        $eof = false;
        while (true) {
            $data .= (yield from $this->stream->consumeUntil(array_keys($actions), $eof));
            $read = (yield from $this->stream->read());
            if (isset($actions[$read])) {
                $consume = true;
                $continue = true;
                $acted = $actions[$read]($read, $data, $consume, $continue);
                yield from $this->yieldOrFail($acted);
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
            (yield from $this->stream->read());
            yield from $this->yieldOrFail($onEof(null, $data, $noop, $noop));
        }
    }

    private function yieldOrFail($acted) {
        if ($acted instanceof \Generator) {
            yield from $acted;
        } elseif ($acted != null) {
            throw new \Exception("Expected generator or null, got " . $acted);
        } else {
            yield from [];
        }
    }

    private $lastStartTagName = null;
    private $comment = "";

    /**
     * @return \Generator
     * @throws \Exception
     */
    public function parse()
    {
        $done = false;
        while (!$done) {
            if ($this->logger) {
                $this->logger->debug("In state " . State::toName($this->getState()));
            }
            switch ($this->getState()) {
                case State::$STATE_DATA:
                    yield from $this->consumeDataWithEntityReplacement(State::$STATE_TAG_OPEN, false, $done);
                    break;
                case State::$STATE_RCDATA:
                    yield from $this->consumeDataWithEntityReplacement(State::$STATE_RCDATA_LT_SIGN, true, $done);
                    break;
                case State::$STATE_RAWTEXT:
                    yield from $this->consumeDataNoEntityReplacement(["<" => [State::$STATE_RAWTEXT_LT_SIGN]], $done);
                    break;
                case State::$STATE_SCRIPT_DATA:
                    yield from $this->consumeDataNoEntityReplacement(["<" => [State::$STATE_SCRIPT_DATA_LT_SIGN]], $done);
                    break;
                case State::$STATE_PLAINTEXT:
                    yield from $this->consumeDataNoEntityReplacement([], $done);
                    break;
                case State::$STATE_TAG_OPEN:
                    $next = (yield from $this->stream->read());
                    switch ($next) {
                        case "!":
                            $this->setState(State::$STATE_MARKUP_DECLARATION_OPEN);
                            break;
                        case "/":
                            $this->setState(State::$STATE_END_TAG_OPEN);
                            break;
                        case "?":
                            yield from $this->parseError(ParseErrors::getUnexpectedQuestionMarkInsteadOfTagName($this->stream->getLineAndColumn()));
                            $this->stream->unconsume();
                            $this->comment = "";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                            break;
                        default:
                            if (preg_match("/[a-zA-Z]/u", $next)) {
                                $this->currentTokenBuilder = HtmlStartTagToken::builder($this->logger);
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_TAG_NAME);
                            } else {
                                if ($next === null) {
                                    yield from $this->parseError(ParseErrors::getEofBeforeTagName($this->stream->getLineAndColumn()));
                                    yield from $this->emit(new HtmlCharToken("<"));
                                    $done = true;
                                } else {
                                    yield from $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName($this->stream->getLineAndColumn()));
                                    yield from $this->emit(new HtmlCharToken("<"));
                                    $this->stream->unconsume();
                                    $this->setState(State::$STATE_DATA);
                                }

                            }
                    }
                    break;
                case State::$STATE_END_TAG_OPEN:
                    $next = (yield from $this->stream->read());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder($this->logger);
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_TAG_NAME);
                    } elseif ($next == ">") {
                        yield from $this->parseError(ParseErrors::getMissingEndTagName($this->stream->getLineAndColumn()));
                        $this->setState(State::$STATE_DATA);
                    } elseif ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofBeforeTagName($this->stream->getLineAndColumn()));
                        yield from $this->emit(new HtmlCharToken("</"));
                        $done = true;
                    } else {
                        yield from $this->parseError(ParseErrors::getInvalidFirstCharacterOfTagName($this->stream->getLineAndColumn()));
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
                    };
                    $actions = [
                        "\t" => $beforeANameSwitcher,
                        "\n" => $beforeANameSwitcher,
                        "\f" => $beforeANameSwitcher,
                        " " => $beforeANameSwitcher,
                        "/" => $this->getBasicStateSwitcher(State::$STATE_SELF_CLOSING_START_TAG, $setTagName),
                        ">" => function($read, &$data, &$consume, &$continue) use (&$tokens) {
                            $continue = false;
                            $this->setState(State::$STATE_DATA);
                            $this->currentTokenBuilder->setName($data);
                            yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                        },
                        "\0" => $this->getNullReplacer(),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    yield from $this->consume(
                        $actions,
                        function ($read, $data) use (&$errors, &$done) {
                            $this->currentTokenBuilder->setName($data);
                            yield from $this->parseError(ParseErrors::getEofInTag($this->stream->getLineAndColumn()));
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_RCDATA_LT_SIGN:
                    $next = (yield from $this->stream->read());
                    if ($next === "/") {
                        $this->setState(State::$STATE_RCDATA_END_TAG_OPEN);
                    } else {
                        yield from $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RCDATA);
                    }
                    break;
                case State::$STATE_RCDATA_END_TAG_OPEN:
                    $next = (yield from $this->stream->read());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder($this->logger);
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RCDATA_END_TAG_NAME);
                    } else {
                        yield from $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RCDATA);
                    }
                    break;
                case State::$STATE_RCDATA_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_RCDATA);
                    } else {
                        switch ((yield from $this->stream->read())) {
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
                                yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_RCDATA);
                        }
                    }
                    break;
                case State::$STATE_RAWTEXT_LT_SIGN:
                    $next = (yield from $this->stream->read());
                    if ($next === "/") {
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_OPEN);
                    } else {
                        yield from $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RAWTEXT);
                    }
                    break;
                case State::$STATE_RAWTEXT_END_TAG_OPEN:
                    $next = (yield from $this->stream->read());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder($this->logger);
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RAWTEXT_END_TAG_NAME);
                    } else {
                        yield from $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_RAWTEXT);
                    }
                    break;
                case State::$STATE_RAWTEXT_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_RAWTEXT);
                    } else {
                        switch ((yield from $this->stream->read())) {
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
                                yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_RAWTEXT);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_LT_SIGN:
                    $next = (yield from $this->stream->read());
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_OPEN);
                    } elseif ($next === "!") {
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START);
                        yield from $this->emit(new HtmlCharToken("<!"));
                    } else {
                        yield from $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_END_TAG_OPEN:
                    $next = (yield from $this->stream->read());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder($this->logger);
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_END_TAG_NAME);
                    } else {
                        yield from $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    } else {
                        switch ((yield from $this->stream->read())) {
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
                                yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_SCRIPT_DATA);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START:
                    if ((yield from $this->stream->read()) == "-") {
                        yield from $this->emit(new HtmlCharToken("-"));
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPE_START_DASH:
                    if ((yield from $this->stream->read()) == "-") {
                        yield from $this->emit(new HtmlCharToken("-"));
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED:
                    yield from $this->consumeDataNoEntityReplacement(
                                [
                                    "<" => [State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN],
                                    "-" => [State::$STATE_SCRIPT_DATA_ESCAPED_DASH, true],
                                ],
                                $eof
                    );
                    if ($eof) {
                        yield from $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText($this->stream->getLineAndColumn()));
                        $done = true;
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH:
                    switch ($read = (yield from $this->stream->read())) {
                        case "-":
                            yield from $this->emit(new HtmlCharToken("-"));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH);
                            break;
                        case "<":
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case "\0":
                            yield from $this->parseError(ParseErrors::getUnexpectedNullCharacter($this->stream->getLineAndColumn()));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            yield from $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                            break;
                        case null:
                            yield from $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText($this->stream->getLineAndColumn()));
                            $done = true;
                            break;
                        default:
                            yield from $this->emit(new HtmlCharToken($read));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_DASH_DASH:
                    switch ($read = (yield from $this->stream->read())) {
                        case "-":
                            yield from $this->emit(new HtmlCharToken("-"));
                            break;
                        case "<":
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN);
                            break;
                        case ">":
                            yield from $this->emit(new HtmlCharToken(">"));
                            $this->setState(State::$STATE_SCRIPT_DATA);
                            break;
                        case "\0":
                            yield from $this->parseError(ParseErrors::getUnexpectedNullCharacter($this->stream->getLineAndColumn()));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                            yield from $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                            break;
                        case null:
                            yield from $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText($this->stream->getLineAndColumn()));
                            $done = true;
                            break;
                        default:
                            yield from $this->emit(new HtmlCharToken($read));
                            $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_LT_SIGN:
                    $next = (yield from $this->stream->read());
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN);
                    } else if (preg_match("/[a-zA-Z]/u", $next)) {
                        yield from $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START);
                    } else {
                        yield from $this->emit(new HtmlCharToken("<"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_OPEN:
                    $next = (yield from $this->stream->read());
                    if (preg_match("/[a-zA-Z]/u", $next)) {
                        $this->currentTokenBuilder = HtmlEndTagToken::builder($this->logger);
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME);
                    } else {
                        yield from $this->emit(new HtmlCharToken("</"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_ESCAPED_END_TAG_NAME:
                    $tempBuffer = $this->stream->readAlpha();
                    $name = mb_convert_case($tempBuffer, MB_CASE_LOWER);
                    if (!$this->lastStartTagName || $name != $this->lastStartTagName) {
                        yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                        $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                    } else {
                        switch ((yield from $this->stream->read())) {
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
                                yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->emit(new HtmlCharToken("</" . $tempBuffer));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_SCRIPT_DATA_ESCAPED);
                        }
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_START:
                    $tempBuffer = $this->stream->readAlpha();
                    switch ($read = (yield from $this->stream->read())) {
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
                    yield from $this->emit(new HtmlCharToken($tempBuffer));
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED:
                    yield from $this->consumeDataNoEntityReplacement(
                        [
                            "-" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH, true],
                            "<" => [State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN, true],
                        ],
                        $eof
                    );
                    if ($eof) {
                        yield from $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText($this->stream->getLineAndColumn()));
                        $done = true;
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED_DASH:
                    $char = (yield from $this->stream->read());
                    if ($char == null) {
                        yield from $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText($this->stream->getLineAndColumn()));
                        $done = true;
                    } else {
                        if ($char == "\0") {
                            yield from $this->parseError(ParseErrors::getUnexpectedNullCharacter($this->stream->getLineAndColumn()));
                            yield from $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                        } else {
                            yield from $this->emit(new HtmlCharToken($char));
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
                    $char = (yield from $this->stream->read());
                    if ($char == null) {
                        yield from $this->parseError(ParseErrors::getEofInScriptHtmlCommentLikeText($this->stream->getLineAndColumn()));
                        $done = true;
                    } else {
                        if ($char == "\0") {
                            yield from $this->parseError(ParseErrors::getUnexpectedNullCharacter($this->stream->getLineAndColumn()));
                            yield from $this->emit(new HtmlCharToken(self::$FFFDReplacementCharacter));
                        } else {
                            yield from $this->emit(new HtmlCharToken($char));
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
                    $next = (yield from $this->stream->read());
                    if ($next === "/") {
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END);
                        yield from $this->emit(new HtmlCharToken("/"));
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPED);
                    }
                    break;
                case State::$STATE_SCRIPT_DATA_DOUBLE_ESCAPE_END:
                    $tempBuffer = $this->stream->readAlpha();
                    switch ($read = (yield from $this->stream->read())) {
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
                    yield from $this->emit(new HtmlCharToken($tempBuffer));
                    break;
                case State::$STATE_BEFORE_ATTRIBUTE_NAME:
                    $next = (yield from $this->stream->read());
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
                                yield from $this->parseError(ParseErrors::getUnexpectedEqualsSignBeforeAttributeName($this->stream->getLineAndColumn()));
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
                        yield from $this->finishAttributeNameOrParseError($data);
                    };
                    $afterANameSwitcher = $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_NAME, $addAttributeName, false);
                    $toLowerCase = function($read, &$data) {
                        $data .= strtolower($read);
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
                        "\"" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInAttributeName),
                        "'" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInAttributeName),
                        "<" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInAttributeName),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    yield from $this->consume(
                        $actions,
                        $afterANameSwitcher
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_NAME:
                    $this->stream->discardWhitespace();
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofInTag($this->stream->getLineAndColumn()));
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
                                yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
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
                    switch ($read = (yield from $this->stream->read())) {
                        case "\"":
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED);
                            break;
                        case "'":
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED);
                            break;
                        case ">":
                            yield from $this->parseError(ParseErrors::getMissingAttributeValue($this->stream->getLineAndColumn()));
                            $this->setState(State::$STATE_DATA);
                            yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                            break;
                        default:
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_ATTRIBUTE_VALUE_UNQUOTED);
                    }
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_DOUBLE_QUOTED:
                    yield from $this->consume(
                        $actions = [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED, function($read, &$data) {
                                $this->finishAttributeValueOrDiscard($data);
                            }),
                            "&" => $this->getEntityReplacer(null, true),
                            "\0" => $this->getNullReplacer(),
                        ],
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            yield from $this->parseError(ParseErrors::getEofInTag($this->stream->getLineAndColumn()));
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_SINGLE_QUOTED:
                    yield from $this->consume(
                        $actions = [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED, function($read, &$data) {
                                $this->finishAttributeValueOrDiscard($data);
                            }),
                            "&" => $this->getEntityReplacer(null, true),
                            "\0" => $this->getNullReplacer(),
                        ],
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            yield from $this->parseError(ParseErrors::getEofInTag($this->stream->getLineAndColumn()));
                            $done = true;
                        }
                        );
                    break;
                case State::$STATE_ATTRIBUTE_VALUE_UNQUOTED:
                    $switchBeforeAttrName = $this->getBasicStateSwitcher(State::$STATE_BEFORE_ATTRIBUTE_NAME, function($read, &$data) {
                        $this->finishAttributeValueOrDiscard($data);
                    }, false);
                    yield from $this->consume(
                        $actions = [
                            "\t" => $switchBeforeAttrName,
                            "\n" => $switchBeforeAttrName,
                            "\f" => $switchBeforeAttrName,
                            " " => $switchBeforeAttrName,
                            "&" => $this->getEntityReplacer( ">", true),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA,
                                function($read, &$data) use (&$tokens, &$errors) {
                                    $this->finishAttributeValueOrDiscard($data);
                                    yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                                }
                            ),
                            "\0" => $this->getNullReplacer(),
                            "\"" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInUnquotedAttributeValue),
                            "'" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInUnquotedAttributeValue),
                            "<" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInUnquotedAttributeValue),
                            "=" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInUnquotedAttributeValue),
                            "`" => $this->getParseErrorAndContinue(ParseErrors::getInstance()->unexpectedCharacterInUnquotedAttributeValue),
                        ],
                        function ($read, $data) use (&$errors, &$done) {
                            $this->finishAttributeValueOrDiscard($data);
                            yield from $this->parseError(ParseErrors::getEofInTag($this->stream->getLineAndColumn()));
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_AFTER_ATTRIBUTE_VALUE_QUOTED:
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofInTag($this->stream->getLineAndColumn()));
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
                                yield from $this->emit($this->currentTokenBuilder->build($this->errorQueue, $this->stream->getLineAndColumn()));
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->parseError(ParseErrors::getMissingWhitespaceBetweenAttributes($this->stream->getLineAndColumn()));
                                $this->stream->unconsume();
                                $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_SELF_CLOSING_START_TAG:
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofInTag($this->stream->getLineAndColumn()));
                        $done = true;
                    } else {
                        if ($next == ">") {
                            yield from $this->emit($this->currentTokenBuilder->isSelfClosing(true)->build($this->errorQueue, $this->stream->getLineAndColumn()));
                            $this->setState(State::$STATE_DATA);
                        } else {
                            yield from $this->parseError(ParseErrors::getUnexpectedSolidusInTag($this->stream->getLineAndColumn()));
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_BEFORE_ATTRIBUTE_NAME);
                        }
                    }
                    break;
                case State::$STATE_BOGUS_COMMENT:
                    $switchAndEmit = $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens) {
                        yield from $this->emit(new HtmlCommentToken($this->comment . $data));
                    });
                    yield from $this->consume(
                        [
                            ">" => $switchAndEmit,
                            "\0" => function($read, &$data) { $data .= self::$FFFDReplacementCharacter; }
                        ],
                        function ($read, $data) use (&$done) {
                            yield from $this->emit(new HtmlCommentToken($this->comment . $data));
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_MARKUP_DECLARATION_OPEN:
                    if ($this->stream->peek(2) == "--") {
                        (yield from $this->stream->read(2));
                        $this->comment = "";
                        $this->setState(State::$STATE_COMMENT_START);
                    } else {
                        $peeked = $this->stream->peek(7);
                        if (strtoupper($peeked) == "DOCTYPE") {
                            (yield from $this->stream->read(7));
                            $this->setState(State::$STATE_DOCTYPE);
                        } elseif ($peeked == "[CDATA[") {// TODO check stack!
                            (yield from $this->stream->read(7));
                            //$this->setState(State::$STATE_CDATA_SECTION);
                            yield from $this->parseError(ParseErrors::getCdataInHtmlContent($this->stream->getLineAndColumn()));
                            $this->comment = "[CDATA[";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        } else {
                            (yield from $this->stream->read());
                            yield from $this->parseError(ParseErrors::getIncorrectlyOpenedComment($this->stream->getLineAndColumn()));
                            $this->stream->unconsume();
                            $this->comment = "";
                            $this->setState(State::$STATE_BOGUS_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_COMMENT_START:
                    switch ((yield from $this->stream->read())) {
                        case "-":
                            $this->setState(State::$STATE_COMMENT_START_DASH);
                            break;
                        case ">":
                            yield from $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment($this->stream->getLineAndColumn()));
                            yield from $this->emit(new HtmlCommentToken($this->comment));
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                    }
                    break;
                case State::$STATE_COMMENT_START_DASH:
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofInComment($this->stream->getLineAndColumn()));
                        yield from $this->emit(new HtmlCommentToken($this->comment));
                        $done = true;
                    } else {
                        switch ($next) {
                            case "-":
                                $this->setState(State::$STATE_COMMENT_END);
                                break;
                            case ">":
                                yield from $this->parseError(ParseErrors::getAbruptClosingOfEmptyComment($this->stream->getLineAndColumn()));
                                yield from $this->emit(new HtmlCommentToken($this->comment));
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
                    yield from $this->consume(
                        [
                            "<" => $this->getBasicStateSwitcher(State::$STATE_COMMENT_LT_SIGN, function($read, &$data) { $this->comment .= $data . $read; }),
                            "-" => $this->getBasicStateSwitcher(State::$STATE_COMMENT_END_DASH, function($read, &$data) { $this->comment .= $data; }),
                            "\0" => $this->getNullReplacer()
                        ],
                        function ($read, &$data) use (&$tokens, &$errors, &$done) {
                            $this->comment .= $data;
                            yield from $this->parseError(ParseErrors::getEofInComment($this->stream->getLineAndColumn()));
                            yield from $this->emit(new HtmlCommentToken($this->comment));
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_COMMENT_LT_SIGN:
                    switch ((yield from $this->stream->read())) {
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
                    if ((yield from $this->stream->read()) == "-") {
                        $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT);
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG_DASH:
                    if ((yield from $this->stream->read()) == "-") {
                        $this->setState(State::$STATE_COMMENT_LT_SIGN_BANG_DASH_DASH);
                    } else {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT_END_DASH);
                    }
                    break;
                case State::$STATE_COMMENT_LT_SIGN_BANG_DASH_DASH:
                    $read = (yield from $this->stream->read());
                    if ($read == null || $read == "-") {
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT_END);
                    } else {
                        yield from $this->parseError(ParseErrors::getNestedComment($this->stream->getLineAndColumn()));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_COMMENT_END);
                    }
                    break;
                case State::$STATE_COMMENT_END_DASH:
                    $read = (yield from $this->stream->read());
                    if ($read === null) {
                        yield from $this->parseError(ParseErrors::getEofInComment($this->stream->getLineAndColumn()));
                        yield from $this->emit(new HtmlCommentToken($this->comment));
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
                    $read = (yield from $this->stream->read());
                    if ($read === null) {
                        yield from $this->parseError(ParseErrors::getEofInComment($this->stream->getLineAndColumn()));
                        yield from $this->emit(new HtmlCommentToken($this->comment));
                        $done = true;
                    } else {
                        if ($read == ">") {
                            yield from $this->emit(new HtmlCommentToken($this->comment));
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
                    $read = (yield from $this->stream->read());
                    if ($read === null) {
                        yield from $this->parseError(ParseErrors::getEofInComment($this->stream->getLineAndColumn()));
                        yield from $this->emit(new HtmlCommentToken($this->comment));
                        $done = true;
                    } else {
                        if ($read == "-") {
                            $this->comment .= "--!";
                            $this->setState(State::$STATE_COMMENT_END_DASH);
                        } elseif ($read == ">") {
                            yield from $this->parseError(ParseErrors::getIncorrectlyClosedComment($this->stream->getLineAndColumn()));
                            yield from $this->emit(new HtmlCommentToken($this->comment));
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $this->comment .= "--!";
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_COMMENT);
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE:
                    switch ((yield from $this->stream->read())) {
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
                            yield from $this->emit(HtmlDocTypeToken::builder($this->logger)->isForceQuirks(true)->build());
                            yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                            $done = true;
                            break;
                        default:
                            yield from $this->parseError(ParseErrors::getMissingWhitespaceBeforeDoctypeName($this->stream->getLineAndColumn()));
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_BEFORE_DOCTYPE_NAME);
                            break;
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_NAME:
                    $this->stream->discardWhitespace();
                    $this->currentDoctypeBuilder = HtmlDocTypeToken::builder($this->logger);
                    switch ((yield from $this->stream->read())) {
                        case ">":
                            yield from $this->parseError(ParseErrors::getMissingDoctypeName($this->stream->getLineAndColumn()));
                            yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                            $this->setState(State::$STATE_DATA);
                            break;
                        case null:
                            yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                            yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                    };
                    $actions = [
                        "\t" => $afterDTNameSwitcher,
                        "\n" => $afterDTNameSwitcher,
                        "\f" => $afterDTNameSwitcher,
                        " " => $afterDTNameSwitcher,
                        ">" => function($read, &$data, &$consume, &$continue) use (&$tokens) {
                        $continue = false;
                            $this->currentDoctypeBuilder->setName($data);
                            yield from $this->emit($this->currentDoctypeBuilder->build());
                            $this->setState(State::$STATE_DATA);
                        },
                        "\0" => $this->getNullReplacer(),
                    ];
                    for ($i = "A"; $i <= "Z"; $i++) {
                        $actions[$i] = $toLowerCase;
                    }
                    yield from $this->consume(
                        $actions,
                        function ($read, $data) use (&$tokens, &$errors, &$done) {
                            yield from $this->emit($this->currentDoctypeBuilder->setName($data)->isForceQuirks(true)->build());
                            yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_NAME:
                    $this->stream->discardWhitespace();
                    $first = (yield from $this->stream->read());
                    if ($first == null) {
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        $done = true;
                    } else {
                        if ($first == ">") {
                            yield from $this->emit($this->currentDoctypeBuilder->build());
                            $this->setState(State::$STATE_DATA);
                        } else {
                            $potentialKeyword = strtoupper($first . $this->stream->peek(5));
                            if ($potentialKeyword == "PUBLIC") {
                                (yield from $this->stream->read(5));
                                $this->setState(State::$STATE_AFTER_DOCTYPE_PUBLIC_KEYWORD);
                            } elseif ($potentialKeyword == "SYSTEM") {
                                (yield from $this->stream->read(5));
                                $this->setState(State::$STATE_AFTER_DOCTYPE_SYSTEM_KEYWORD);
                            } else {
                                yield from $this->parseError(ParseErrors::getInvalidCharacterSequenceAfterDoctypeName($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                            }
                        }
                    }
                    break;
                case State::$STATE_AFTER_DOCTYPE_PUBLIC_KEYWORD:
                    $read = (yield from $this->stream->read());
                    if ($read == null) {
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                yield from $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypePublicKeyword($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                yield from $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypePublicKeyword($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->setPublicIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                yield from $this->parseError(ParseErrors::getMissingDoctypePublicIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypePublicIdentifier($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_PUBLIC_IDENTIFIER:
                    $this->stream->discardWhitespace();
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
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
                                yield from $this->parseError(ParseErrors::getMissingDoctypePublicIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypePublicIdentifier($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED:
                    yield from $this->consume(
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                yield from $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                            yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            $done = true;
                        },
                        $errors
                    );
                    break;
                case State::$STATE_DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED:
                    yield from $this->consume(
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setPublicIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                yield from $this->parseError(ParseErrors::getAbruptDoctypePublicIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            })                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                            yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setPublicIdentifier($data)->build());
                            $done = true;
                        }

                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_PUBLIC_IDENTIFIER:
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                yield from $this->emit($this->currentDoctypeBuilder->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            case "\"":
                                yield from $this->parseError(ParseErrors::getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                yield from $this->parseError(ParseErrors::getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            default:
                                yield from $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BETWEEN_DOCTYPE_PUBLIC_AND_SYSTEM_IDENTIFIERS:
                    $this->stream->discardWhitespace();
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                        $done = true;
                    } else {
                        switch ($next) {
                            case ">":
                                yield from $this->emit($this->currentDoctypeBuilder->build());
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
                                yield from $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_AFTER_DOCTYPE_SYSTEM_KEYWORD:
                    $read = (yield from $this->stream->read());
                    if ($read == null) {
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                yield from $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypeSystemKeyword($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED);
                                break;
                            case "'":
                                yield from $this->parseError(ParseErrors::getMissingWhitespaceAfterDoctypeSystemKeyword($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->setSystemIdentifier("");
                                $this->setState(State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED);
                                break;
                            case ">":
                                yield from $this->parseError(ParseErrors::getMissingDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_BEFORE_DOCTYPE_SYSTEM_IDENTIFIER:
                    $this->stream->discardWhitespace();
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
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
                                yield from $this->parseError(ParseErrors::getMissingDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                                $this->setState(State::$STATE_DATA);
                                break;
                            default:
                                yield from $this->parseError(ParseErrors::getMissingQuoteBeforeDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                $this->currentDoctypeBuilder->isForceQuirks(true);
                                $this->setState(State::$STATE_BOGUS_DOCTYPE);
                                break;
                        }
                    }
                    break;
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED:
                    yield from $this->consume(
                        [
                            "\"" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                yield from $this->parseError(ParseErrors::getAbruptDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                            yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED:
                    yield from $this->consume(
                        [
                            "'" => $this->getBasicStateSwitcher(State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER, function($read, &$data) { $this->currentDoctypeBuilder->setSystemIdentifier($data); }),
                            "\0" => $this->getNullReplacer(),
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens, &$errors) {
                                yield from $this->parseError(ParseErrors::getAbruptDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                                yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            })
                        ],
                        function($read, &$data) use (&$tokens, &$errors, &$done) {
                            yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                            yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->setSystemIdentifier($data)->build());
                            $done = true;
                        }
                    );
                    break;
                case State::$STATE_AFTER_DOCTYPE_SYSTEM_IDENTIFIER:
                    $this->stream->discardWhitespace();
                    $next = (yield from $this->stream->read());
                    if ($next == null) {
                        yield from $this->parseError(ParseErrors::getEofInDoctype($this->stream->getLineAndColumn()));
                        yield from $this->emit($this->currentDoctypeBuilder->isForceQuirks(true)->build());
                        $done = true;
                    } else {
                        if ($next == ">") {
                            yield from $this->emit($this->currentDoctypeBuilder->build());
                            $this->setState(State::$STATE_DATA);
                        } else {
                            yield from $this->parseError(ParseErrors::getUnexpectedCharacterAfterDoctypeSystemIdentifier($this->stream->getLineAndColumn()));
                            $this->setState(State::$STATE_BOGUS_DOCTYPE);
                            break;
                        }
                    }
                    break;
                case State::$STATE_BOGUS_DOCTYPE:
                    yield from $this->consume(
                        [
                            ">" => $this->getBasicStateSwitcher(State::$STATE_DATA, function($read, &$data) use (&$tokens) {
                                yield from $this->emit($this->currentDoctypeBuilder->build());
                            }),
                        ],
                        function($read, &$data) use (&$done, &$tokens) {
                            yield from $this->emit($this->currentDoctypeBuilder->build());
                            $done = true;
                        }
                        );
                    break;
                case State::$STATE_CDATA_SECTION:
                    $consumed = (yield from $this->stream->consumeUntil("]", $eof));
                    if ($consumed != "") {
                        yield from $this->emit(new HtmlCharToken($consumed));
                    }
                    if ($eof) {
                        yield from $this->parseError(ParseErrors::getEofInCdata($this->stream->getLineAndColumn()));
                        $done = true;
                    } else {
                        $this->setState(State::$STATE_CDATA_SECTION_BRACKET);
                    }
                    break;
                case State::$STATE_CDATA_SECTION_BRACKET:
                    if ((yield from $this->stream->read()) == "]") {
                        $this->setState(State::$STATE_CDATA_SECTION_END);
                    } else {
                        yield from $this->emit(new HtmlCharToken("]"));
                        $this->stream->unconsume();
                        $this->setState(State::$STATE_CDATA_SECTION);
                    }
                    break;
                case State::$STATE_CDATA_SECTION_END:
                    switch ((yield from $this->stream->read())) {
                        case "]":
                            yield from $this->emit(new HtmlCharToken("]"));
                            break;
                        case ">":
                            $this->setState(State::$STATE_DATA);
                            break;
                        default:
                            yield from $this->emit(new HtmlCharToken("]]"));
                            $this->stream->unconsume();
                            $this->setState(State::$STATE_CDATA_SECTION);
                    }
                    break;
                default:
                    throw new \Exception("TODO: Parse error invalid state: " . $this->getState());
            }
        }
        yield from $this->errorQueue;
    }

    private function finishAttributeNameOrParseError($name) {
        try {
            $this->currentTokenBuilder->finishAttributeName($name);
        } catch (\Exception $e) {
            // TODO more specific excpetion
            yield from $this->parseError(ParseErrors::getDuplicateAttribute($this->stream->getLineAndColumn()));
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


<?php
namespace Woaf\HtmlTokenizer\Html5Lib;

use FilesystemIterator;
use GlobIterator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlTokenizer;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCommentToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;
use Woaf\HtmlTokenizer\ParseError;

/**
 * @backupGlobals disabled
 */
class Html5LibTest extends TestCase
{

    private static $HTMLLIB5TESTS = __DIR__ . '/../../../../../html5lib-tests/';

    public static function provide() {
        $path = getenv('HTMLLIB5TESTS');
        if ($path === false) {
            $path = self::$HTMLLIB5TESTS;
        }
        $path .= '/tokenizer';
        $iterator = new GlobIterator($path . '/*.test', FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::CURRENT_AS_PATHNAME);

        if (!$iterator->count()) {
            return null;
        } else {
            $tests = [];
            foreach ($iterator as $fileName => $filePath) {
                $fileTests = json_decode(file_get_contents($filePath));
                if (!isset($fileTests->tests)) {
                    $tests[$fileName] = [$fileName, null, null];
                } else {
                    foreach ($fileTests->tests as $test) {
                        $initialStates = ["Data state"];
                        if (isset($test->initialStates)) {
                            $initialStates = $test->initialStates;
                        }
                        foreach ($initialStates as $initialState) {
                            $tests[$fileName . ' : ' . $test->description . '. Testing ' . $initialState] = [$fileName, $initialState, $test];
                        }
                    }
                }
            }
            return $tests;
        }

    }

    private static function unescape($victim) {
        return preg_replace_callback('/\\\u([a-zA-Z0-9]{4})/', function($matches) {
            return mb_decode_numericentity("&#x" . $matches[1] . ";", [ 0x0, 0xffff, 0, 0xffff ]);
        }, $victim);
    }
    /**
     * @dataProvider provide
     */
    public function testTest($filename, $initialState, $test) {
        if ($test === null) {
            $this->markTestSkipped("$filename contained no tests");
            return;
        }

        // , last tag state
        $tokenizer = new HtmlTokenizer(new Logger("html5libtest", [new StreamHandler(STDOUT)]));
        $lastStartTagName = null;
        if (isset($test->lastStartTag)) {
            $lastStartTagName = $test->lastStartTag;
        }
        $doubleEscaped = isset($test->doubleEscaped) && $test->doubleEscaped;
        $tokenizer->pushState($this->convertState($initialState), $lastStartTagName);
        $result = $tokenizer->parseText($doubleEscaped ? self::unescape($test->input) : $test->input);

        $this->assertEquals(array_map(function($a) use ($doubleEscaped) { return $this->convertToken($a, $doubleEscaped); }, $test->output), $result->getTokens(), "Tokens failed to match expected");
        $this->assertEquals(array_map(array($this, 'convertError'), isset($test->errors) ? $test->errors : []), $result->getErrors(), "Errors failed to match expected");
    }

    private function convertError($error) {
        // TODO code, line, col
        switch ($error->code) {
            case "eof-in-tag":
                return new ParseError();
            case "control-character-reference":
                return new ParseError();
            case "missing-semicolon-after-character-reference":
                return new ParseError();
            case "eof-in-script-html-comment-like-text":
                return new ParseError();
            case "abrupt-closing-of-empty-comment":
                return new ParseError();
            case "unexpected-question-mark-instead-of-tag-name":
                return new ParseError();
            case "unknown-named-character-reference":
                return new ParseError();
            case "unexpected-null-character":
                return new ParseError();
            case "noncharacter-character-reference":
                return new ParseError();
            case "eof-in-comment":
                return new ParseError();
            case "unexpected-character-in-attribute-name":
                return new ParseError();
            case "invalid-first-character-of-tag-name":
                return new ParseError();
            case "incorrectly-opened-comment":
                return new ParseError();
            case "control-character-in-input-stream":
                return new ParseError();
            case "unexpected-solidus-in-tag":
                return new ParseError();
            case "unexpected-character-in-unquoted-attribute-value":
                return new ParseError();
            case "noncharacter-in-input-stream":
                return new ParseError();
            default:
                throw new \Exception("Unknown error type {$error->code}");
        }
    }

    private function convertState($stateName) {
        $parts = explode(" ", $stateName);
        if (array_pop($parts) != "state") {
            throw new \Exception("Unable to convert state $stateName to our states");
        }
        $name = strtoupper("STATE_" . implode("_", $parts));
        if (!property_exists('\Woaf\HtmlTokenizer\HtmlTokenizer', $name)) {
            throw new \Exception("Unable to convert state $stateName to our states");
        }
        return HtmlTokenizer::$$name;
    }

    private function convertToken(array $arrtok, $doubleEscaped) {
        switch ($arrtok[0]) {
            case "Character":
                return new HtmlCharToken($doubleEscaped ? self::unescape($arrtok[1]) : $arrtok[1]);
            case "Comment":
                return new HtmlCommentToken($doubleEscaped ? self::unescape($arrtok[1]) : $arrtok[1]);
            case "EndTag":
                return new HtmlEndTagToken($arrtok[1], false, []);
            case "StartTag":
                return new HtmlStartTagToken($arrtok[1], isset($arrtok[3]) ? $arrtok[3] : false, (array)$arrtok[2]);
            default:
                throw new \Exception("Unknown token type {$arrtok[0]}");
        }
    }

}


<?php
namespace Woaf\HtmlTokenizer\Html5Lib;

use FilesystemIterator;
use GlobIterator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlTokenizer;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCommentToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;
use Woaf\HtmlTokenizer\Tables\ParseErrors;
use Woaf\HtmlTokenizer\Tables\State;

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
            return mb_decode_numericentity("&#x" . $matches[1] . ";", [ 0x0, 0x10ffff, 0, 0x10ffff ]);
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
        $tokenizer = new HtmlTokenizer(new Logger("html5libtest", [new StreamHandler(STDOUT)], [new IntrospectionProcessor()]));
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
//        return ParseErrors::forCode($error->code, $error->line, $error->col);
        return ParseErrors::forCode($error->code, 0, 0);
    }

    private function convertState($stateName) {
        $parts = explode(" ", $stateName);
        if (array_pop($parts) != "state") {
            throw new \Exception("Unable to convert state $stateName to our states");
        }
        $name = strtoupper("STATE_" . implode("_", $parts));
        if (!property_exists('\Woaf\HtmlTokenizer\Tables\State', $name)) {
            throw new \Exception("Unable to convert state $stateName to our states");
        }
        return State::$$name;
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
            case "DOCTYPE":
                return new HtmlDocTypeToken($arrtok[1], $arrtok[2], $arrtok[3], !$arrtok[4]); // $arrtok[4] is  "correctness", which is !quirks-mode.
            default:
                throw new \Exception("Unknown token type {$arrtok[0]}");
        }
    }

}


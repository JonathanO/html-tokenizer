<?php
namespace Woaf\HtmlTokenizer\Html5Lib;

use FilesystemIterator;
use GlobIterator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlParseError;
use Woaf\HtmlTokenizer\HtmlTokenizer;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCommentToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEofToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;
use Woaf\HtmlTokenizer\Tables\ParseErrors;
use Woaf\HtmlTokenizer\Tables\State;
use Woaf\HtmlTokenizer\TokenStreamingTokenizer;

/**
 * @backupGlobals disabled
 * @group html5lib
 */
class Html5LibTest extends TestCase
{

    private static $HTMLLIB5TESTS = __DIR__ . '/../../../../../html5lib-tests/';

    protected static function getPathToTests() {
        $path = getenv('HTMLLIB5TESTS');
        if ($path === false) {
            $path = self::$HTMLLIB5TESTS;
        }
        return $path . '/tokenizer';
    }

    public static function provide() {
        $path = self::getPathToTests();
        if (!is_dir($path)) {
            // GlobIterator bug in PHP 7.0 means we need to bail out early if there are no files.
            return [[null, null, null]];
        }
        $iterator = new GlobIterator($path . '/*.test', FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::CURRENT_AS_PATHNAME);

        if (!$iterator->count()) {
            return [[null, null, null]];
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

    protected function setUp() {
        $testSource = self::getPathToTests();
        if (!is_dir($testSource)) {
            $this->markTestSkipped("Did not find html5lib tests in expected location: $testSource");
        }
    }

    /**
     * @dataProvider provide
     */
    public function testTest($filename, $initialState, $test) {
        if ($filename === null) {
            $this->markTestSkipped("No test files found in " . self::getPathToTests());
            return;
        }
        if ($test === null) {
            $this->markTestSkipped("$filename contained no tests");
            return;
        }

        $logLevel = getenv("LOGLEVEL");
        if ($logLevel === false) {
            $logLevel = "DEBUG";
        }

        $tokenizer = new TokenStreamingTokenizer(new Logger("html5libtest", [new StreamHandler(STDOUT, constant("Monolog\Logger::$logLevel"))], [new IntrospectionProcessor()]));
        $lastStartTagName = null;
        if (isset($test->lastStartTag)) {
            $lastStartTagName = $test->lastStartTag;
        }
        $doubleEscaped = isset($test->doubleEscaped) && $test->doubleEscaped;
        $tokenizer->pushState($this->convertState($initialState), $lastStartTagName);
        $result = $tokenizer->parseText($doubleEscaped ? self::unescape($test->input) : $test->input);

        $this->assertEquals($this->convertTokens($test->output, $doubleEscaped), $result->getTokens(), "Tokens failed to match expected");
        $this->assertEquals(
            array_map(array($this, 'convertError'),
                isset($test->errors) ? $test->errors : []
            ),
            array_map(array($this, 'convertParseError'), $result->getErrors()),
            "Errors failed to match expected");
    }

    private function convertParseError(HtmlParseError $error) {
        return $error->getCode();
    }

    private function convertError($error) {
        return $error->code;
    }

    private function convertTokens($tokens, $doubleEscaped) {
        $tokens = array_map(function($a) use ($doubleEscaped) { return $this->convertToken($a, $doubleEscaped); }, $tokens);
        $tokens[] = new HtmlEofToken();
        return $tokens;
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


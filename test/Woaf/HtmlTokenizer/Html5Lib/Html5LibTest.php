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
                    $tests[$fileName] = [$fileName, null];
                } else {
                    foreach ($fileTests->tests as $test) {
                        $initialStates = ["Data state"];
                        if (isset($test->initialStates)) {
                            $initialStates = $test->initialStates;
                        }
                        foreach ($initialStates as $initialState) {
                            $tests[$fileName . ' ' . $test->description . ' ' . $initialState] = [$fileName, $initialState, $test];
                        }
                    }
                }
            }
            return $tests;
        }

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
        $tokenizer->pushState($this->convertState($initialState), $lastStartTagName);
        $result = $tokenizer->parseText($test->input);

        $this->assertEquals(array_map(array($this, 'convertToken'), $test->output), $result->getTokens(), "Tokens failed to match expected");
        $this->assertEquals(array_map(array($this, 'convertError'), isset($test->errors) ? $test->errors : []), $result->getErrors(), "Errors failed to match expected");
    }

    private function convertError($error) {
        // TODO code, line, col
        switch ($error->code) {
            case "eof-in-tag":
                return new ParseError();
                break;
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

    private function convertToken(array $arrtok) {
        switch ($arrtok[0]) {
            case "Character":
                return new HtmlCharToken($arrtok[1]);
            case "Comment":
                return new HtmlCommentToken($arrtok[1]);
            case "EndTag":
                return new HtmlEndTagToken($arrtok[1], false, []);
            default:
                throw new \Exception("Unknown token type {$arrtok[0]}");
        }
    }

}


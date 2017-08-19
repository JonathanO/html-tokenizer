<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 08/08/2017
 * Time: 13:45
 */

namespace Woaf\HtmlTokenizer;


use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlToken;

class TokenStreamingTokenizer
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * TokenStreamingTokenizer constructor.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    private $st = null;

    public function pushState($state, $lastStartTagName) {
        $this->st = [$state, $lastStartTagName];
    }

    public function parseText($text) {
        $s = new HtmlStream($text, "UTF-8");
        $p = new HtmlTokenizer($s, $this->logger);
        if ($this->st) {
            $p->pushState($this->st[0], $this->st[1]);
        }
        $toks = [];
        $errors = [];
        foreach ($p->parse() as $t) {
            if ($t instanceof HtmlToken) {
                $toks[] = $t;
            } else {
                $errors[] = $t;
            }
        };
        return new TokenizerResult($this->compressTokens($toks), $errors);
    }

    private function compressTokens($tokens) {
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

}
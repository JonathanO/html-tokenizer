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
        $r = new TSTReceiver($this->logger);
        $s = new HtmlStream($text, "UTF-8");
        $p = new HtmlTokenizer($s, $r, $this->logger);
        if ($this->st) {
            $p->pushState($this->st[0], $this->st[1]);
        }
        $p->parse();
        return new TokenizerResult($this->compressTokens($r->getTokens()), $r->getErrors());
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

class TSTReceiver implements TokenReceiver {

    private $tokens = [];
    private $errors = [];

    private $logger;

    /**
     * TSTReceiver constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    public function consume(HtmlToken $token, TokenizerState $state)
    {
        $this->tokens[] = $token;
    }

    public function error(HtmlTokenizerError $error, TokenizerState $state)
    {
        $this->errors[] = $error;
    }

    public function endOfStream(TokenizerState $state)
    {
        // TODO: Implement endOfStream() method.
    }

    /**
     * @return array
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }



}
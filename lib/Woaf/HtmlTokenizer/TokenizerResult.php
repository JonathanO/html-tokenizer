<?php
namespace Woaf\HtmlTokenizer;

use Woaf\HtmlTokenizer\HtmlTokens\HtmlToken;

class TokenizerResult {

    private $tokens;
    private $errors;

    private $state;

    /**
     * TokenizerResult constructor.
     * @param HtmlToken[] $tokens
     * @param HtmlTokenizerError[] $errors
     * @param $state
     */
    public function __construct($tokens, $errors, $state = null)
    {
        $this->tokens = $tokens;
        $this->errors = $errors;
        $this->state = $state;
    }

    /**
     * @return HtmlToken[]
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * @return HtmlTokenizerError[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }




}
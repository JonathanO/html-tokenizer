<?php

namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;

use Woaf\HtmlTokenizer\HtmlTokenizerError;
use Woaf\HtmlTokenizer\TokenizerState;
use Woaf\HtmlTokenizer\TokenReceiver;

class ErrorReceiver
{
    /**
     * @var TokenizerState
     */
    private $state;

    /**
     * @var TokenReceiver
     */
    private $receiver;

    public function __construct(TokenizerState $state, TokenReceiver $receiver)
    {
        $this->state = $state;
        $this->receiver = $receiver;
    }

    public function error(HtmlTokenizerError $error)
    {
        $this->receiver->error($error, $this->state);
    }

}
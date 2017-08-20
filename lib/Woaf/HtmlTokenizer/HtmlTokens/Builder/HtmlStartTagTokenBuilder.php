<?php


namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;

use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;

class HtmlStartTagTokenBuilder extends HtmlTagTokenBuilder
{
    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    public function build(array &$errors, $line, $col = null) {
        return new HtmlStartTagToken($this->name, $this->isSelfClosing, $this->attributes);
    }

}
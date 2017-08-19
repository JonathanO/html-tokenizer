<?php


namespace Woaf\HtmlTokenizer\HtmlTokens;


use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlStartTagTokenBuilder;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlTagTokenBuilder;

class HtmlStartTagToken extends AbstractHtmlTagToken {

    /**
     * @return HtmlTagTokenBuilder
     */
    public static function builder(LoggerInterface $logger = null)
    {
        return new HtmlStartTagTokenBuilder($logger);
    }

    public function __toString() {
        return "<" . $this->getData() . " " . $this->buildAttributeString() . ($this->isSelfClosing() ? "/" : "") . ">";
    }
}
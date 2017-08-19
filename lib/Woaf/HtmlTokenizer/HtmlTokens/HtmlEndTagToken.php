<?php


namespace Woaf\HtmlTokenizer\HtmlTokens;


use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlEndTagTokenBuilder;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlTagTokenBuilder;

class HtmlEndTagToken extends AbstractHtmlTagToken {

    /**
     * @return HtmlTagTokenBuilder
     */
    public static function builder(LoggerInterface $logger = null)
    {
        return new HtmlEndTagTokenBuilder($logger);
    }

    public function __toString() {
        return "</" . $this->getData() . " " . $this->buildAttributeString() . $this->isSelfClosing() ? "/" : "" . ">";
    }
}
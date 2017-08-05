<?php


namespace Woaf\HtmlTokenizer\HtmlTokens;


use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlEndTagTokenBuilder;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlTagTokenBuilder;

class HtmlEndTagToken extends AbstractHtmlTagToken {

    /**
     * @return HtmlTagTokenBuilder
     */
    public static function builder()
    {
        return new HtmlEndTagTokenBuilder();
    }

    public function __toString() {
        return "</" . $this->getData() . " " . $this->buildAttributeString() . $this->isSelfClosing() ? "/" : "" . ">";
    }
}
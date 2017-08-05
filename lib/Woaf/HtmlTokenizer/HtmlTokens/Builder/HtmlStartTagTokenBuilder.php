<?php


namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;

use Woaf\HtmlTokenizer\HtmlTokens\HtmlStartTagToken;

class HtmlStartTagTokenBuilder extends HtmlTagTokenBuilder
{
    public function build(array &$errors) {
        return new HtmlStartTagToken($this->name, $this->isSelfClosing, $this->attributes);
    }

}
<?php


namespace Woaf\HtmlTokenizer\HtmlTokens;

use Woaf\HtmlTokenizer\Token;

interface HtmlToken extends Token
{

    public function __toString();

}
<?php


namespace Woaf\HtmlTokenizer;


interface HtmlTokenizerError extends Token
{

    public function __toString();
}
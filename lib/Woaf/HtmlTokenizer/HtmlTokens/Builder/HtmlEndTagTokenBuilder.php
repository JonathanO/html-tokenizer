<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 27/07/2017
 * Time: 17:56
 */

namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;

class HtmlEndTagTokenBuilder extends HtmlTagTokenBuilder
{
    public function build() {
        return new HtmlEndTagToken($this->name, $this->isSelfClosing, $this->attributes);
    }

}
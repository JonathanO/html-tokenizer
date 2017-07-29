<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:19
 */

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
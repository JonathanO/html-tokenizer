<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:19
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlStartTagTokenBuilder;
use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlTagTokenBuilder;

class HtmlStartTagToken extends AbstractHtmlTagToken {

    /**
     * @return HtmlTagTokenBuilder
     */
    public static function builder()
    {
        return new HtmlStartTagTokenBuilder();
    }
}
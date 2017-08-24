<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 20/08/2017
 * Time: 17:02
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlEofToken implements HtmlToken
{

    public function __toString()
    {
        return "EOF";
    }
}
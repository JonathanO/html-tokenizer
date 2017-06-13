<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:15
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlOpenTagEndToken implements HtmlToken
{

    /**
     * @var bool
     */
    private $close;

    /**
     * HtmlOpenTagEndToken constructor.
     * @param bool $close
     */
    public function __construct($close)
    {
        $this->close = $close;
    }

    /**
     * @return bool
     */
    public function isClose()
    {
        return $this->close;
    }



}
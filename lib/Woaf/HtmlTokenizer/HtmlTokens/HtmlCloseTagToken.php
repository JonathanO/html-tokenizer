<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:13
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlCloseTagToken implements HtmlToken
{
    /**
     * HtmlCloseTagToken constructor.
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @var string
     */
    private $name;


}
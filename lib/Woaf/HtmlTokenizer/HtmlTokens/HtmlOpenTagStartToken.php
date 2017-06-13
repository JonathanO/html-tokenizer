<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:12
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlOpenTagStartToken implements HtmlToken
{

    /**
     * @var string
     */
    private $name;
    /**
     * @var bool
     */
    private $voidElement;

    /**
     * HtmlOpenTagStartToken constructor.
     * @param string $name
     * @param $voidElement
     */
    public function __construct($name, $voidElement)
    {
        $this->name = $name;
        $this->voidElement = $voidElement;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function isVoidElement()
    {
        return $this->voidElement;
    }



}
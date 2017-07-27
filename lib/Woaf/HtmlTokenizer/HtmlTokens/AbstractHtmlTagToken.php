<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:19
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class AbstractHtmlTagToken implements HtmlToken
{

    /**
     * @var string
     */
    private $name;

    /**
     * @var boolean
     */
    private $isSelfClosing;

    private $attributes = [];

    /**
     * HtmlCDataToken constructor.
     * @param string $name
     * @param $isSelfClosing
     * @param $attributes
     */
    public function __construct($name, $isSelfClosing, $attributes)
    {
        $this->name = $name;
        $this->isSelfClosing = $isSelfClosing;
        $this->attributes = $attributes;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isSelfClosing()
    {
        return $this->isSelfClosing;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

}
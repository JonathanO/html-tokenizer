<?php


namespace Woaf\HtmlTokenizer\HtmlTokens;


abstract class AbstractHtmlTagToken implements HtmlToken
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

    protected function buildAttributeString()
    {
        return implode(" ", array_map(function($k, $v) { return "$k=\"$v\""; }, array_keys($this->getAttributes()), $this->getAttributes()));
    }

}
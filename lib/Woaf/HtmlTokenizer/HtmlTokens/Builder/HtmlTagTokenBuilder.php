<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 27/07/2017
 * Time: 17:59
 */

namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


abstract class HtmlTagTokenBuilder
{
    protected $name;
    protected $isSelfClosing = false;
    protected $attributes = [];

    private $lastAttribute;

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function addAttribute($name, $value) {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function isSelfClosing($isSelfClosing) {
        $this->isSelfClosing = $isSelfClosing;
        return $this;
    }

    public function addAttributeName($name) {
        $this->lastAttribute = $name;
        $this->attributes[$name] = null;
        return $this;
    }

    public function hasAttribute($name) {
        return array_key_exists($name, $this->attributes);
    }

    public function addAttributeValue($value) {
        if (!isset($this->lastAttribute)) {
            throw new \Exception("No open attribute!");
        }
        $this->attributes[$this->lastAttribute] = $value;
        return $this;
    }

    abstract public function build();
}
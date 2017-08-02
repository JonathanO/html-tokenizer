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

    private $currentAttributeName;
    private $currentAttributeValue;

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function addAttribute($name, $value) {
        if ($this->hasAttribute($name)) {
            throw new \Exception("Duplicate attribute name");
        }
        $this->lastAttribute = null;
        $this->attributes[$name] = $value;
        return $this;
    }

    public function startAttributeName($initialValue = "") {
        $this->lastAttribute = null;
        $this->currentAttributeName = $initialValue;
        return $this;
    }

    public function finishAttributeName($attributeName = "") {
        if ($this->currentAttributeName != null) {
            $attributeName = $this->currentAttributeName . $attributeName;
        }
        $this->currentAttributeName = null;
        $this->addAttributeName($attributeName);
        return $this;
    }

    public function isSelfClosing($isSelfClosing) {
        $this->isSelfClosing = $isSelfClosing;
        return $this;
    }

    public function addAttributeName($name) {
        if ($this->hasAttribute($name)) {
            throw new \Exception("Duplicate attribute name");
        }
        $this->lastAttribute = $name;
        $this->attributes[$name] = "";
        return $this;
    }

    public function hasAttribute($name) {
        return array_key_exists($name, $this->attributes);
    }

    public function startAttributeValue($start = "") {
        $this->currentAttributeValue = $start;
        return $this;
    }

    public function finishAttributeValue($attributeValue = "") {
        if ($this->currentAttributeValue != null) {
            $attributeValue = $this->currentAttributeValue . $attributeValue;
        }
        $this->currentAttributeValue = null;
        $this->addAttributeValue($attributeValue);
        return $this;
    }
    public function addAttributeValue($value) {
        if (!isset($this->lastAttribute)) {
            throw new \Exception("No open attribute!");
        }
        $this->attributes[$this->lastAttribute] = $value;
        $this->lastAttribute = null;
        return $this;
    }

    abstract public function build();
}
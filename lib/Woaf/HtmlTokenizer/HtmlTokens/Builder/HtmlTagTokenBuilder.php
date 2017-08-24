<?php


namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

abstract class HtmlTagTokenBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $name = "";
    protected $isSelfClosing = false;
    protected $attributes = [];

    private $lastAttribute;

    private $currentAttributeName;
    private $currentAttributeValue;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function appendToName($name) {
        assert($this->name  !== null, "Public identifier not initialized!");
        $this->name .= $name;
        return $this;
    }

    private function addAttribute($name, $value) {
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

    public function appendToAttributeName($value) {
        assert($this->currentAttributeName  !== null, "Public identifier not initialized!");
        $this->currentAttributeName .= $value;
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
        if ($this->logger) {
            $this->logger->debug("Added attribute name " . $name);
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

    public function appendToAttributeValue($value) {
        assert($this->currentAttributeValue  !== null, "Public identifier not initialized!");
        $this->currentAttributeValue .= $value;
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
    private function addAttributeValue($value) {
        if (!isset($this->lastAttribute)) {
            throw new \Exception("No open attribute!");
        }
        if ($this->logger) {
            $this->logger->debug("Added attribute value " . $value);
        }
        $this->attributes[$this->lastAttribute] = $value;
        $this->lastAttribute = null;
        return $this;
    }

    abstract public function build(array &$errors, $line, $col = null);
}
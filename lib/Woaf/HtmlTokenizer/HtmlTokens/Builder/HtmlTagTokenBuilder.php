<?php


namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\ProtectedBuffer;
use Woaf\HtmlTokenizer\TempBuffer;

abstract class HtmlTagTokenBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ProtectedBuffer
     */
    protected $name = null;
    protected $isSelfClosing = false;
    protected $attributes = [];

    private $lastAttribute = null;
    /**
     * @var TempBuffer
     */
    private $currentAttributeName;

    /**
     * @var TempBuffer
     */
    private $currentAttributeValue;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->name = new ProtectedBuffer();
        $this->currentAttributeName = new TempBuffer();
        $this->currentAttributeValue = new TempBuffer();
        $this->logger = $logger;
    }

    public function startName() {
        $this->name->init();
        return $this;
    }

    public function appendToName($name) {
        $this->name->append($name);
        return $this;
    }

    public function startAttribute() {
        assert($this->lastAttribute === null, "Last attribute {$this->lastAttribute} wasn't finished!");
        $this->currentAttributeName->init();
        $this->currentAttributeValue->init();
        return $this;
    }

    public function appendToAttributeName($value) {
        $this->currentAttributeName->append($value);
        return $this;
    }

    public function finishAttributeName($attributeName = "") {
        $this->currentAttributeName->append($attributeName);
        $this->addAttributeName($this->currentAttributeName->useValue());
        return $this;
    }

    public function isSelfClosing($isSelfClosing) {
        $this->isSelfClosing = $isSelfClosing;
        return $this;
    }

    private function addAttributeName($name) {
        assert($this->lastAttribute === null, "Last attribute {$this->lastAttribute} wasn't finished!");
        $this->lastAttribute = $name;
        if ($this->hasAttribute($name)) {
            $this->logger->debug("Found duplicate attribute $name");
            throw new AttributeException("Duplicate attribute name");
        }
        if ($this->logger) {
            $this->logger->debug("Adding attribute name " . $name);
        }
        return $this;
    }

    private function hasAttribute($name) {
        return array_key_exists($name, $this->attributes);
    }

    public function appendToAttributeValue($value) {
        $this->currentAttributeValue->append($value);
        return $this;
    }

    public function withEmptyAttributeValue() {
        assert($this->currentAttributeValue->getValue() === "", "Attribute value was populated.");
        $this->addAttributeValue($this->currentAttributeValue->useValue());
        return $this;
    }

    public function finishAttributeValue($attributeValue = "") {
        $this->currentAttributeValue->append($attributeValue);
        $this->addAttributeValue($this->currentAttributeValue->useValue());
        return $this;
    }

    private function addAttributeValue($value) {
        assert($this->lastAttribute !== null, "No open attribute");
        $name = $this->lastAttribute;
        $this->lastAttribute = null;
        if ($this->hasAttribute($name)) {
            // Silently discard it as it was a duplicate.
            if ($this->logger) {
                $this->logger->debug("Discarded value $value as it's a duplicate of attribute $name");
            }
            return $this;
        }
        if ($this->logger) {
            $this->logger->debug("Added attribute value " . $value . " to " . $name);
        }
        $this->attributes[$name] = $value;
        return $this;
    }

    protected function closeLastAttribute() {
        if ($this->lastAttribute != null) {
            $this->withEmptyAttributeValue();
        }
    }

    abstract public function build(array &$errors, $line, $col = null);
}
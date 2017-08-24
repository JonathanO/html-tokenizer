<?php


namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;
use Woaf\HtmlTokenizer\ProtectedBuffer;

class HtmlDocTypeTokenBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ProtectedBuffer
     */
    private $name = null;

    /**
     * @var ProtectedBuffer
     */
    private $publicIdentifier = null;

    /**
     * @var ProtectedBuffer
     */
    private $systemIdentifier = null;

    /**
     * @var boolean
     */
    private $forceQuirks = false;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->name = new ProtectedBuffer();
        $this->publicIdentifier = new ProtectedBuffer();
        $this->systemIdentifier = new ProtectedBuffer();
        $this->logger = $logger;
    }

    public function setNamePresent()
    {
        $this->name->init();
        return $this;
    }

    public function appendToName($str)
    {
        $this->name->append($str);
        return $this;
    }

    /**
     * @param bool $forceQuirks
     * @return HtmlDocTypeTokenBuilder
     */
    public function isForceQuirks($forceQuirks)
    {
        $this->forceQuirks = $forceQuirks;
        return $this;
    }

    public function build()
    {
        return new HtmlDocTypeToken($this->name->getValueOrNull(), $this->publicIdentifier->getValueOrNull(), $this->systemIdentifier->getValueOrNull(), $this->forceQuirks);
    }

    public function setPublicIdentifierPresent()
    {
        $this->publicIdentifier->init();
        return $this;
    }

    public function setSystemIdentifierPresent()
    {
        $this->systemIdentifier->init();
        return $this;
    }

    public function appendToPublicIdentifier($data)
    {
        $this->publicIdentifier->append($data);
        return $this;
    }

    public function appendToSystemIdentifier($data)
    {
        $this->systemIdentifier->append($data);
        return $this;
    }

}
<?php


namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;

class HtmlDocTypeTokenBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    private $name = null;

    /**
     * @var string
     */
    private $publicIdentifier = null;

    /**
     * @var string
     */
    private $systemIdentifier = null;

    /**
     * @var boolean
     */
    private $forceQuirks = false;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function setNamePresent()
    {
        assert($this->name  === null, "Public identifier not initialized!");
        $this->name = "";
        return $this;
    }

    public function appendToName($str)
    {
        assert($this->name  !== null, "Name not initialized!");
        $this->name .= $str;
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
        return new HtmlDocTypeToken($this->name, $this->publicIdentifier, $this->systemIdentifier, $this->forceQuirks);
    }

    public function setPublicIdentifierPresent()
    {
        assert($this->publicIdentifier  === null, "Public identifier not initialized!");
        $this->publicIdentifier = "";
    }

    public function setSystemIdentifierPresent()
    {
        assert($this->systemIdentifier  === null, "Public identifier not initialized!");
        $this->systemIdentifier = "";
    }

    public function appendToPublicIdentifier($data)
    {
        assert($this->publicIdentifier  !== null, "Public identifier not initialized!");
        $this->publicIdentifier .= $data;
        return $this;
    }

    public function appendToSystemIdentifier($data)
    {
        assert($this->systemIdentifier  !== null, "System identifier not initialized!");
        $this->systemIdentifier .= $data;
        return $this;
    }

}
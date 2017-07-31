<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 28/07/2017
 * Time: 22:24
 */

namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


use Woaf\HtmlTokenizer\HtmlTokens\HtmlDocTypeToken;

class HtmlDocTypeTokenBuilder
{
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

    /**
     * @param string $name
     * @return HtmlDocTypeTokenBuilder
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    /**
     * @param string $publicIdentifier
     * @return HtmlDocTypeTokenBuilder
     */
    public function setPublicIdentifier($publicIdentifier)
    {
        $this->publicIdentifier = $publicIdentifier;
        return $this;
    }

    /**
     * @param string $systemIdentifier
     * @return HtmlDocTypeTokenBuilder
     */
    public function setSystemIdentifier($systemIdentifier)
    {
        $this->systemIdentifier = $systemIdentifier;
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

}
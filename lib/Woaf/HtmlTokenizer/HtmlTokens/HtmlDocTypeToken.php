<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 28/07/2017
 * Time: 22:25
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


use Woaf\HtmlTokenizer\HtmlTokens\Builder\HtmlDocTypeTokenBuilder;

class HtmlDocTypeToken
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $publicIdentifier;

    /**
     * @var string
     */
    private $systemIdentifier;

    /**
     * @var boolean
     */
    private $forceQuirks;

    /**
     * HtmlDocTypeToken constructor.
     * @param string $name
     * @param string $publicIdentifier
     * @param string $systemIdentifier
     * @param bool $forceQuirks
     */
    public function __construct($name, $publicIdentifier, $systemIdentifier, $forceQuirks)
    {
        $this->name = $name;
        $this->publicIdentifier = $publicIdentifier;
        $this->systemIdentifier = $systemIdentifier;
        $this->forceQuirks = $forceQuirks;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getPublicIdentifier()
    {
        return $this->publicIdentifier;
    }

    /**
     * @return string
     */
    public function getSystemIdentifier()
    {
        return $this->systemIdentifier;
    }

    /**
     * @return bool
     */
    public function isForceQuirks()
    {
        return $this->forceQuirks;
    }

    public static function builder() {
        return new HtmlDocTypeTokenBuilder();
    }

}
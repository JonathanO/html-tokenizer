<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:14
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlAttrStartToken implements HtmlToken
{

    /**
     * @var string
     */
    private $name;

    /**
     * HtmlAttrNameToken constructor.
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }



}
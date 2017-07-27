<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 27/07/2017
 * Time: 17:59
 */

namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


abstract class TagTokenBuilder
{
    protected $name;
    protected $isSelfClosing = false;
    protected $attributes = [];

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function addAttribute($name, $value) {
        $this->attributes[$name] = $value;
    }

    public function isSelfClosing($isSelfClosing) {
        $this->isSelfClosing = $isSelfClosing;
        return $this;
    }



    abstract public function build();
}
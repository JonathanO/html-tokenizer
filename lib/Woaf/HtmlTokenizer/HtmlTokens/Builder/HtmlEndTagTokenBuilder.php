<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 27/07/2017
 * Time: 17:56
 */

namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\Tables\ParseErrors;

class HtmlEndTagTokenBuilder extends HtmlTagTokenBuilder
{
    public function build(array &$errors) {
        if ($this->isSelfClosing) {
            $errors[] = ParseErrors::getEndTagWithTrailingSolidus();
        }
        if ($this->attributes != []) {
            $errors[] = ParseErrors::getEndTagWithAttributes();
        }
        return new HtmlEndTagToken($this->name, false, []);
    }

}
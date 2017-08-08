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
    public function build(ErrorReceiver $receiver) {
        if ($this->isSelfClosing) {
            $receiver->error(ParseErrors::getEndTagWithTrailingSolidus());
        }
        if ($this->attributes != []) {
            $receiver->error($errors[] = ParseErrors::getEndTagWithAttributes());
        }
        return new HtmlEndTagToken($this->name, false, []);
    }

}
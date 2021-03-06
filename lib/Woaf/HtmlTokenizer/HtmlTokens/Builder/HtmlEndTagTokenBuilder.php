<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 27/07/2017
 * Time: 17:56
 */

namespace Woaf\HtmlTokenizer\HtmlTokens\Builder;


use Psr\Log\LoggerInterface;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlEndTagToken;
use Woaf\HtmlTokenizer\Tables\ParseErrors;

class HtmlEndTagTokenBuilder extends HtmlTagTokenBuilder
{
    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    public function build(array &$errors, $line, $col = null) {
        $this->closeLastAttribute();
        if ($this->isSelfClosing) {
            $errors[] = ParseErrors::getEndTagWithTrailingSolidus($line, $col);
        }
        if ($this->attributes != []) {
            $errors[] = ParseErrors::getEndTagWithAttributes($line, $col);
        }
        return new HtmlEndTagToken($this->name->getValue(), false, []);
    }

}
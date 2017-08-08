<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 08/08/2017
 * Time: 13:18
 */

namespace Woaf\HtmlTokenizer;


use Woaf\HtmlTokenizer\HtmlTokens\HtmlToken;

interface TokenReceiver
{

    public function consume(HtmlToken $token, TokenizerState $state);

    public function error(HtmlTokenizerError $error, TokenizerState $state);

    public function endOfStream(TokenizerState $state);

}
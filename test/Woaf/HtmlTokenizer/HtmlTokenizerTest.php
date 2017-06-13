<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:35
 */

namespace Woaf\HtmlTokenizer;

use PHPUnit\Framework\TestCase;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlAttrEndToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlAttrStartToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlAttrValueToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCharToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlCloseTagToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlOpenTagEndToken;
use Woaf\HtmlTokenizer\HtmlTokens\HtmlOpenTagStartToken;

class HtmlTokenizerTest extends TestCase
{
    public function testBasicElement() {
        $parser = new HtmlTokenizer();
        $tokens = $parser->parseText('<div class="foo">LOL</div>');
        $this->assertEquals([
            new HtmlOpenTagStartToken("div", false),
            new HtmlAttrStartToken("class"),
            new HtmlAttrValueToken("foo"),
            new HtmlAttrEndToken(),
            new HtmlOpenTagEndToken(false),
            new HtmlCharToken("LOL"),
            new HtmlCloseTagToken("div")
        ], $tokens);
    }

    public function testBasicHtml() {
        $parser = new HtmlTokenizer();
        $tokens = $parser->parseText(file_get_contents("basic.html"));
        $this->assertEquals([
            new HtmlOpenTagStartToken("div", false),
            new HtmlAttrStartToken("class"),
            new HtmlAttrValueToken("foo"),
            new HtmlAttrEndToken(),
            new HtmlOpenTagEndToken(false),
            new HtmlCharToken("LOL"),
            new HtmlCloseTagToken("div")
        ], $tokens);
    }

}

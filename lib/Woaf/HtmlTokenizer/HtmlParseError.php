<?php


namespace Woaf\HtmlTokenizer;


class HtmlParseError implements HtmlTokenizerError
{

    private $code;
    private $message;
    private $line;
    private $col;

    public function __construct($code, $message, $line, $col)
    {
        $this->code = $code;
        $this->message = $message;
        $this->line = $line;
        $this->col = $col;
    }

    public function __toString()
    {
        return "ParseError {$this->message} at {$this->line}:{$this->col}";
    }
}
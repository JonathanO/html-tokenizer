<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 27/07/2017
 * Time: 20:28
 */

namespace Woaf\HtmlTokenizer;


class ParseError implements Error
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
<?php


namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlCommentToken implements HtmlToken
{

    /**
     * @var string
     */
    private $data;

    /**
     * HtmlCDataToken constructor.
     * @param string $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    public function __toString()
    {
        return "<!--" . $this->getData() . "-->";
    }

}
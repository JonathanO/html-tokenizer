<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:19
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlCharToken implements HtmlToken
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
        return $this->getData();
    }

}
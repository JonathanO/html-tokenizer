<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 03/05/2017
 * Time: 17:23
 */

namespace Woaf\HtmlTokenizer\HtmlTokens;


class HtmlAttrValueToken implements HtmlToken
{
    /**
     * @var string
     */
    private $data;

    /**
     * HtmlAttrValueToken constructor.
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


}
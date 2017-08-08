<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 07/08/2017
 * Time: 15:53
 */

namespace Woaf\HtmlTokenizer;


use Woaf\HtmlTokenizer\Tables\State;

class TokenizerState
{

    private $state;

    public function __construct()
    {
        $this->state = State::$STATE_DATA;
    }


    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }



}
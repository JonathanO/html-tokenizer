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

    private $returnState = null;

    public function __construct()
    {
        $this->state = State::$STATE_DATA;
    }

    public function setReturnState($returnState)
    {
        assert($this->returnState == null);
        $this->returnState = $returnState;
    }

    public function setReturnPoint()
    {
        $this->setReturnState($this->state);
    }

    public function getReturnState()
    {
        assert($this->returnState != null);
        return $this->returnState;
    }

    public function doReturn()
    {
        assert($this->returnState != null);
        $this->state = $this->returnState;
        $this->returnState = null;
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
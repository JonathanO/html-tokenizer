<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 19/08/2017
 * Time: 16:29
 */

namespace Woaf\HtmlTokenizer;


class StreamLocation
{
    private $cur;
    private $curBytes;

    private $line;
    private $col;

    /**
     * StreamLocation constructor.
     * @param $cur
     * @param $curBytes
     * @param $line
     * @param $col
     */
    public function __construct($cur, $curBytes, $line, $col)
    {
        $this->cur = $cur;
        $this->curBytes = $curBytes;
        $this->line = $line;
        $this->col = $col;
    }

    /**
     * @return mixed
     */
    public function _getCur()
    {
        return $this->cur;
    }

    /**
     * @return mixed
     */
    public function _getCurBytes()
    {
        return $this->curBytes;
    }

    /**
     * @return mixed
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return mixed
     */
    public function getCol()
    {
        return $this->col;
    }




}
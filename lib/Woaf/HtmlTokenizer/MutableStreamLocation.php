<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 19/08/2017
 * Time: 16:29
 */

namespace Woaf\HtmlTokenizer;


class MutableStreamLocation
{
    public $cur = 0;
    public $curBytes = 0;

    public $line = 1;
    public $col = 0;

    public function __construct(MutableStreamLocation $from = null)
    {
        if ($from) {
            $this->update($from);
        }
    }

    public function newline()
    {
        $this->line++;
        $this->col = 0;
    }

    public function fromStreamLocation(StreamLocation $streamLocation) {
        $this->cur = $streamLocation->_getCur();
        $this->curBytes = $streamLocation->_getCurBytes();
        $this->col = $streamLocation->getCol();
        $this->line = $streamLocation->getLine();
        return $this;
    }

    public function asStreamLocation() {
        return new StreamLocation($this->cur, $this->curBytes, $this->line, $this->col);
    }

    public function update(MutableStreamLocation $streamLocation) {
        $this->cur = $streamLocation->cur;
        $this->curBytes = $streamLocation->curBytes;
        $this->line = $streamLocation->line;
        $this->col = $streamLocation->col;
        return $this;
    }

}
<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 20/08/2017
 * Time: 17:22
 */

namespace Woaf\HtmlTokenizer;


class TempBuffer extends ProtectedBuffer
{

    public function release() {
        $this->assertInitialized();
        $this->clear();
    }

    public function useValue() {
        $ret = $this->getValue();
        $this->release();
        return $ret;
    }

}
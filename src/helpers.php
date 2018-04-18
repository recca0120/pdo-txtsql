<?php

if (function_exists('tap') === false) {
    function tap($o, callable $cb)
    {
        cb($o);

        return $o;
    }
}

<?php

require_once('../Handleable/HandleableException.php');

class RouterException extends HandleableException
{
    static $Errors =
    [
        'METHOD' => 1,
        'INPUT' => 2
    ];
}

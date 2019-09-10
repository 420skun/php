<?php

namespace DB;

require_once('../Handleable/HandleableException.php');

class DBException extends \HandleableException
{
    static $Errors =
    [
        'CONFIG' => 1,
        'CONNECTION' => 2,
        'SQL' => 3,
        'VALUES' => 4
    ];
}
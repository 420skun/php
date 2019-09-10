<?php

namespace DB;
use PDO;

final class Config
{
    public $DSN;
    public $User;
    public $Password;

    public $ID = 'id';
    public $PasswordTable;
    public $PermissionTable;

    const OPTIONS = 
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    function __construct(string $host, string $database, string $user, string $password)
    {
        $this->DSN = "mysql:dbname=$database;host=$host";
        $this->User = $user;
        $this->Password = $password;
    }
}
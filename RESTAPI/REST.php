<?php

namespace REST;

require_once('../Router/Router.php');
require_once('../DB/DB.php');

use Router\RESTRouter;
use Router\RouterException;
use DB\DB;

class Config
{
    public $Router, $DB, $Present;

    function __construct()
    {
        $this->Router = new RESTRouter();
        $this->DB = DB::Share();
        $this->Present = function($results) { print(json_encode($results, JSON_PRETTY_PRINT)); };
    }
}

class API
{
    private $Config;
    private $Router;
    private $DB;

    function __construct(Config $config = NULL)
    {
        if (is_null($config)) $config = new Config();
        $this->Config = $config;
        $this->Router = $config->Router;
        $this->DB = $config->DB;

        $this->Service();
    }

    private function Present(array $results) { call_user_func($this->Config->Present, $results); }

    private function GET()
    {
        return
        [
            NULL,
            function($table)
            {
                $this->Present($this->DB->_Get($table));
            },
            function($table, $column)
            {
                $this->Present($this->DB->_Get($table, [ $column ]));
            },
            function($table, $column, $value)
            {
                $this->Present($this->DB->_Get($table, [], [ $column => $value ]));
            }
        ];
    }

    private function POST()
    {
        return
        [
            NULL,
            function($input, $table)
            {
                $this->Present($this->DB->_Set($table, $input));
            }
        ];
    }

    private function PUT()
    {
        return
        [
            NULL, NULL, NULL,
            function($input, $table, $column, $value)
            {
                $this->Present($this->DB->_Table($table)->Get([$column => $value])->Update($input)->Result());
            }
        ];
    }

    private function PATCH()
    {
        return
        [
            NULL, NULL, NULL,
            function($input, $table, $column, $value)
            {
                $this->Present($this->DB->_Set($table, $input, [ $column => $value ]));
            }
        ];
    }

    private function DELETE()
    {
        return
        [
            NULL, NULL, NULL,
            function($table, $column, $value)
            {
                $this->Present($this->DB->_Set($table, NULL, [ $column => $value ]));
            }
        ];  
    }

    private function Service()
    {
        $this->Router->_Summary($this->GET(), $this->POST(), $this->PUT(), $this->PATCH(), $this->DELETE());
    }
}
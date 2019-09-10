<?php

require_once("RouterException.php");

class Request
{
    private static function GetMethod(array $legal = NULL)
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if (!is_null($legal))
        {
            if (!array_key_exists($method, $legal)) RouterException::METHOD();
            
            else $method = $legal[$method];
        }

        return $method;
    }
    
    private const ROUTE_ALIAS = 'route';
    private static function GetRoute()
    {
        if (array_key_exists(self::ROUTE_ALIAS, $_GET)) return $_GET[self::ROUTE_ALIAS];
        else return '';
    }

    private static function GetInput()
    {
        return (array) json_decode(file_get_contents('php://input'));
    }

    public $Method;
    public $Route;
    public $Input;

    public function __construct(array $legal = NULL)
    {
        $this->Method = self::GetMethod($legal);
        $this->Route = self::GetRoute();
        $this->Input = self::GetInput();
    }

    private function GetArguments(array $parameters)
    {
        return array_combine($parameters, Route::Segment($this->Route));
    }
}
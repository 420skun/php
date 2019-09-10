<?php

abstract class HandleableException extends Exception
{
    private static $Handlers = [];

    static function OnError(callable $handler)
    {
        $class = get_called_class();

        self::$Handlers[$class] = $handler;
    }

    static function Error(int $code = NULL, string $message = NULL, Exception $thrown = NULL)
    {        
        $class = get_called_class();
        if (is_null($thrown)) $thrown = new Exception($message, $code);
        else if (is_null($message)) $message = $thrown;

        $exception = new $class($message, $code);

        if (array_key_exists($class, self::$Handlers) && is_callable(self::$Handlers[$class]))
        {
            self::$Handlers[$class]($exception, $thrown);
        }
        else throw $exception;
    }

    private static function GetTuple(string $class, string $error)
    {
        if (property_exists($class , 'Errors') && is_array($e = $class::$Errors[$error]))
        {
            if (count($e) == 2) return new ErrorTuple($e[0], $e[1]);
        }
        
        return new ErrorTuple();
    }

    static function __callStatic(string $method, array $arguments)
    {
        $thrown = NULL;
        $error = self::GetTuple(get_called_class(), $method);
        
        if (count($arguments) && $arguments[0] instanceof Exception) $thrown = $arguments[0];
        
        self::Error($error->Code, $error->Message, $thrown);
    }
}

class ErrorTuple
{
    public $Code;
    public $Message;

    function __construct(int $code = NULL, string $message = NULL)
    {
        $this->Code = $code;
        $this->Message = $message;
    }
}
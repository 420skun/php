<?php

abstract class Shareable
{
    private static $Shared = [];

    public static function Share()
    {
        $class = get_called_class();

        if (!array_key_exists($class, self::$Shared))
        {
            if (in_array('Instance', get_class_methods($class)))
            { 
                self::$Shared[$class] = $class::Instance();
            }
            else self::$Shared[$class] = new $class;
        }

        return self::$Shared[$class];
    }

    static function __callStatic(string $method, array $arguments)
    {
        return call_user_func([self::Share(), "_$method"], ...$arguments);
    }
}
<?php

namespace DB;

require_once('ChainlessQuery.php');

abstract class Condition extends BoundQuery
{
    function __construct(string $template, array $columns, array $values, string $link)
    {
        parent::__construct(new Query($template), new Binding('', $columns, $values, $link));
    }

    function And(Condition $condition, bool $parentheses = false)
    {
        if (!$parentheses) return $this->Chain($condition, self::AND);
        else return $this->Wrap($condition, self::AND);
    }

    function Or(Condition $condition, bool $parentheses = false)
    {
        if (!$parentheses) return $this->Chain($condition, self::OR);
        else return $this->Wrap($condition, self::OR);    
    }

    static function Equals(array $binding, bool $positive = true, string $link = self::AND)
    {
        return new Equal($binding, $positive, $link);
    }

    static function Like(array $binding, bool $positive = true, string $link = self::AND)
    {
        return new Like($binding, $positive, $link);
    }

    static function IsNull(array $columns, bool $positive = true, string $link = self::AND)
    {
        return new IsNull($columns, $positive, $link);
    }
}

class Equal extends Condition
{
    const EQUALS = '[@s = @v]';
    const UNEQUALS = '[@s != @v]';

    function __construct(array $binding, bool $positive, string $link)
    {
        if ($positive) $template = self::EQUALS; else $template = self::UNEQUALS;

        parent::__construct($template, array_keys($binding), array_values($binding), $link);
    }
}

class Like extends Condition
{
    const LIKE = '[@s LIKE @v]';
    const UNLIKE = '[@s NOT LIKE @v]';

    function __construct(array $binding, bool $positive = true, string $link = self::AND)
    {   
        if ($positive) $template = self::LIKE; else $template = self::UNLIKE;

        $wildcards = array_map(function($v) { return "%$v%"; }, array_values($binding));
        parent::__construct($template, array_keys($binding), $wildcards, $link);
    }
}

class IsNull extends Condition
{
    const ISNULL = '[@s IS NULL]';
    const ISNOTNULL = '[@s IS NOT NULL]';

    function __construct(array $columns, bool $positive = true, string $link = self::AND)
    {
        if ($positive) $template = self::ISNULL; else $template = self::ISNOTNULL;

        parent::__construct($template, $columns, [], $link);
    }
}
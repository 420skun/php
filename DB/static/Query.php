<?php

class Query
{
    const SPACE = '';
    const COMMA = ',';
    const AND = 'AND';
    const OR = 'OR';

    public $Template;
    public $Position;

    function __construct(string $template, int $position = 0)
    {
        $this->Template = $template;
        $this->Position = $position;
    }

    public $Chain = [];

    function GetPosition()
    {
        if (!empty($this->Chain)) return max(array_keys($this->Chain));
        else return 0;
    }

    function Chain(BoundQuery ...$templates)
    {
        foreach ($templates as $template) $this->Chain[$template->Position] = $template;

        return $this;
    }

    function Append(BoundQuery ...$templates)
    {
        foreach ($templates as $template)
        {
            $position = $this->GetPosition() + 1;
            $template->Position = $position;
            $this->Chain[$position] = $template;
        }
    }

    function Merge()
    {
        $merged = $this->Chain;
        $merged[$this->Position] = $this;
        ksort($merged);
        return $merged;
    }

    function GetStatement()
    {
        $statements = array_map(function($template) { return $template->Template; }, $this->Merge());
        return implode(' ', $statements);
    }

    function Expand(int $selections, int $values, string $link)
    {
        $result = '';
        $opened = false;
        $expand = '';

        foreach (str_split($this->Template) as $i => $char)
        {
            if ($opened)
            {
                if ($char == open_select || $char == open_select)
                {
                    $opened = false;
                    $result .= implode(" {$link} ", array_fill(0, $count, $expand));
                    $expand = '';
                }
                else $expand .= $char;
            }
            else if ($char == close_select || $char == close_value) $opened = true;
            else $result .= $char;
        }

        if (strlen($expand)) $result .= $expand;

        return $result;
    }

    function Assemble(string $table, array $selection = [], array $values = [], string $link = self::COMMA)
    {
        $expansion = $this->Expand(count($selection), $link);
        $result = '';        

        $special = '@';
        $specialed = false;

        foreach (str_split($expansion) as $char)
        {
            if ($char == $special) $specialed = true;
            else if ($specialed)
            {
                $specialed = false;

                switch ($char)
                {
                    case 's':
                        $result .= current($selection);
                        next($selection);
                        break;
                    case 'v':
                        $result .= '?';
                        break;
                    case 't':
                        $result .= $table;
                }
            }
            else $result .= $char;
        }

        $new = $this;
        $new->Template = $result;
        return new BoundQuery($new->GetStatement(), $this->Position, $values);
    }
}

class BoundQuery extends Query
{
    public $Values;

    function __construct(string $statement, int $position, array $values)
    {
        parent::__construct($statement, $position);
        $this->Values = $values;
    }

    function GetValues()
    {
        $values = [];

        foreach ($this->Merge() as $template) $values = array_merge($values, $template->Values);

        return $values;
    }
}

class QueryContainer extends BoundQuery
{
    function __construct() { parent::__construct('', 999, []); }
}
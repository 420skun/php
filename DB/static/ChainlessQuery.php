<?php

namespace DB;

class Query
{
    const SPACE = '';
    const COMMA = ',';
    const AND = 'AND';
    const OR = 'OR';

    public $Template;
    public $Position;
    public $Values = [];

    function __construct(string $template = '', int $position = 0)
    {
        $this->Template = $template;
        $this->Position = $position;
    }

    function Chain(Query $query, string $link = self::SPACE)
    {
        $query = $query->Bind();
        $this->Values = array_merge($this->Values, $query->Values);
        $this->Template = "{$this->Template} $link {$query->Template}";
        return $this;

        // $reversed = new BoundQuery($query, $query->Template, []);
        // return new ChainedQuery($this, $reversed, $link);
    }

    function Wrap(Query $query, string $link = self::SPACE) // might get merged with chain if no reverse
    {
        $query->Template = "({$query->Template})";
        return $this->Chain($query, $link);
    }

    function Bind(string $table = '', array $selection = [], array $values = [], string $link = self::COMMA)
    {
        return new BoundQuery($this, new Binding($table, $selection, $values, $link));
    }

    function BindClass(Binding $binding) { return new BoundQuery($this, $binding); }
}

class Binding
{
    public $Table;
    public $Selection;
    public $Values;
    public $Link;

    function __construct(string $table = '', array $selection = [], array $values = [], string $link = Query::COMMA)
    {
        $this->Table = $table; $this->Selection = $selection; $this->Values = $values; $this->Link = $link;
    }
}

class BoundQuery extends Query
{
    function __construct(Query $query, Binding $binding)
    {
        $statement = self::Assemble($query->Template, $binding);
        parent::__construct($statement, $query->Position);
        $this->Values = $binding->Values;
    }

    // @override
    function Bind(string $table = '', array $selection = [], array $values = [], string $link = self::COMMA) { return $this; }

    private static function Expand(string $template, string $open, string $close, int $count, string $link)
    {
        $result = '';
        $opened = false;
        $expand = '';

        foreach (str_split($template) as $i => $char)
        {
            if ($opened)
            {
                if ($char == $close)
                {
                    $opened = false;
                    $result .= implode(" $link ", array_fill(0, $count, $expand));
                    $expand = '';
                }
                else $expand .= $char;
            }
            else if ($char == $open) $opened = true;
            else $result .= $char;
        }

        if (strlen($expand)) $result .= $expand;

        return $result;
    }

    private static function Assemble(string $template, Binding $binding)
    {
        $expansion = self::Expand($template, '[', ']', count($binding->Selection), $binding->Link);
        $expansion = self::Expand($expansion, '<', '>', count($binding->Values), $binding->Link);
        $selection = $binding->Selection;
        
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
                        $current = current($selection);
                        $result .= "`$current`";
                        next($selection);
                        break;
                    case 'v':
                        $result .= '?';
                        break;
                    case 't':
                        $result .= $binding->Table;
                }
            }
            else $result .= $char;
        }

        return $result;
    }
}

class QueryContainer
{
    private $Queries = [];
    static $Position = 0;

    function Add(BoundQuery $query, bool $new = false) 
    { 
        if (!$new) $container =& $this; else $container = $this;

        if ($query->Position > $container::$Position) $container::$Position = $query->Position;

        $container->Queries[$query->Position] = $query; 
        return $container;
    }

    function Append(BoundQuery $query) // not sure if used
    {
        self::$Position++;
        $this->Queries[self::$Position] = $query;
        return $this;
    }

    function GetStatement()
    {
        ksort($this->Queries);
        $statements = array_map(function($query) { return $query->Template; }, $this->Queries);
        return implode(' ', $statements);
    }

    function GetValues()
    {
        ksort($this->Queries);
        $values = [];
        foreach ($this->Queries as $query) $values = array_merge($values, $query->Values);
        return $values;
    }
}
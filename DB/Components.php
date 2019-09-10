<?php

namespace DB;

class Component extends \Shareable
{
    protected $DB;

    function __construct(DB $db = NULL) 
    { 
        if (is_null($db)) $db = DB::Share();
        $this->DB = $db; 
    }
}

class Columns extends Component
{
    public $Columns;
    public $Fields;
    public $Required;
    public $Types;
    public $ID;
    
    function __construct(string $table, DB $db)
    {
        parent::__construct($db);

        $this->Columns = $this->DB->_Execute(SQL::COLUMNS($table)->Template)->fetchAll();

        foreach ($this->Columns as $column)
        {
            $field = $column->Field;
            $this->Fields[] = $field;

            if ($column->Null != 'YES' && $column->Key != 'PRI' && $column->Extra != 'auto_increment' && !$column->Default)
            {
                $this->Required[] = $field;
            }
        }

        $this->Types = self::GetTypes($table);
    }

    private function GetTypes(string $table)
    {
        $sample = $this->DB->_Execute(SQL::SELECTALL($table)->Template)->fetch();
        $types = []; 
        
        foreach ($sample as $column => $data) $types[$column] = gettype($data);
        return $types;
    }

    function CheckColumns(array $columns)
    {
        return empty(array_diff($this->Required, $columns));
    }

    function CheckTypes(array $binding)
    {
        foreach ($this->Types as $column => $type)
        {
            if (!array_key_exists($column, $binding)) continue;
            if (!settype($binding[$column], $type)) return false;
        }

        return true;
    }

    function Check(array $binding)
    {
        return $this->CheckColumns(array_keys($binding)) && $this->CheckTypes($binding);
    }

    static function QCheck(string $table, array $binding) { return (new Columns($table))->Check($binding); }
}

class ABC extends Component
{
    function _Test()
    {
        print_r($this->DB::Get('dupa'));
    }
}
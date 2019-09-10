<?php

namespace DB;

final class SQL
{
    const SELECT = 'SELECT [@s] FROM @t';
    const SELECTALL = 'SELECT * FROM @t';
    const WHERE = 'WHERE';
    const INSERT = 'INSERT INTO @t ([@s]) VALUES ([@v])';
    const FULLINSERT = 'INSERT INTO @t VALUES (<@v>)';
    const COLUMNS = 'SHOW COLUMNS FROM @t';
    const DELETE = 'DELETE FROM @t';
    const UPDATE = 'UPDATE @t SET [@s = @v]';

    static function __callStatic(string $alias, array $arguments) 
    {
        return (new Query(constant("self::$alias")))->Bind(...$arguments);        
    }
}

class Action extends QueryContainer
{
    protected $DB;
    public $Table;
    public $Columns;

    function __construct(DB $db, string $table = '')
    { 
        $this->DB = $db;
        $this->Table = $table;
        if(strlen($table)) $this->Columns = new Columns($table, $db);
    }

    protected function Build(string $query, int $position, array $selection = [], array $values = [], string $link = Query::COMMA)
    {
        $q = new Query($query, $position);
        return $q->Bind($this->Table, $selection, $values, $link);
    }

    function Result(bool $debug = false)
    {
        $result = $this->DB->_Execute($this->GetStatement(), $this->GetValues(), $debug);
        $this->DB->_Commit();
        return $result->fetchAll();
    }

    function Select(string ...$selection)
    {
        if (empty($selection)) $query = SQL::SELECTALL($this->Table);
        else $query = SQL::SELECT($this->Table, $selection);

        return $this->Add($query);
    }

    function Where(Condition $conditions)
    {
        $where = $this->Build(SQL::WHERE, 1)->Chain($conditions);
        return $this->Add($where);
    }

    function Get(array $conditions, bool $positive = true, string $link = Query::AND)
    {
        return $this->Where(Condition::Equals($conditions));
    }

    function Find(array $conditions, bool $positive = false, string $link = Query::OR)
    {
        return $this->Where(Condition::Like($conditions));
    }

    function Debug() { $this->Result(true); }

    function Insert(array $binding)
    {
        if (!$this->Columns->Check($binding)) DBException::VALUES();

        return $this->Add(SQL::INSERT($this->Table, array_keys($binding), array_values($binding)));
    }

    function Purge()
    {
        return $this->Add(SQL::DELETE($this->Table));
    }

    function Patch(array $binding)
    {
        if (!$this->Columns->CheckTypes($binding)) DBException::VALUES();

        return $this->Add(SQL::UPDATE($this->Table, array_keys($binding), array_values($binding)));
    }

    function Update(array $binding)
    {
        if (!$this->Columns->Check($binding)) DBException::VALUES();

        return $this->Patch($binding);
    }
}

class ModAction extends Action
{
    private $Affected;
    private $Inserted = false;
    private $Removed = false;

    function __construct(DB $db, string $table = '')
    {
        parent::__construct($db, $table);
        $this->Affected = (new Action($db, $table))->Select();
    }

    function Where(Condition $conditions)
    {
        $this->Affected->Where($conditions);
        return parent::Where($conditions);
    }

    function Insert(array $binding)
    {   
        $this->Inserted = true;
        return parent::Insert($binding);
    }

    function Purge()
    {
        $this->Removed = true;
        return parent::Purge();
    }

    function Result(bool $debug = false)
    {
        if ($this->Inserted)
        {
            $result = $this->DB->_Execute($this->GetStatement(), $this->GetValues(), $debug);
            $condition = [ $this->DB->Config->ID => $this->DB->PDO->lastInsertId() ];
            return $this->Affected->Get($condition)->Result();
        }
        
        if ($this->Removed)
        {
            $deleted = $this->Affected->Result();
            parent::Result($debug);
            return $deleted;
        }

        if (empty($result = parent::Result($debug))) $result = $this->Affected->Result();

        return $result;
    }
}

class BulkAction extends ModAction
{
    const INSERT = 'Insert';

    function Bulk(string $method, array $sets)
    {
        $result = [];

        foreach ($sets as $arguments) 
        {
            $result[] = call_user_func([$this, $method], ...$arguments)->Result();
        }

        return $result;
    }

    function ConditionBulk(array $conditions)
    {
        $result = [];

        foreach ($conditions as $condition) $result[] = $this->Get($condition)->Result();

        return $result;
    }

    function Sequence(string $method, array $sets)
    {

    }

    function ConditionSequence(string $method)
    {

    }

    /// helper

    function BulkInsert(array $sets) { return $this->Bulk(self::INSERT, $sets); }

    function BulkUpdate(array $binding, array $conditions)
    {
        return $this->Update($binding)->ConditionBulk($conditions);
    }

    function BulkDelete()
    {

    }
}
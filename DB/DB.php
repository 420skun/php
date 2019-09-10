<?php

namespace DB;

require_once('../Shareable/Shareable.php');
require_once('static/ChainlessQuery.php');
require_once('static/Condition.php');
require_once('static/DBException.php');
require_once('Components.php');
require_once('SQL.php');
require_once('Config.php');

use PDO;
use PDOException;

class DB extends \Shareable
{
    public $PDO;
    public $Config;

    /// connect

    function __construct(Config $config)
    {
        try
        {
            $this->PDO = new PDO($config->DSN, $config->User, $config->Password, Config::OPTIONS);
        } catch (PDOException $e) { DBException::CONNECTION($e); }

        $this->Config = $config;

        register_shutdown_function([$this, '_Commit']);
    }

    private static $GlobalConfig;
    static function Config(Config $config) { self::$GlobalConfig = $config; }

    static function Instance() // @override
    {
        if (is_null(self::$GlobalConfig)) DBException::CONFIG();

        return new DB(self::$GlobalConfig);
    }

    function _Commit()
    {
        if ($this->PDO->inTransaction()) $this->PDO->commit();
    }

    function _Revert()
    {
        if ($this->PDO->inTransaction()) $this->PDO->rollBack();
    }

    function _Table(string $table = '')
    {
        return (new BulkAction($this, $table))->Select();
    }

    function _Get(string $table, array $columns = [], array $conditions = [], bool $find = false)
    {
        $selection = $this->_Table($table)->Select(...$columns);

        if (!empty($conditions))
        {
            if ($find) $selection = $selection->Find($conditions);
            else $selection = $selection->Get($conditions);
        }

        return $selection->Result();
    }

    function _Set(string $table, array $binding = NULL, array $conditions = [], bool $find = false)
    {
        $action = $this->_Table($table);
        
        if (!empty($conditions))
        {
            if ($find) $action = $action->Find($conditions);
            else $action = $action->Get($conditions);

            if (is_null($binding)) $action = $action->Purge();
            
            else $action = $action->Patch($binding);
        }
        else $action = $action->Insert($binding);

        return $action->Result();
    }

    function _Execute(string $statement, array $values = [], bool $debug = false)
    {
        if ($debug)
        {
            print_r($values);
            print($statement);
            die();
        }

        $this->_Commit();
        $this->PDO->beginTransaction();

        try 
        { 
            $stmt = $this->PDO->prepare($statement); 
            $stmt->execute($values);
        }
        catch (PDOException $e) { DBException::SQL($e); }

        return $stmt;
    }

    function _Query(BoundQuery $query, bool $debug = false)
    {
        return $this->_Execute($query->Template, $query->Values, $debug);
    }

    function _Bulk(string $statement, array $repetitions) 
    { 
        $result = [];

        foreach ($repetitions as $values) $result[] = $this->_Execute($statement, $values);

        return $result;
    }

    function _Sequence(string $statement, array $repetitions)
    {
        $sequence = array_fill(0, count($repetitions), "$statement;");
        $all_values = [];

        foreach ($repetitions as $values) foreach ($values as $value) $all_values[] = $value;

        return $this->_Execute($sequence, $all_values);
    }

    // function _BulkAction(string $statement, array $bindings, Condition $conditions = NULL)
    // {
    //     $result = [];
    //     $action = $this->_Table();
    //     if (!is_null($conditions)) $action->Where($conditions);
    //     $query = new Query($statement);

    //     foreach ($bindings as $binding)
    //     {
    //         $next = $action;
    //         $bound = $query->BindClass($binding);
    //         $result[] = $next->Add($bound)->Result();
    //     }

    //     return $result;
    // }
}
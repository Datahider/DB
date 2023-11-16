<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBList
 *
 * @author drweb_000
 */
class DBList {
    
    protected DBView $dbview;
    protected string $class;


    public function __construct(string $class, array|string $filter, array|string|null $params=null) {
        $this->class = $class;
        
        if (is_array($filter) && is_null($params)) {
            $this->construct2Args($class, $filter);
        } elseif (is_string($filter) && !is_null($params)) {
            $this->construct3Args($class, $filter, $params);
        } else {
            throw new \Exception('Invalid argument type.');
        }
    }
    
    protected function construct2Args($class, $filter) {
        $primary_key = $class::getPrimaryKey();
        $table = $class::tableName();
        $where = $this->where($filter);
        
        $this->dbview = new DBView("SELECT $primary_key FROM $table WHERE $where", $filter);
    }
    
    protected function construct3Args($class, $filter, $params) {
        $primary_key = $class::getPrimaryKey();
        $table = $class::tableName();
        
        $this->dbview = new DBView("SELECT $primary_key FROM $table WHERE $filter", $params);
    }
    
    protected function where(array $filter) {
        $result_array = [];
        
        foreach (array_keys($filter) as $key) {
            $result_array[] = "$key = :$key";
        }
        return implode(" AND ", $result_array);
    }
    
    public function next() : DBObject|false {
        if ($this->dbview->next()) {
            $class = $this->class;
            $primary_key = $class::getPrimaryKey();
            
            $obj = new $class([$primary_key => $this->dbview->$primary_key]);
            return $obj;
        } else {
            return false;
        }
    }
    
    public function reset() {
        $this->dbview->reset();
    }
    
    public function asArray() {
        
        $result = [];
        
        $this->reset();
        while ($obj = $this->next()) {
            $result[] = $obj;
        }
        
        $this->reset();
        return $result;
    }
}

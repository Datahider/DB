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
abstract class DBList extends \losthost\SelfTestingSuite\SelfTestingClass {
    
    const SQL_QUERY = <<<END
            SELECT id
            FROM [some_table]
            WHERE ?
            END; // use [] to represent a table in the query to automatically add the DB::$prefix
    
    protected $__sql_query;
    protected $__params;
    protected $__data;
    protected $__pointer;
    protected $__out_of_data;

    public function __construct($params = []) {
        
        $prefix = DB::$prefix;
        $this->__sql_query = preg_replace("/\[(.*?)\]/", "$prefix$1", static::SQL_QUERY);

        if (is_scalar($params)) {
            $this->__params = [$params];
        } else {
            $this->__params = $params;
        }
        
        $this->fetch();
        
    }
    
    public function fetch($params=null) {

        if ($params !== null) {
            if (is_scalar($params)) {
                $this->__params = [$params];
            } else {
                $this->__params = $params;
            }
        }
        
        $sth = DB::$pdo->prepare($this->__sql_query);
        
        if ($sth->execute($this->__params) === false) {
            $error_info = $sth->errorInfo();
            throw new Exception($error_info[2], $error_info[1]);
        }
        
        $this->__data = $sth->fetchAll(\PDO::FETCH_ASSOC);
        $this->__pointer = -1;
        $this->__out_of_data = true;
        
    }
    
    public function reset() {
        $this->__pointer = -1;
        $this->__out_of_data = true;
    }
    
    public function next() {
        $this->__pointer++;
        $this->__out_of_data = count($this->__data) <= $this->__pointer;
        return !$this->__out_of_data;
    }
    
    public function __get($name) {
        if ($this->__out_of_data) {
            throw new \Exception("The list is out of data.");
        }
        if (array_key_exists($name, $this->__data[$this->__pointer])) {
            return $this->__data[$this->__pointer][$name];
        } else {
            throw new \Exception("Field $name does not exist in the local data set.", -10003);
        }
    }
    
    
}

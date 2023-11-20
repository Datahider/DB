<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBView
 *
 * @author drweb
 */
class DBView extends DBBaseClass {
    
    protected $__sql;
    protected $__params;
    protected $__field_types;
    protected $__data;
    protected $__pointer;


    public function __construct(string $sql, $params=[]) {
        $this->fetch($sql, $params);
    }
    
    protected function fetch($sql=null, $params=null, $vars=[]) {
        
        if ($sql !== null) {
            $this->__sql = $sql;
        }
        if ($params !== null) {
            if (!is_array($params)) {
                $this->__params = [$params];
            } else {
                $this->__params = $params;
            }
        }
        
        $sth = $this->prepare($this->__sql, $vars);
        $sth->execute($this->filterTypes($this->__params));
        
        
        $this->fillTypes($sth);
        $this->__data = $sth->fetchAll(\PDO::FETCH_ASSOC);
        $this->__pointer = -1;
        return $this->__data;
    }
    
    protected function fillTypes(\PDOStatement &$sth) {
        $this->__field_types = [];
        for ($index = 0; true; $index++) {
            $meta = $sth->getColumnMeta($index);
            if ($meta === false) {
                break;
            }
            
            if ( true
                    && $meta['native_type'] == 'TINY'
                    && $meta['len'] == 1
                    && (count($meta['flags']) == 0 || count($meta['flags']) == 1 && $meta['flags'][0] == 'not_null')) {
                $this->__field_types[$meta['name']] = 'BOOL';
            } else {
                $this->__field_types[$meta['name']] = $meta['native_type'];
            }
        }
    }
    
    public function __get($name) {
        if ($this->isOutOfRange()) {
            throw new \Exception('Out of range', -10009);
        }
        if (!array_key_exists($name, $this->__data[$this->__pointer])) {
            throw new \Exception('Field does not exist', -10003);
        }
        if ($this->__data[$this->__pointer][$name] === null) {
            return null;
        } elseif ($this->__field_types[$name] == 'BOOL') {
            return (bool) $this->__data[$this->__pointer][$name];
        } elseif ($this->__field_types[$name] == 'DATETIME') {
            return new \DateTimeImmutable($this->__data[$this->__pointer][$name]);
        } else {
            return $this->__data[$this->__pointer][$name];
        }
    }
    
    public function next() {
        $this->__pointer++;
        return !$this->isOutOfRange();
    }

    public function reset() {
        $this->__pointer = -1;
    }
    
    protected function isOutOfRange() {
        return $this->__pointer < 0 || $this->__pointer >= count($this->__data);
    }

    protected function prepare($sql, $vars=[]) {
        return DB::PDO()->prepare($this->replaceVars($sql, $vars));
    }
    
    protected function replaceVars($string, $vars=[]) {
    
        $default_vars = [
            'DATABASE' => DB::$database,
        ];
        
        $full_vars = array_replace($default_vars, $vars);

        $result = $string;
        foreach ($full_vars as $key => $value) {
            if ($value === null) {
                $value = '';
            }
            $result = str_replace("%$key%", $value, $result);
        }
        
        $prefix = DB::$prefix;
        
        return \preg_replace("/\[(\w+)\]/", "$prefix$1", $result);

    }
}

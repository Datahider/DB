<?php

/*
 * Класс DBFather - прародитель всех классов использующих базу данных как хранилище информации
 * 
 */

namespace losthost\DB;

/**
 * Description of DBFather
 *
 * @author drweb
 */
abstract class DBObject extends \losthost\SelfTestingSuite\SelfTestingClass {
    
    /* 
     * В дочерних классах определите константы CREATE_TABLE И UPGRADE_FROM_N_N_N
     * для автоматического создания и обновления структуры таблицы в которой хранятся
     * данные объектов. Например
     * 
     сonst SQL_CREATE_TABLE = <<<END
            CREATE TABLE IF NOT EXISTS %TABLE_NAME% (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                data varchar(256) NOT NULL DEFAULT '',
                PRIMARY KEY (id)
            ) COMMENT = 'v1.0.0';  // <-- это исходная версия таблицы (хранится в комментарии)
            END;
    

     const SQL_UPGRADE_FROM_1_0_0 = <<<END
            ALTER TABLE %TABLE_NAME% COMMENT = 'v1.0.9',  // <-- не забудьте обновить версию таблицы
            ADD COLUMN more_data TEXT;
            END;
    
     */

    protected $__table_name;
    
    protected $__fields_list;
    protected $__values_list;
    protected $__field_value_pairs;


    protected $__data = [];
    protected $__is_new = true;


    protected static $__fields = [];
    protected static $__labels = [];
    protected static $__pri = [];
    protected static $__autoincrement = [];

    public function __construct($where = null, $params = []) {
        $this->__table_name = static::TABLE_NAME;

        $this->initDataStructure();
        
        if ($where !== null) {
            if (!$this->fetch($where, $params)) {
                throw new \Exception('Not found');
            }
        }
    }
   
    public function fetch($where, $params = []) {
        $sth = $this->prepare(static::SQL_SELECT, [ 'WHERE' => $where ]);
        
        if (is_scalar($params)) {
            $params = [$params];
        }
        
        $sth->execute($params);
        
        $result = $sth->fetch(\PDO::FETCH_ASSOC);
        
        if ($sth->fetch()) {
            throw new \Exception("More than one row selected.", -10002);
        }
        
        if (!$result) {
            return false;
        }
        
        $this->__data = $result;
        $this->__is_new = false;
        return true;
    }
    
    public function write() {
        if ($this->__is_new) {
            $sth = $this->prepare(static::SQL_INSERT);
            $sth->execute($this->__data);
            if ($this->getAutoIncrement()) {
                $this->__data[$this->getAutoIncrement()] = DB::$pdo->lastInsertId();
            }
            $this->__is_new = false;
        } else {
            $sth = $this->prepare(static::SQL_UPDATE, [ 'WHERE' => $this->getPrimaryKey(). ' = :'. $this->getPrimaryKey()]);
            $sth->execute($this->__data);
        }
    }
    
    public function isNew() {
        return $this->__is_new;
    }
    
    public function getFields() {
        if (!isset(self::$__fields[get_class($this)])) {
            $this->fetchDataStructure();
        }
        return self::$__fields[get_class($this)];
    }
    
    public function getAutoIncrement() {
        if (!isset(self::$__fields[get_class($this)])) {
            $this->fetchDataStructure();
        }
        return self::$__autoincrement[get_class($this)];
    }

    public function getPrimaryKey() {
        if (!isset(self::$__fields[get_class($this)])) {
            $this->fetchDataStructure();
        }
        return self::$__pri[get_class($this)];
    }

    public function getLabel($field_name) {
        if (array_key_exists($field_name, self::$__labels[get_class($this)])) {
            return self::$__labels[get_class($this)][$field_name];
        } else {
            throw new \Exception('Unknown field: '. $field_name, -10003);
        }
    }
    
    public function __set($name, $value) {
        if (array_key_exists($name, $this->__data)) {
            $this->checkSetField($name);
            $this->__data[$name] = $value;
        } else {
            throw new \Exception("Field $name does not exist in the local data set.", -10003);
        }
    }
    
    public function __get($name) {
        if (array_key_exists($name, $this->__data)) {
            return $this->__data[$name];
        } else {
            throw new \Exception("Field $name does not exist in the local data set.", -10003);
        }
    }
    
    protected function prepare($sql, $vars=[]) {
        return DB::$pdo->prepare($this->replaceVars($sql, $vars));
    }
    
    protected function initDataStructure($reinit=false) {
        if (!isset(self::$__fields[get_class($this)]) || $reinit) {
            $this->fetchDataStructure();
        }
        
        $this->__fields_list = implode(', ', $this->getFields());
        $this->__values_list = ':'. implode(', :', $this->getFields());
        
        $pairs = [];
        foreach ($this->getFields() as $field) {
            $pairs[] = "$field = :$field";
        }
        
        $this->__field_value_pairs = implode(", ", $pairs);

        if (count($this->__data) === 0) {
            $this->initData();
            $this->__is_new = true;
        } else {
            $this->__is_new = false;
        }
    }
    
    protected function fetchDataStructure() {
        $sth = $this->prepare(static::SQL_FETCH_COLUMNS);
        $sth->execute();
        
        while ($row = $sth->fetch(\PDO::FETCH_OBJ)) {
            self::$__fields[get_class($this)][] = $row->Field;
            self::$__labels[get_class($this)][$row->Field] = empty($row->Comment) ? $row->Field : $row->Comment;
            if ($row->Key == 'PRI') {
                self::$__pri[get_class($this)] = $row->Field;
            }
            if (strpos($row->Extra, 'auto_increment') !== false) {
                self::$__autoincrement[get_class($this)] = $row->Field;
            }
        }
    }

    protected function initData() {
        foreach (self::$__fields[get_class($this)] as $field) {
            $this->__data[$field] = null;
        }
    }

    protected function checkSetField($name) {
        if ( $name == $this->getPrimaryKey() && !$this->__is_new ) {
            throw new \Exception('Can not change the primary key for stored data.');
        }
    }
    
    protected function replaceVars($string, $vars) {
    
        $default_vars = [
            'DATABASE' => DB::$database,
            'TABLE_NAME' => DB::$prefix. $this->__table_name,
            'FIELDS_LIST' => $this->__fields_list,
            'VALUES_LIST' => $this->__values_list,
            'FIELD_VALUE_PAIRS' => $this->__field_value_pairs
        ];
        
        $full_vars = array_replace($default_vars, $vars);

        $result = $string;
        foreach ($full_vars as $key => $value) {
            $result = str_replace("%$key%", $value, $result);
        }
        
        return $result;
    }
    
    protected function _test_data() {
        return [
            'replaceVars' => [
                ['testing %DATABASE% name replacing', 'testing test name replacing'],
                ['testing %TABLE_NAME% replacing', 'testing t_test_table2 replacing'],
                ['testing both %DATABASE%.%TABLE_NAME%', 'testing both test.t_test_table2'],
                ['testing %WRONG_VAR%', 'testing %WRONG_VAR%'],
                ['testing none', 'testing none'],
            ],
            'fetch' => '_test_skip_',
            'prepare' => '_test_skip_',
            'checkDataStructure' => '_test_skip_',
        ];
    }

    const SQL_SELECT = <<<END
            SELECT %FIELDS_LIST% FROM %TABLE_NAME%
            WHERE %WHERE%
            END;
    
    const SQL_INSERT = <<<END
            INSERT INTO %TABLE_NAME%
            (%FIELDS_LIST%) VALUES (%VALUES_LIST%)
            END;

    const SQL_UPDATE = <<<END
            UPDATE %TABLE_NAME%
            SET %FIELD_VALUE_PAIRS%
            WHERE %WHERE%
            END;

    const SQL_FETCH_COLUMNS = <<<END
            SHOW FULL FIELDS FROM %TABLE_NAME%;
            END;
    
    const SQL_FETCH_TABLE_VERSION = <<<END
            SELECT table_comment FROM information_schema.tables 
            WHERE table_schema = '%DATABASE%' AND TABLE_NAME = '%TABLE_NAME%';
            END;

}

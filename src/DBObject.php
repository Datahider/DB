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
     const SQL_CREATE_TABLE = <<<END
            CREATE TABLE IF NOT EXISTS %TABLE_NAME% (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                data varchar(256) NOT NULL DEFAULT '',
                PRIMARY KEY (id)
            ) COMMENT = 'v1.0.0'  // <-- это исходная версия таблицы (хранится в комментарии)
            END;
    

     const SQL_UPGRADE_FROM_1_0_0 = <<<END
            ALTER TABLE %TABLE_NAME% COMMENT = 'v1.0.9',  // <-- не забудьте обновить версию таблицы
            ADD COLUMN more_data TEXT;
            END;
    
     */

    const INIT = 'init';
    
    protected $__commit;

    protected $__data = [];
    protected $__is_new = true;
    protected $__fields_modified = [];
    protected $__events_active = [];
    protected $__immutable = false;
    protected $__unuseable = false;

    protected static $__fields = [];
    protected static $__labels = [];
    protected static $__pri = [];
    protected static $__autoincrement = [];
    protected static $__data_struct_checked = [];


    public function __construct($where = null, $params = [], $create=false) {

        static::initDataStructure();
        $this->initData();
        
        if ($where !== null) {
            if (!$this->fetch($where, $params) && !$create) {
                throw new \Exception('Not found', -10002);
            }
        }
    }
    
    public function fetch($where=null, $params = []) {
        
        $this->checkUnuseable();
        
        if ($where === null) {
            $where = 1;
        }
        
        if (is_scalar($params)) {
            $params = [$params];
        }
        
        $sth = $this->prepare(static::SQL_SELECT, [ 'WHERE' => $where ]);
        
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
        $this->clearModifiedFeilds();
        return true;
    }
    
    public function write($comment='', $data=null) {

        $this->checkUnuseable();
        
        if ($this->isNew()) {
            $this->insert($comment, $data);
        } else {
            $this->update($comment, $data);
        }
    }
    
    protected function insert($comment, $data) {
        $sth = $this->prepare(static::SQL_INSERT);
        $this->beforeInsert($comment, $data);
        if (DB::$pdo->inTransaction()) {
            $this->__commit = false;
        } else {
            $this->__commit = true;
            DB::$pdo->beginTransaction();
        }
        try {
            $sth->execute($this->__data);
            if ($this->getAutoIncrement()) {
                $this->__data[$this->getAutoIncrement()] = DB::$pdo->lastInsertId();
            }
            $this->__is_new = false;
                $this->intranInsert($comment, $data);
            if ($this->__commit) {
                DB::$pdo->commit();
            }
        } catch (\Exception $e) {
            if ($this->__commit) {
                DB::$pdo->rollBack();
                $this->__is_new = true;
            }
            throw $e;
        }
        $this->afterInsert($comment, $data);
    }
    protected function update($comment, $data) {
        $sth = $this->prepare(static::SQL_UPDATE, [ 'WHERE' => $this->getPrimaryKey(). ' = :'. $this->getPrimaryKey()]);
        $this->beforeUpdate($comment, $data);
        if (DB::$pdo->inTransaction()) {
            $this->__commit = false;
        } else {
            $this->__commit = true;
            DB::$pdo->beginTransaction();
        }
        try {
            $sth->execute($this->__data);
            $this->intranUpdate($comment, $data);
            if ($this->__commit) {
                DB::$pdo->commit();
            }
        } catch (\Exception $e) {
            if ($this->__commit) {
                DB::$pdo->rollBack();
                $this->__is_new = true;
            }
            throw $e;
        }
        $this->afterUpdate($comment, $data);
    }
    
    public function delete($comment='', $data=null) {
        $this->checkUnuseable();
        $sth = $this->prepare(static::SQL_DELETE, [ 'WHERE' => $this->getPrimaryKey(). ' = ?']);
        $this->beforeDelete($comment, $data);
        if (DB::$pdo->inTransaction()) {
            $this->__commit = false;
        } else {
            DB::$pdo->beginTransaction();
            $this->__commit = true;
        }
        $sth->execute([$this->__data[$this->getPrimaryKey()]]);
        $this->intranDelete($comment, $data);
        if ($this->__commit) {
            DB::$pdo->commit();
        }
        $this->__unuseable = true;
        $this->afterDelete($comment, $data);
    }
    
    public function isNew() {
        $this->checkUnuseable();
        return $this->__is_new;
    }
    
    public function isModified() {
        $this->checkUnuseable();
        return count($this->__fields_modified) > 0;
    }
    
    public function checkUnuseable() {
        if ( $this->__unuseable ) {
            throw new \Exception("The object is in unuseable state (deleted?)", -10013);
        }
    }

    public function asString($fields=3) {
        $this->checkUnuseable();
        $class = DB::classShortName($this);
        $result = "$class: ";
        
        foreach ($this->__data as $key => $value) {
            $result .= "\n\t$key = $value";
            $fields--;
            if (!$fields) { break; }
        }
        return $result;
    }
    
    public function asArray() {
        return $this->__data;
    }

    static public function getFields() {
        if (!isset(static::$__fields[static::class])) {
            static::fetchDataStructure();
        }
        return static::$__fields[static::class];
    }
    
    static public function getAutoIncrement() {
        if (!isset(static::$__fields[static::class])) {
            static::fetchDataStructure();
        }
        return isset(self::$__autoincrement[static::class]) ? self::$__autoincrement[static::class] : null;
    }

    static public function getPrimaryKey() {
        if (!isset(static::$__fields[static::class])) {
            static::fetchDataStructure();
        }
        return isset(static::$__pri[static::class]) ? static::$__pri[static::class] : null;
    }

    public function getLabel($field_name) {
        $this->checkUnuseable();
        if (array_key_exists($field_name, self::$__labels[get_class($this)])) {
            return self::$__labels[get_class($this)][$field_name];
        } else {
            throw new \Exception('Unknown field: '. $field_name, -10003);
        }
    }
    
    public function __set($name, $value) {
        $this->checkUnuseable();
        if ($this->__immutable) {
            throw new \Exception('The object is in immutable state.', -10013);
        }
        if (array_key_exists($name, $this->__data)) {
            $this->checkSetField($name);
            if ($this->__data[$name] !== $value) {
                $this->beforeModify($name, $value);
                $this->__data[$name] = $value;
                $this->afterModify($name, $value);
            }
        } else {
            throw new \Exception("Field $name does not exist in the local data set.", -10003);
        }
    }
    
    public function __get($name) {
        $this->checkUnuseable();
        if (array_key_exists($name, $this->__data)) {
            return $this->__data[$name];
        } else {
            throw new \Exception("Field $name does not exist in the local data set.", -10003);
        }
    }
    
    static protected function prepare($sql, $vars=[]) {
        if ($vars === static::INIT) {
            return DB::$pdo->prepare(static::replaceVarsInit($sql));
        } 
        return DB::$pdo->prepare(static::replaceVars($sql, $vars));
    }
    
    static protected function createAlterTable() {
        $class = static::class;
        if (!defined("$class::TABLE_NAME")) {
            throw new \Exception("Constant $class::TABLE_NAME does not defined.", -10007);
        }
        if (!defined("$class::SQL_CREATE_TABLE")) {
            throw new \Exception("Constant $class::SQL_CREATE_TABLE does not defined.", -10007);
        }
        
        $sth_get_version = static::prepare(static::SQL_FETCH_TABLE_VERSION, static::INIT);
        $sth_get_version->setFetchMode(\PDO::FETCH_COLUMN, 0);
        $sth_get_version->execute();
        $version = $sth_get_version->fetch();

        if ($version === false) { // таблица не найдена, создаём
            if (DB::$pdo->inTransaction()) {
                throw new \Exception("You can't create table in transaction.", -10013);
            }
            $sth_create = static::prepare(static::SQL_CREATE_TABLE, static::INIT);
            $sth_create->execute();
        }
        
        for($i=0; $i<100; $i++) {
            $sth_get_version->execute();
            $version = $sth_get_version->fetch();
            
            $const = str_replace(['v', '.'], '_', "$class::SQL_UPGRADE_FROM$version");
            if (defined($const)) {
                if (DB::$pdo->inTransaction()) {
                    throw new \Exception("You can't create table in transaction.", -10013);
                }
                $sth_upgrade = static::prepare(constant($const), static::INIT);
                $sth_upgrade->execute();
            } else {
                self::$__data_struct_checked[$class] = true;
                return true;
            }
        }
        
        throw new \Exception("Upgrade data structure iteration limit exceded.", -10009);

    }
    
    static public function initDataStructure($reinit=false) {
        if (empty(static::$__data_struct_checked[static::class]) || $reinit) {
            static::createAlterTable();
        }
        if (!isset(static::$__fields[static::class]) || $reinit) {
            static::fetchDataStructure();
        }
    }
    
    static protected function fetchDataStructure() {
        $sth = static::prepare(static::SQL_FETCH_COLUMNS, static::INIT);
        $sth->execute();
        
        static::$__fields[static::class] = [];
        while ($row = $sth->fetch(\PDO::FETCH_OBJ)) {
            static::$__fields[static::class][] = $row->Field;
            static::$__labels[static::class][$row->Field] = empty($row->Comment) ? $row->Field : $row->Comment;
            if ($row->Key == 'PRI') {
                self::$__pri[static::class] = $row->Field;
            }
            if (strpos($row->Extra, 'auto_increment') !== false) {
                self::$__autoincrement[static::class] = $row->Field;
            }
        }
    }

    protected function initData() {
        foreach (static::$__fields[static::class] as $field) {
            $this->__data[$field] = null;
        }
    }

    protected function checkSetField($name) {
        if ( $name == $this->getPrimaryKey() && !$this->__is_new ) {
            throw new \Exception('Can not change the primary key for stored data.', -10003);
        }
    }
    
    static protected function replaceVarsInit($string) {

        $result1 = str_replace("%DATABASE%", DB::$database, $string);
        $result2 = str_replace("%TABLE_NAME%", DB::$prefix. static::TABLE_NAME, $result1);

        return $result2;
    }
    
    static protected function replaceVars($string, $vars=[]) {
    
        $pairs = [];
        foreach (static::getFields() as $field) {
            $pairs[] = "$field = :$field";
        }
        $field_value_pairs = implode(", ", $pairs);

        $default_vars = [
            'DATABASE' => DB::$database,
            'TABLE_NAME' => DB::$prefix. static::TABLE_NAME,
            'FIELDS_LIST' => implode(', ', static::getFields()),
            'VALUES_LIST' => ':'. implode(', :', static::getFields()),
            'FIELD_VALUE_PAIRS' => $field_value_pairs
        ];

        $full_vars = array_replace($default_vars, $vars);

        $result = $string;
        foreach ($full_vars as $key => $value) {
            if ($value === null) {
                $value = '';
            }
            $result = str_replace("%$key%", $value, $result);
        }
        
        return $result;
    }
    
    protected function beforeInsert($comment, $data) {
        $this->eventSetActive(DBEvent::BEFORE_INSERT);
        DB::notify(new DBEvent(DBEvent::BEFORE_INSERT, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::BEFORE_INSERT);
        $this->__immutable = true;
    }
    protected function intranInsert($comment, $data) {
        $this->eventSetActive(DBEvent::INTRAN_INSERT);
        DB::notify(new DBEvent(DBEvent::INTRAN_INSERT, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::INTRAN_INSERT);
    }
    protected function afterInsert($comment, $data) {
        $this->eventSetActive(DBEvent::AFTER_INSERT);
        DB::notify(new DBEvent(DBEvent::AFTER_INSERT, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::AFTER_INSERT);
        $this->clearModifiedFeilds();
        $this->__immutable = false;
    }
    
    protected function beforeUpdate($comment, $data) {
        $this->eventSetActive(DBEvent::BEFORE_UPDATE);
        DB::notify(new DBEvent(DBEvent::BEFORE_UPDATE, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::BEFORE_UPDATE);
        $this->__immutable = true;
    }
    protected function intranUpdate($comment, $data) {
        $this->eventSetActive(DBEvent::INTRAN_UPDATE);
        DB::notify(new DBEvent(DBEvent::INTRAN_UPDATE, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::INTRAN_UPDATE);
    }
    protected function afterUpdate($comment, $data) {
        $this->eventSetActive(DBEvent::AFTER_UPDATE);
        DB::notify(new DBEvent(DBEvent::AFTER_UPDATE, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::AFTER_UPDATE);
        $this->clearModifiedFeilds();
        $this->__immutable = false;
    }
    
    protected function beforeDelete($comment, $data) {
        $this->eventSetActive(DBEvent::BEFORE_DELETE);
        DB::notify(new DBEvent(DBEvent::BEFORE_DELETE, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::BEFORE_DELETE);
        $this->__immutable = true;
    }
    protected function intranDelete($comment, $data) {
        $this->eventSetActive(DBEvent::INTRAN_DELETE);
        DB::notify(new DBEvent(DBEvent::INTRAN_DELETE, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::INTRAN_DELETE);
    }
    protected function afterDelete($comment, $data) {
        $this->eventSetActive(DBEvent::AFTER_DELETE);
        DB::notify(new DBEvent(DBEvent::AFTER_DELETE, $this, array_keys($this->__fields_modified), $data, $comment));
        $this->eventUnsetActive(DBEvent::AFTER_DELETE);
        $this->__immutable = false;
        $this->__data[$this->getAutoIncrement()] = null;
        $this->__is_new = true;
    }
    
    protected function beforeModify($name, $value) {
        $this->eventSetActive(DBEvent::BEFORE_MODIFY);
        $this->__immutable = true;
        DB::notify(new DBEvent(DBEvent::BEFORE_MODIFY, $this, $name, $value));
        $this->eventUnsetActive(DBEvent::BEFORE_MODIFY);
    }
    protected function afterModify($name, $value) {
        $this->eventSetActive(DBEvent::AFTER_MODIFY);
        $this->addModifiedField($name);
        DB::notify(new DBEvent(DBEvent::AFTER_MODIFY, $this, $name, $value));
        $this->__immutable = false;
        $this->eventUnsetActive(DBEvent::AFTER_MODIFY);
    }
    
    protected function addModifiedField($name) {
        if (isset($this->__fields_modified[$name])) {
            $this->__fields_modified[$name]++;
        } else {
            $this->__fields_modified[$name] = 1;
        }
    }
    protected function clearModifiedFeilds() {
        $this->__fields_modified = [];
    }

    protected function eventSetActive(int $event_type) {
        if (isset($this->__events_active[$event_type])) {
            throw new \Exception("Event ". DBEvent::typeName($event_type). " is already active. Possible loop.", -10014);
        }
        $this->__events_active[$event_type] = true;
    }
    protected function eventUnsetActive(int $event_type) {
        if (!isset($this->__events_active[$event_type])) {
            throw new \Exception("Event ". DBEvent::typeName($event_type). " was not active.", -10003);
        } 
        unset($this->__events_active[$event_type]);
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

    const SQL_DELETE = <<<END
            DELETE FROM %TABLE_NAME%
            WHERE %WHERE%
            END;

    const SQL_FETCH_COLUMNS = <<<END
            SHOW FULL FIELDS FROM %TABLE_NAME%;
            END;
    
    const SQL_FETCH_TABLE_VERSION = <<<END
            SELECT table_comment FROM information_schema.tables 
            WHERE table_schema = '%DATABASE%' AND TABLE_NAME = '%TABLE_NAME%';
            END;
    
    protected function _test_data() {
        return [
            'fetch' => '_test_skip_',
            'write' => '_test_skip_',
            'insert' => '_test_skip_',
            'update' => '_test_skip_',
            'isNew' => '_test_skip_',
            'isModified' => '_test_skip_',
            'asString' => '_test_skip_',
            'asArray' => '_test_skip_',
            'getFields' => '_test_skip_',
            'getAutoIncrement' => '_test_skip_',
            'getPrimaryKey' => '_test_skip_',
            'getLabel' => '_test_skip_',
            '__set' => '_test_skip_',
            '__get' => '_test_skip_',
            'prepare' => '_test_skip_',
            'createAlterTable' => '_test_skip_',
            'initDataStructure' => '_test_skip_',
            'fetchDataStructure' => '_test_skip_',
            'initData' => '_test_skip_',
            'checkSetField' => '_test_skip_',
            'replaceVars' => '_test_skip_',
            'replaceVarsInit' => '_test_skip_',
            'beforeInsert' => '_test_skip_',
            'intranInsert' => '_test_skip_',
            'afterInsert' => '_test_skip_',
            'beforeUpdate' => '_test_skip_',
            'intranUpdate' => '_test_skip_',
            'afterUpdate' => '_test_skip_',
            'beforeDelete' => '_test_skip_',
            'intranDelete' => '_test_skip_',
            'afterDelete' => '_test_skip_',
            'beforeModify' => '_test_skip_',
            'afterModify' => '_test_skip_',
            'addModifiedField' => '_test_skip_',
            'clearModifiedFeilds' => '_test_skip_',
            'eventSetActive' => '_test_skip_',
            'eventUnsetActive' => '_test_skip_',
            'checkUnuseable' => '_test_skip_',
            'delete' => '_test_skip_',
        ];
    }
}

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
abstract class DBObject extends DBBaseClass {
    
    /* 
     * В дочерних классах определите константу METADATA
     * для автоматического создания и обновления структуры таблицы в которой хранятся
     * данные объектов. Например
     *
    
    const METADATA = [
        'id'   => 'bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT "Идентификатор"',
        'data' => 'varchar(256) NOT NULL DEFAULT COMMENT "Данные"',
        'PRIMARY KEY' => 'id',
        'INDEX id_data' => ['id', 'data']
    ];

     */

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
    protected static $__field_types = [];
    protected static $__data_struct_checked = [];


    public function __construct($data=[], $create=false) {

        static::initDataStructure();
        $this->initData();
        
        if (count($data) > 0) {
            if (!$this->fetch($data) && !$create) {
                throw new \Exception('Not found', -10002);
            } elseif ($this->isNew()) {
                foreach ($data as $key => $value) {
                    $this->$key = $value;
                }
            }
        }
    }
    
    protected function where(array $data) {
        $result_array = [];
        
        foreach (array_keys($data) as $key) {
            if (is_null($data[$key])) {
                $result_array[] = "$key IS :$key";
            } else {
                $result_array[] = "$key = :$key";
            }
        }
        return implode(" AND ", $result_array);
    }
    
    public function fetch(array $filter=[]) {
        
        if (count($filter) == 0) {
            $primary_key = $this->getPrimaryKey();
            $filter[$primary_key] = $this->$primary_key; 
        }
        
        $this->checkUnuseable();
        
        $sth = DB::prepare('SELECT * FROM '. $this->tableName(). ' WHERE '. $this->where($filter));
        $sth->execute($this->filterTypes($filter));
        
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
        $sth = DB::prepare('INSERT INTO '. static::tableName(). ' ('. implode(', ', static::getFields()). ') VALUES (:'. implode(', :', static::getFields()). ')');
        $this->beforeInsert($comment, $data);
        if (DB::inTransaction()) {
            $commit = false;
        } else {
            $commit = true;
            DB::beginTransaction();
        }
        try {
            $sth->execute($this->__data);
            if ($this->getAutoIncrement()) {
                $this->__data[$this->getAutoIncrement()] = DB::PDO()->lastInsertId();
            }
            $this->__is_new = false;
                $this->intranInsert($comment, $data);
            if ($commit) {
                DB::commit();
            }
        } catch (\Exception $e) {
            if ($commit) {
                DB::rollBack();
                $this->__is_new = true;
            }
            throw $e;
        }
        $this->afterInsert($comment, $data);
    }
    protected function update($comment, $data) {
        $sth = DB::prepare('UPDATE '. static::tableName(). ' SET '. static::getFieldValuePairs(). ' WHERE '. $this->getPrimaryKey(). ' = :'. $this->getPrimaryKey());
            
        $this->beforeUpdate($comment, $data);
        if (DB::inTransaction()) {
            $commit = false;
        } else {
            $commit = true;
            DB::beginTransaction();
        }
        try {
            $sth->execute($this->__data);
            $this->intranUpdate($comment, $data);
            if ($commit) {
                DB::commit();
            }
        } catch (\Exception $e) {
            if ($commit) {
                DB::rollBack();
                $this->__is_new = true;
            }
            throw $e;
        }
        $this->afterUpdate($comment, $data);
    }
    
    public function delete($comment='', $data=null) {
        $this->checkUnuseable();
        $sth = DB::prepare('DELETE FROM '. static::tableName(). ' WHERE '. $this->getPrimaryKey(). ' = ?');
        $this->beforeDelete($comment, $data);
        if (DB::inTransaction()) {
            $commit = false;
        } else {
            DB::beginTransaction();
            $commit = true;
        }
        $sth->execute([$this->__data[$this->getPrimaryKey()]]);
        $this->intranDelete($comment, $data);
        if ($commit) {
            DB::commit();
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

    public function asArray() {

        foreach ( self::$__fields[static::class] as $field_name ) {
            $result[$field_name] = $this->$field_name;
        }
        return $result;
    }
    
    static public function getFields() {
        if (!isset(static::$__fields[static::class])) {
            static::fetchDataStructure();
        }
        return static::$__fields[static::class];
    }
    
    static public function getFieldValuePairs() {
        $pairs = [];
        foreach (static::getFields() as $field) {
            $pairs[] = "$field = :$field";
        }
        return implode(", ", $pairs);
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
    
    protected function formatDateTime($value) {
        if (is_a($value, '\DateTime') || is_a($value, '\DateTimeImmutable')) {
            return $value->format(DB::DATE_FORMAT);
        }
        
        throw new \Exception("The value must be of type DateTime or DateTimeImmutable.", -10003);
    }
    
    public function __set($name, $value) {
        $this->checkUnuseable();
        if ($this->__immutable) {
            throw new \Exception('The object is in immutable state.', -10013);
        }
        if (array_key_exists($name, $this->__data)) {
            $this->checkSetField($name);
            
            if ($value === null) {
                $new_value = null;
            } elseif ( self::$__field_types[static::class][$name] == 'datetime' ) {
                $new_value = $this->formatDateTime($value);
            } elseif ( self::$__field_types[static::class][$name] == 'bool' ) {
                $new_value = (int)((bool)$value);
            } else {
                $new_value = $value;
            }
            
            if ($this->__data[$name] !== $new_value) {
                $this->beforeModify($name, $value);
                $this->__data[$name] = $new_value;
                $this->afterModify($name, $value);
            }
        } else {
            throw new \Exception("Field $name does not exist in the local data set.", -10003);
        }
    }
    
    public function __get($name) {
        $this->checkUnuseable();
        if (array_key_exists($name, $this->__data)) {
            if ($this->__data[$name] === null) {
                return null;
            } elseif ( self::$__field_types[static::class][$name] == 'datetime' ) {
                return $this->toDateTime($this->__data[$name]);
            } elseif ( self::$__field_types[static::class][$name] == 'bool' ) {
                return (bool) $this->__data[$name];
            } else {
                return $this->__data[$name];
            }
        } else {
            throw new \Exception("Field $name does not exist in the local data set.", -10003);
        }
    }
    
    protected function toDateTime($value) {
        if ($value === null) {
            return null;
        }
        return new \DateTimeImmutable($value);
    }
    
    static public function tableExists() : bool {
        $table = static::tableName();
        $sth = DB::query("SHOW TABLE STATUS WHERE NAME = '$table'");
        if ($sth->fetch() === false) {
            return false;
        }
        return true;
    }
    
    static public function initDataStructure($reinit=false) {
        if (empty(static::$__data_struct_checked[static::class]) || $reinit) {
            if (DB::inTransaction()) {
                throw new \Exception("You can't create/alter table in transaction.", -10013);
            }
            if (!static::tableExists()) {
                static::createTable();
            } else {
                static::alterFields();
                static::alterIndexes();
            }
            static::$__data_struct_checked[static::class] = true;
        }
        if (!isset(static::$__fields[static::class]) || $reinit) {
            static::fetchDataStructure();
        }
    }
    
    static protected function isIndex($key) {
        if (preg_match("/^PRIMARY KEY$/i", $key) || preg_match("/^UNIQUE INDEX /i", $key) || preg_match("/^INDEX /i", $key)) {
            return true;
        }
        return false;
    }

    static protected function createTable() {
        $table = static::tableName();
        $sql_create_table = "CREATE TABLE $table (";
        $coma = '';
        foreach (static::METADATA as $name => $description) {
            if (static::isIndex($name)) {
                if (is_array($description)) {
                    $description = implode(", ", $description);
                }
                $description = "($description)";
            }
            $sql_create_table .= "$coma\n    $name $description";
            $coma = ',';
        }
        $sql_create_table .= "\n)";
        
        DB::exec($sql_create_table);
    }

    static protected function fetchFields() {
        $sth = DB::query('SHOW FULL FIELDS FROM '. static::tableName(), \PDO::FETCH_OBJ);
        return $sth->fetchAll();
    }
    
    static protected function fetchIndexes() {
        $sth = DB::query('SHOW INDEXES FROM '. static::tableName(), \PDO::FETCH_OBJ);
        return $sth->fetchAll();
    }


    static protected function metadataFields() {
        $result = [];
        foreach (static::METADATA as $key => $value) {
            if (!static::isIndex($key)) {
                $result[] = $key;
            }
        }
        return $result;
    }
    
    static protected function metadataIndexes() {
        $result = [];
        foreach (static::METADATA as $key => $value) {
            if (static::isIndex($key)) {
                $result[] = $key;
            }
        }
        return $result;
    }

    static protected function alterFields() {
        
        $fields = static::metadataFields();
        $sql_alter_table = 'ALTER TABLE '. static::tableName();
        $coma = '';
        
        foreach (static::fetchFields() as $row) {
            $index = array_search($row->Field, $fields);
            if ( $index === false) {
                $sql_alter_table .= "$coma\n    DROP COLUMN $row->Field";  
            } else {
                $sql_alter_table .= "$coma\n    CHANGE COLUMN $row->Field $row->Field ". static::METADATA[$row->Field];
            }
            $coma = ',';
            unset($fields[$index]);
        }
        
        foreach ($fields as $key) {
            $sql_alter_table .= ",\n    ADD COLUMN $key ". static::METADATA[$key];
        }
        DB::exec($sql_alter_table);
    }

    static protected function scalarToArray($param) : array {
        if (is_scalar($param)) {
            $param = [$param];
        } 
        return $param;
    }
    
    static protected function alterIndexes() {
        
        $indexes = static::metadataIndexes();
        $sql_alter_table = 'ALTER TABLE '. static::tableName();
        $coma = '';
        $existing = [];
        
        foreach (static::fetchIndexes() as $row) {
            if ($row->Key_name == 'PRIMARY') {
                $key = 'PRIMARY KEY';
            } elseif ($row->Non_unique === 0) {
                $key = 'UNIQUE INDEX '. $row->Key_name;
            } else {
                $key = 'INDEX '. $row->Key_name;
            }
            $existing[$key][] = $row->Column_name;
        }
        
        foreach ($existing as $key => $value) {
            $found = array_search($key, $indexes);
            if ($found === false) {
                $sql_alter_table .= "$coma\n DROP $key";
                $coma = ',';
            } elseif (static::scalarToArray(static::METADATA[$indexes[$found]]) != $existing[$key]) {
                $sql_alter_table .= "$coma\n DROP $key";
                $coma = ',';
                $sql_alter_table .= "$coma\n ADD $key (". implode(", ", static::scalarToArray(static::METADATA[$indexes[$found]])).")";
            }
        }
        
        if ($coma == ',') {
            DB::exec($sql_alter_table);
        }
        
    }
        
    static protected function fetchDataStructure() {
        
        $fields = static::fetchFields();
        
        self::$__fields[static::class] = [];
        self::$__field_types[static::class] = [];
        
        foreach ($fields as $row) {
            static::$__fields[static::class][] = $row->Field;
            static::$__labels[static::class][$row->Field] = empty($row->Comment) ? $row->Field : $row->Comment;
            if ($row->Key == 'PRI') {
                self::$__pri[static::class] = $row->Field;
            }
            if (strpos($row->Extra, 'auto_increment') !== false) {
                self::$__autoincrement[static::class] = $row->Field;
            }
            if ($row->Type == 'datetime') {
                self::$__field_types[static::class][$row->Field] = 'datetime';
            } elseif ($row->Type == 'tinyint(1)') {
                self::$__field_types[static::class][$row->Field] = 'bool';
            } else {
                self::$__field_types[static::class][$row->Field] = 'general';
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
    
    static public function tableName() {
        $m = [];
        preg_match("/\w+$/", static::class, $m);
        return DB::$prefix. $m[0];
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

}

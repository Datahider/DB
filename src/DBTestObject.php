<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBChildObjectExample
 *
 * @author drweb
 */
class DBTestObject extends DBObject {
    
    /*
     * Define table name
     */
    const TABLE_NAME = 'test_objects';
    
    /*
     *  Define CREATE_TABLE constant
     */
    const SQL_CREATE_TABLE = <<<END
            CREATE TABLE IF NOT EXISTS %TABLE_NAME% (
                id bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
                name varchar(50) COMMENT 'Имя',
                PRIMARY KEY (id)
            ) COMMENT = 'v1.0.0'
            END;
    
    /*
     * If you will need to upgrade data structure then define UPGRADE_FROM_* constant 
     */
    const SQL_UPGRADE_FROM_1_0_0 = <<<END
            ALTER TABLE %TABLE_NAME% COMMENT = 'v1.0.7',
            ADD description varchar(1024) COMMENT 'Описание'
            END;
    
    const SQL_UPGRADE_FROM_1_0_7 = <<<END
            ALTER TABLE %TABLE_NAME% COMMENT = 'v1.0.8',
            ADD some_date DATETIME COMMENT 'Какая-то дата'
            END;
    
    const SQL_UPGRADE_FROM_1_0_8 = <<<END
            ALTER TABLE %TABLE_NAME% COMMENT = 'v1.0.9',
            ADD bool_field BOOL NOT NULL COMMENT 'Булево поле',
            ADD another_bool BOOL COMMENT 'Другое булево'
            END;
    
    protected function _test_modifyAndStore() {
        $this->name = 'test_name';
        $this->bool_field = false;
        echo '.';
        if ($this->__data['name'] != 'test_name') {
            throw new \Exception('__set seems not to be working.', -10002);
        }
        echo '.';
        if (!$this->isModified()) {
            throw new \Exception('The object has to be modified at this point.', -10002);
        }
        echo '.';
        $this->write(); // as it's new object tests $this->insert() also
        echo '.';
        if ($this->isNew()) {
            throw new \Exception('The object has not to be "new" at this point.', -10002);
        }
        echo '.';
        if ($this->isModified()) {
            throw new \Exception('The object has not to be modified at this point.', -10002);
        }
        echo '.';
    }
    
    protected function _test_modifyAndFetch($step) {
        switch ($step) {
            case 1:
                $this->name = 'test_name_1';
                $this->__data['name'] = 'test_name_2';
                return $this->name;
            case 2:
                $this->fetch();
                return $this->name;
            case 3:
                return $this->isModified();
            case 4: 
                return $this->isNew();
            case 5:
                $this->name = 'test_name_1';
                return $this->isModified();
            case 6:
                $this->write(); // As it is not new also tests update
                return $this->isModified();
            case 7:
                $this->id = 10; // Awaiting exception as it is autoincrement field
                return true;
            case 8:
                $this->some_date = '2023-10-18 21:18:10'; // Awaiting exception as the value is not a datetime object
                return true; 
            case 9:
                $this->some_date = new \DateTimeImmutable();
                $this->write();
                return true; 
            case 10:
                if (!is_string($this->__data['some_date'])) {
                    throw new Exception("Type exception. Awaiting internal some_date to be a string", -10002);
                }
                return $this->some_date;
            case 11:
                $this->bool_field = true;
                $this->write();
                return [$this->bool_field === true ? 'TRUE' : 'NOT TRUE', $this->__data['bool_field']];
            case 12:
                $this->bool_field = false;
                $this->another_bool = false;
                $this->write();
                return [$this->bool_field === false ? 'FALSE' : 'NOT FALSE', $this->__data['bool_field']];
            case 13:
                $this->another_bool = null;
                $this->write();
                return 'Ok';
            case 14:
                if ($this->another_bool !== null) {
                    throw new \Exception("Awaiting to be NULL");
                }
                return 'Ok';
            default:
                throw new \Exception("Unknown test step", -10003);
        }
    }
    
    protected function _test_asArray() {
        echo '.'; $array = $this->asArray();
        echo '.'; if ($array['id'] != $this->id) {
            throw new \Exception("Incorrect value", -10002);
        }
        echo '.'; $array['id'] = 'test_value';
        echo '.'; if ($array['id'] == $this->id) {
            throw new \Exception("Incorrect value", -10002);
        }
        echo '.'; if (is_bool($array['bool_field']) != true) {
            throw new \Exception("Incorrect value type", -10002);
        }
        echo '.'; if (is_a($array['some_date'], '\DateTimeImmutable') != true) {
            throw new \Exception("Incorrect value type", -10002);
        }
    }
    
    protected function _test_data() {
        return [
            'initDataStructure' => '_test_skip_',   // used in __constructor
            'createAlterTable' => '_test_skip_',    // used in __constructor
            'fetchDataStructure' => '_test_skip_',  // used in __constructor
            'prepare' => '_test_skip_',             // used in __constructor
            'initData' => '_test_skip_',            // used in __constructor
            
            'replaceVarsInit' => [
                ['testing %DATABASE% name replacing', 'testing test name replacing'],
                ['testing %TABLE_NAME% replacing', 'testing t_test_objects replacing'],
                ['testing both %DATABASE%.%TABLE_NAME%', 'testing both test.t_test_objects'],
                ['testing %WRONG_VAR%', 'testing %WRONG_VAR%'],
                ['testing none', 'testing none'],
            ],
            'replaceVars' => [
                ['testing %DATABASE% name replacing', 'testing test name replacing'],
                ['testing %TABLE_NAME% replacing', 'testing t_test_objects replacing'],
                ['testing both %DATABASE%.%TABLE_NAME%', 'testing both test.t_test_objects'],
                ['testing %WRONG_VAR%', 'testing %WRONG_VAR%'],
                ['testing none', 'testing none'],
                ['testing additional %VAR%', ['VAR' => 'test'], 'testing additional test'],
            ],
            'getAutoIncrement' => [
                ['id']
            ],
            'getFields' => [
                [['id', 'name', 'description', 'some_date', 'bool_field', 'another_bool']]
            ],
            'getPrimaryKey' => [
                ['id']
            ],
            'isNew' => [
                [true]
            ],
            'isModified' => [
                [false]
            ],
            '_test_modifyAndStore' => [[null]],
            'write' => '_test_skip_',       // Tested in _test_modifyAndStore
            'insert' => '_test_skip_',      // Tested in _test_modifyAndStore
            '__set' => '_test_skip_',       // Tested in _test_modifyAndStore
            '_test_modifyAndFetch' => [
                [1, 'test_name_2'],
                [2, 'test_name'],
                [3, false],
                [4, false],
                [5, true],
                [6, false],
                [7, new \Exception('', -10003)],
                [8, new \Exception('', -10003)],
                [9, 1],
                [10, new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::IS_A, '\DateTimeImmutable')],
                [11, ['TRUE', 1]],
                [12, ['FALSE', 0]],
                [13, 'Ok'],
                [14, 'Ok'],
            ],
            'fetch' => '_test_skip_',           // Tested in _test_modifyAndFetch
            '__get' => '_test_skip_',           // Tested in _test_modifyAndFetch
            'update' => '_test_skip_',          // Tested in _test_modifyAndFetch
            'checkSetField' => '_test_skip_',   // Tested in _test_modifyAndFetch
            'getLabel' => [
                ['id', 'Идентификатор'],
                ['name', 'Имя'],
                ['description', 'Описание'],
                ['unexistant', new \Exception('', -10003)],
            ],
            'asString' => [
                [new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::PCRE, "/test_name_1/")],
                ['%id%, %name%', '2, test_name_1'],
                ['%CLASS%:template0', 'losthost\DB\DBTestObject:template0'],
            ],
            'asArray' => '_test_asArray',
            'asFormattedArray' => [
                [ new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::ELEM, 'bool_field', 'FALSE')],
            ],
            'fieldType' => [
                ['some_date', 'datetime'],
                ['bool_field', 'bool'],
                ['id', 'general']
            ],
            'defaultFormats' => [
                [['datetime' => 'Y-m-d H:i:s', 'bool' => ['FALSE', 'TRUE']]],
            ],
            'toDateTime' => [
                ['2023-10-01 23:00:10', new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::IS_A, 'DateTimeImmutable')],
            ],
            'checkUnuseable' => '_test_skip_',
            'delete' => '_test_skip_',
            
            'formatDateTime' => '_test_skip_',
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
        ];
    }
}

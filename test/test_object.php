<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB\test;
use losthost\DB\DBObject;
use losthost\DB\DB;

/**
 * Description of test_object
 *
 * @author drweb_000
 */
class test_object extends DBObject {
    
    const METADATA = [
        'id' => 'bigint(20) unsigned auto_increment COMMENT "Идентификатор"',
        'name' => 'varchar(50) COMMENT "Имя"',
        'description' => 'varchar(1024) COMMENT "Описание"',
        'some_date' => 'DATETIME COMMENT "Какая-то дата"',
        'bool_field' => 'BOOL NOT NULL COMMENT "Булево поле"',
        'another_bool' => 'BOOL COMMENT "Другое булево"',
        'PRIMARY KEY' => 'id',
        'UNIQUE INDEX name' => 'name',
        'INDEX some_date_bool_field' => ['some_date', 'bool_field']
    ];
    
    public static function tableName() {
        return DB::$prefix. 'objects';
    }
    
    protected function intranInsert($comment, $data) {
        parent::intranInsert($comment, $data);
        error_log($this->name);
        if ($this->name == 'tmp') {
            error_log('I am here');
            $this->__data['name'] = 'persistent';
            $this->update($comment, $data);
        }
    }
}

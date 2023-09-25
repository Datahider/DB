<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * A test object with no datetime or bool fields
 *
 * @author drweb
 */
class DBTestObject2 extends DBObject {
    
    const TABLE_NAME = 'test_objects_2';
    
    const SQL_CREATE_TABLE = <<<END
            CREATE TABLE IF NOT EXISTS %TABLE_NAME% (
                id bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
                name varchar(50) COMMENT 'Имя',
                PRIMARY KEY (id)
            ) COMMENT = 'v1.0.0'
            END;

}

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
class DBChildObjectExample extends DBObject {
    
    /*
     * Define table name
     */
    const TABLE_NAME = 'example_objects';
    
    /*
     *  Define CREATE_TABLE constant
     */
    const SQL_CREATE_TABLE = <<<END
            CREATE TABLE IF NOT EXISTS %TABLE_NAME% (
                id bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
                name varchar(50) COMMENT 'Имя',
                PRIMARY KEY (id)
            ) COMMENT = 'v1.0.0'    /* Don't forget to specify version as table comment */
            END;
    
    /*
     * If you will need to upgrade data structure then define UPGRADE_FROM_* constant 
     */
    const SQL_UPGRADE_FROM_1_0_0 = <<<END
            ALTER TABLE %TABLE_NAME% COMMENT = 'v1.0.7',  /* Don't forget to specify new version of table structure */
            ADD description varchar(1024) COMMENT 'Описание'
            END;
    
    
}

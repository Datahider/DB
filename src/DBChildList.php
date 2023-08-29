<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBChildList
 *
 * @author drweb_000
 */
class DBChildList extends DBList {

    const SQL_QUERY = <<<END
            SELECT 
                * 
            FROM
                [example_objects]
            WHERE
                description LIKE ?
            END;
    
    public function __construct($params = []) {
        parent::__construct($params);
    }
    
}

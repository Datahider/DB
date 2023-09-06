<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBValue
 *
 * @author drweb
 */
class DBValue extends \losthost\DB\DBView {
    
    public function __construct(string $sql, $params = []) {
        parent::__construct($sql, $params);
        $this->next();
    }

}

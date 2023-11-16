<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;
use losthost\DB\DBEvent;

/**
 * Description of DBListener
 *
 * @author drweb
 */
abstract class DBTracker {
    
    abstract public function track(DBEvent $event);
    
}

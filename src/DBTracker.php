<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBListener
 *
 * @author drweb
 */
abstract class DBTracker extends \losthost\SelfTestingSuite\SelfTestingClass {
    
    abstract public function track(DBEvent $event);
    
}

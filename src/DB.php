<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace pio\DB;

/**
 * Description of DB
 *
 * @author drweb
 */
class DB {
    public static $pdo;
    public static $prefix;
    public static $suffix;

        public static function connect($db_host, $db_user, $db_pass, $db_name, $db_prefix='', $db_encoding='utf8mb4') {
        
        DB::$pdo = new PDO("mysql:dbname=$db_name;host=$db_host", 
                $db_user, 
                $db_pass, 
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '$db_encoding'")
        );
        
        DB::$prefix = $db_prefix;
        
    }
}

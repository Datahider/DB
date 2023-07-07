<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DB
 *
 * @author drweb
 */
class DB {
    
    public static $pdo;
    public static $prefix;
    public static $database;
    protected static $namespace = '';
    
    public static function connect($db_host, $db_user, $db_pass, $db_name, $db_prefix='', $db_encoding='utf8mb4') {
        
        DB::$pdo = new \PDO("mysql:dbname=$db_name;host=$db_host", 
                $db_user, 
                $db_pass, 
                array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '$db_encoding'")
        );
        
        DB::$prefix = $db_prefix;
        DB::$database = $db_name;
        
    }
    
    public static function replaceVars($string, $table_name, $condition=1) {
    
        $result = str_replace(['%DATABASE%', '%TABLE_NAME%', '%CONDITION%'], [DB::$database, DB::$prefix. $table_name, $condition], $string);
        return $result;
        
    }

    public static function checkDataStructure($classes, $upgrade=false) {
        
        if (!is_array($classes)) {
            $classes  = explode(' ', $classes);
        }
        
        $result = false;
        foreach ($classes as $class) {
            $result |= self::checkClassDataStructure(self::classFullName($class), $upgrade);
        }
        
        return $result;
    }

    public static function setClassNamespace($namespace) {
        self::$namespace = $namespace;
    }
    
    static public function dropAllTables($sure=false, $absolutely=false) {
        if (!$sure) {
            throw new \Exception('You have to be sure to drop all tables');
        }

        $sth_tables = self::prepare(self::SQL_SHOW_TABLES);
        $sth_tables->execute();
        $sth_tables->setFetchMode(\PDO::FETCH_COLUMN, 0);

        while ($table = $sth_tables->fetch()) {
            if (strpos($table, self::$prefix) !== 0) {
                continue;
            }

            if (!$absolutely) {
                throw new \Exception("You have to be absolutely sure to drop table $table");
            }

            $sth_drop = self::prepare(str_replace("%TABLE_NAME%", $table, self::SQL_DROP_TABLE));
            $sth_drop->execute();
        }
    }
    
    protected static function checkClassDataStructure($class, $upgrade) {
        $class = self::classFullName($class);
        
        if (!defined("$class::SQL_CREATE_TABLE")) {
            throw new \Exception("const SQL_CREATE_TABLE is not defined for class $class.", -10007);
        }
        
        $sth_create = self::prepare($class::SQL_CREATE_TABLE, $class::TABLE_NAME);
        $sth_create->execute();
        
        return self::upgradeTable($class, $upgrade); 
    }
    
    protected static function upgradeTable($class, $upgrade) {

        $class = self::classFullName($class);
        
        $sth_get_version = self::prepare($class::SQL_FETCH_TABLE_VERSION, $class::TABLE_NAME);
        $sth_get_version->setFetchMode(\PDO::FETCH_COLUMN, 0);
        
        for($i=0; $i<100; $i++) {
            $sth_get_version->execute();
            $version = $sth_get_version->fetch();
            
            $const = str_replace(['v', '.'], '_', "$class::SQL_UPGRADE_FROM$version");
            if (defined($const) && $upgrade) {
                $sth_upgrade = self::prepare(constant($const), $class::TABLE_NAME);
                $sth_upgrade->execute();
            } elseif (defined($const)) {
                return true;
            } else {
                return false;
            }
        }
        
        throw new \Exception("Upgrade data structure iteration limit exceded.", -10009);
        
    }

    protected static function prepare($sql, $table_name='', $condition=1) {
        
        $sql = self::replaceVars($sql, $table_name, $condition);

        $sth = self::$pdo->prepare($sql);
        return $sth;
        
    }
    
    protected static function classFullName($class) {
        if (strpos($class, "\\") === false) {
            return self::$namespace. $class;
        } else {
            return $class;
        }
    }

    const SQL_SHOW_TABLES = "SHOW TABLES";
    const SQL_DROP_TABLE = "DROP TABLE %TABLE_NAME%";
    
}

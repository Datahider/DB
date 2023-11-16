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
    
    const DATE_FORMAT = 'Y-m-d H:i:s';
    
    public static string $prefix;
    public static string $database;
    public static string $language_code;
    
    protected static \PDO $pdo;

    protected static string $host;
    protected static string $user;
    protected static string $pass;
    protected static string $encoding;
    
    protected static bool $in_transaction;

    protected static $trackers = [
        DBEvent::ALL_EVENTS => [],
        DBEvent::BEFORE_MODIFY => [],
        DBEvent::BEFORE_INSERT => [],
        DBEvent::BEFORE_UPDATE => [],
        DBEvent::BEFORE_DELETE => [],
        DBEvent::INTRAN_INSERT => [],
        DBEvent::INTRAN_UPDATE => [],
        DBEvent::INTRAN_DELETE => [],
        DBEvent::AFTER_MODIFY => [],
        DBEvent::AFTER_INSERT => [],
        DBEvent::AFTER_UPDATE => [],
        DBEvent::AFTER_DELETE => [],
    ];

    public static function PDO() : false | \PDO {
        
        if (!isset(self::$pdo)) {
            return false;
        }
        
        $count = 240;
        $sleep = false;
        while (1) {
            try {
                self::$pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
                return self::$pdo;
            } catch (\PDOException $ex) {
                if (self::inTransaction()) {
                    self::$in_transaction = false;
                    throw new \Exception('SQL server connection lost while in transaction.', -10013);
                }
                if ($count <= 0) {
                    self::$in_transaction = false;
                    throw new \Exception('Retry limit reached while trying to reconnect to SQL server.', -10009);
                }
                self::reconnect();
                if ($sleep) {
                    sleep(5);
                } else {
                    $sleep = true;
                }
                $count--;
            }
        }
    }
    
    public static function beginTransaction() {
        if (self::inTransaction()) {
            throw new \Exception('Already in transaction');
        }
        self::PDO()->beginTransaction();
        self::$in_transaction = true;
    }
    
    public static function commit() {
        if (!self::inTransaction()) {
            throw new \Exception('Not in transaction');
        }
        self::PDO()->commit();
        self::$in_transaction = false;
    }
    
    public static function rollBack() {
        if (!self::inTransaction()) {
            throw new \Exception('Not in transaction');
        }
        self::PDO()->rollBack();
        self::$in_transaction = false;
    }

    public static function inTransaction() {
        return self::$in_transaction;
    }
    
    public static function addTracker(int|array $event_types, string|array $classes, string|DBTracker $tracker) {
        if (is_int($event_types)) {
            $event_types = [$event_types];
        }
        
        if (is_string($classes)) {
            $classes = [$classes];
        }
        
        foreach ($event_types as $type) {
            foreach ($classes as $class) {
                self::$trackers[$type][$class][] = $tracker;
            }
        }
    }
    
    public static function clearTrackers() {
        self::$trackers = [
            DBEvent::ALL_EVENTS => [],
            DBEvent::BEFORE_MODIFY => [],
            DBEvent::BEFORE_INSERT => [],
            DBEvent::BEFORE_UPDATE => [],
            DBEvent::BEFORE_DELETE => [],
            DBEvent::INTRAN_INSERT => [],
            DBEvent::INTRAN_UPDATE => [],
            DBEvent::INTRAN_DELETE => [],
            DBEvent::AFTER_MODIFY => [],
            DBEvent::AFTER_INSERT => [],
            DBEvent::AFTER_UPDATE => [],
            DBEvent::AFTER_DELETE => [],
        ];
        return self::$trackers;
    }
    
    public static function notify(DBEvent $event) {

        $result = DB::notifyArray(
                isset(self::$trackers[$event->type][get_class($event->object)]) 
                ? self::$trackers[$event->type][get_class($event->object)]
                : null, $event);
        $result += DB::notifyArray(
                isset(self::$trackers[$event->type]['*']) 
                ? self::$trackers[$event->type]['*']
                : null, $event);
        $result += DB::notifyArray(
                isset(self::$trackers[DBEvent::ALL_EVENTS][get_class($event->object)]) 
                ? self::$trackers[DBEvent::ALL_EVENTS][get_class($event->object)]
                : null, $event);
        $result += DB::notifyArray(
                isset(self::$trackers[DBEvent::ALL_EVENTS]['*']) 
                ? self::$trackers[DBEvent::ALL_EVENTS]['*']
                : null, $event);
        
        return $result;
        
    }

    protected static function notifyArray(array|null $notifiers, DBEvent $event) : int {
        
        $result = 0;
        if (isset($notifiers)) {
            foreach ($notifiers as $tracker) {
                if (is_string($tracker)) {
                    $tracker = new $tracker();
                }
                if (!$event->isNotified($tracker)) {
                    $tracker->track($event);
                    $event->addNotified($tracker);
                    $result++;
                }
            }
        }
        return $result;
    }

    public static function connect($db_host, $db_user, $db_pass, $db_name, $db_prefix='', $db_encoding='utf8mb4') {
        
        DB::$pdo = new \PDO("mysql:dbname=$db_name;host=$db_host;charset=utf8mb4", 
                $db_user, 
                $db_pass
        );
        
        DB::$host = $db_host;
        DB::$user = $db_user;
        DB::$pass = $db_pass;
        DB::$encoding = $db_encoding;
        
        DB::$prefix = $db_prefix;
        DB::$database = $db_name;
        
        if (!isset(self::$in_transaction)) {
            self::$in_transaction = false;
        }
        
    }
    
    protected static function reconnect() {
        self::connect(DB::$host, DB::$user, DB::$pass, DB::$database, DB::$prefix, DB::$encoding);
    }
    
    public static function dropAllTables($sure=false, $absolutely=false) {

        if (!$sure) {
            throw new \Exception('You have to be sure to drop all tables', -10003);
        }

        $sth_tables = self::prepare("SHOW TABLES");
        $sth_tables->execute();
        $sth_tables->setFetchMode(\PDO::FETCH_COLUMN, 0);

        while ($table = $sth_tables->fetch()) {
            if (strpos($table, self::$prefix) !== 0) {
                continue;
            }

            if (!$absolutely) {
                throw new \Exception("You have to be absolutely sure to drop table $table", -10007);
            }

            $prefix = static::$prefix;
            $sth_drop = self::prepare("DROP TABLE $table");
            $sth_drop->execute();
        }
        
        return true;
        
    }
    
    public static function prepare(string $sql, array $options=[]) : \PDOStatement|false {
        
        $sth = self::PDO()->prepare(self::convertTables($sql), $options);
        return $sth;
        
    }
    
    public static function query(string $sql, ?int $fetch_mode=null, int|string $colno_class_name=0, array $constructor_args=[]) : \PDOStatement|false {
        
        if ($fetch_mode == \PDO::FETCH_COLUMN) {
            return self::PDO()->query(self::convertTables($sql), $fetch_mode, $colno_class_name);
        } elseif ($fetch_mode == \PDO::FETCH_CLASS) {
            return self::PDO()->query(self::convertTables($sql), $fetch_mode, $colno_class_name, $constructor_args);
        } else {
            return self::PDO()->query(self::convertTables($sql), $fetch_mode);
        }
        
    }
    
    public static function exec(string $sql) : int|false {
        
        return self::PDO()->exec(self::convertTables($sql));
                
    }
    
    protected static function convertTables($sql) : string {
        $prefix = self::$prefix;
        return preg_replace("/\[(\w+?)\]/", "$prefix$1", $sql);
    }
    
    public static function getTables($sql) {
        $m = [];
        $tables = [];
        
        if (preg_match_all("/\[(\w+?)\]/", $sql, $m, PREG_SET_ORDER)) {
            foreach ($m as $table) {
                $tables[] = self::$prefix. $table[1];
            }
        }
        return $tables;
    }

}

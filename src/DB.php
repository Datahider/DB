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
class DB extends \losthost\SelfTestingSuite\SelfTestingClass {
    
    const DATE_FORMAT = 'Y-m-d H:i:s';
    
    public static \PDO $pdo;
    public static string $prefix;
    public static string $database;
    public static string $language_code;
    
    protected static $namespace = '';
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

    public static function getFormat($type) {
        $lang = strtoupper(self::$language_code);
        $type = strtoupper($type);
        
        $lang_constant_name = "FORMAT_{$lang}_{$type}";
        $common_constant_name = "FORMAT_$type";
        
        if (defined($lang_constant_name)) {
            return constant($lang_constant_name);
        } elseif (defined($common_constant_name)) {
            return constant($common_constant_name);
        } elseif ($type == 'DATETIME') {
            return self::DATE_FORMAT;
        } elseif ($type == 'BOOL') {
            return [ 'FALSE', 'TRUE' ];
        }
        return null;
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
        
        DB::$prefix = $db_prefix;
        DB::$database = $db_name;
        
    }
    
    public static function replaceVars($string, $table_name, $condition=1) {
    
        $result = str_replace(['%DATABASE%', '%TABLE_NAME%', '%CONDITION%'], [DB::$database, DB::$prefix. $table_name, $condition], $string);
        return $result;
        
    }

    public static function setClassNamespace($namespace) {
        if (preg_match("/\\\\$/", $namespace)) {
            self::$namespace = $namespace;
        } else {
            self::$namespace = "$namespace\\";
        }
        return self::$namespace;
    }
    
    static public function dropAllTables($sure=false, $absolutely=false) {

        if (!$sure) {
            throw new \Exception('You have to be sure to drop all tables', -10003);
        }

        $sth_tables = self::prepare(self::SQL_SHOW_TABLES);
        $sth_tables->execute();
        $sth_tables->setFetchMode(\PDO::FETCH_COLUMN, 0);

        while ($table = $sth_tables->fetch()) {
            if (strpos($table, self::$prefix) !== 0) {
                continue;
            }

            if (!$absolutely) {
                throw new \Exception("You have to be absolutely sure to drop table $table", -10007);
            }

            $sth_drop = self::prepare(str_replace("%TABLE_NAME%", $table, self::SQL_DROP_TABLE));
            $sth_drop->execute();
        }
        
        return true;
        
    }
    
    protected static function prepare($_sql, $table_name='', $condition=1) {
        
        $sql = self::replaceVars($_sql, $table_name, $condition);

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
    
//    public static function shortClassName($class_or_object) {
//        return self::classShortName($class_or_object);
//    }
    
    public static function classShortName($class_or_object) {
        if (!is_string($class_or_object)) {
            $class_or_object = get_class($class_or_object);
        }
        $matches = [];
        preg_match("/\\\\?([^\\\\]+)$/", $class_or_object, $matches);
        return $matches[1];
    }

    const SQL_SHOW_TABLES = "SHOW TABLES";
    const SQL_DROP_TABLE = "DROP TABLE %TABLE_NAME%";
    
    public function _test_connected() {
        if (!self::$pdo) {
            throw new \Exception("Please call DB::connect('localhost', 'test', 'correct_password', 'test', 't_') before start testing this class.");
        } else {
            self::$pdo->query("SELECT 1");
            echo '.';
            if (self::$database != 'test') {
                throw new \Exception("The database name have to be `test`.");
            }
            echo '.';
            if (self::$prefix != 't_') {
                throw new \Exception("The prefix have to be `t_`.");
            }
            echo '.';
        }
    }
    
    public function _test_addTracker() {
        $tracker = new DBTestTracker();
        self::addTracker(DBEvent::BEFORE_MODIFY, 'losthost\DB\DBTestObject', $tracker);
        echo '.';
        self::addTracker(DBEvent::AFTER_MODIFY, 'losthost\DB\DBTestObject', $tracker);
        echo '.';
        self::addTracker(DBEvent::ALL_EVENTS, '*', $tracker);
        echo '.';
    }
    
    public function _test_notify() {
        
        if (($result = $this->notify(new DBEvent(DBEvent::BEFORE_MODIFY, new DBTestObject(), 'id', 1))) != 1) {
            throw new \Exception('Awaiting result to be 1 but got '. $result);
        }
        echo '.';
        if (($result = $this->notify(new DBEvent(DBEvent::BEFORE_UPDATE, new DBTestObject(), ['id']))) != 1) {
            throw new \Exception('Awaiting result to be 1 but got '. $result);
        }
        echo '.';
        
    }
    
    public function _test_prepare() {
        $sth = $this->prepare("SELECT 1 FROM %DATABASE%.%TABLE_NAME% WHERE %CONDITION%", 'sometable', '2=3');
        if ($sth->queryString != "SELECT 1 FROM test.t_sometable WHERE 2=3") {
            throw new \Exception('Awaiting queryString to be "SELECT 1 FROM test.t_sometable WHERE 2=3" but got "'. $sth->queryString. '"');
        }
        echo '.';
    }
    
    public function _test_PrepareDB() {
        $sth = $this->prepare("CREATE TABLE IF NOT EXISTS t_testtable ( id INT )");
        $sth->execute();
    }

    public function _test() {
        return parent::_test();
    }
    
    public function _test_data() {
        return [
            'connect' => '_test_connected',
            'setClassNamespace' => [
                ['losthost\\DB', 'losthost\\DB\\'],
                ['losthost\\DB\\', 'losthost\\DB\\'],
            ],
            'classFullName' => [
                ['someclass', 'losthost\\DB\\someclass'],
                ['losthost\\otherclass', 'losthost\\otherclass'],
            ],
            'classShortName' => [
                ['losthost\\someclass', 'someclass'],
                [$this, 'DB'],
            ],
            'replaceVars' => [
                ['%DATABASE% %TABLE_NAME% %CONDITION%', 'sometable', 'test t_sometable 1'],
                ['%DATABASE% %TABLE_NAME% %CONDITION%',  'sometable', '888', 'test t_sometable 888'],
            ],
            'prepare' => '_test_prepare',
            '_test_PrepareDB' => [
                [null]
            ],
            'dropAllTables' => [
                [new \Exception('', -10003)],
                [true, new \Exception('', -10007)],
                [true, true, true],
            ],
            'addTracker' => '_test_addTracker',
            'notifyArray' => '_test_skip_',
            'notify' => '_test_notify',
            'clearTrackers' => [
                [[
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
                ]]
            ],
            'getFormat' => [
                ['bool', ['FALSE', 'TRUE']],
                ['datetime', 'Y-m-d H:i:s'],
            ],
        ];
    }
}

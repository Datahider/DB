<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBView
 *
 * @author drweb
 */
class DBView extends \losthost\SelfTestingSuite\SelfTestingClass {
    
    protected $__sql;
    protected $__params;
    protected $__data;
    protected $__pointer;


    public function __construct(string $sql, $params=[]) {
        $this->fetch($sql, $params);
    }
    
    protected function fetch($sql=null, $params=null, $vars=[]) {
        
        if ($sql !== null) {
            $this->__sql = $sql;
        }
        if ($params !== null) {
            if (!is_array($params)) {
                $this->__params = [$params];
            } else {
                $this->__params = $params;
            }
        }
        
        $sth = $this->prepare($this->__sql, $vars);
        $sth->execute($this->__params);
        
        $this->__data = $sth->fetchAll(\PDO::FETCH_ASSOC);
        $this->__pointer = -1;
        return $this->__data;
    }
    
    public function __get($name) {
        if ($this->isOutOfRange()) {
            throw new \Exception('Out of range', -10009);
        }
        if (!array_key_exists($name, $this->__data[$this->__pointer])) {
            throw new \Exception('Field does not exist', -10003);
        }
        return $this->__data[$this->__pointer][$name];
    }
    
    public function next() {
        $this->__pointer++;
        return !$this->isOutOfRange();
    }

    public function reset() {
        $this->__pointer = -1;
    }
    
    protected function isOutOfRange() {
        return $this->__pointer < 0 || $this->__pointer >= count($this->__data);
    }

    protected function prepare($sql, $vars=[]) {
        return DB::$pdo->prepare($this->replaceVars($sql, $vars));
    }
    
    protected function replaceVars($string, $vars=[]) {
    
        $default_vars = [
            'DATABASE' => DB::$database,
        ];
        
        $full_vars = array_replace($default_vars, $vars);

        $result = $string;
        foreach ($full_vars as $key => $value) {
            if ($value === null) {
                $value = '';
            }
            $result = str_replace("%$key%", $value, $result);
        }
        
        $prefix = DB::$prefix;
        
        return \preg_replace("/\[(\w+)\]/", "$prefix$1", $result);

    }
    
    protected function _test_prepare() {
        $sth = $this->prepare("SELECT id FROM [test_objects] WHERE 1");
        $test1 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::EQ, true);
        $test2 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::EQ, "SELECT id FROM t_test_objects WHERE 1");
        echo '.'; $test1->test(is_a($sth, 'PDOStatement'));
        echo '.'; $test2->test($sth->queryString);
    }
    
    protected function _test_fetch() {
        // Тестовые объекты уже должны быть созданы во время тестирования DBTestObject
        $test1 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::IS_ARRAY);
        $test2 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::ELEM_IS_ARRAY, 0);
        $test3 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::EQ, 1);
        $test4 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::EQ, 0);
        $test5 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::EQ, 2);
        $test6 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::EQ, true);
        $test7 = new \losthost\SelfTestingSuite\Test(\losthost\SelfTestingSuite\Test::EQ, false);
        echo '.'; $test1->test($this->fetch("SELECT id FROM [test_objects] WHERE 1", []));
        echo '.'; $test2->test($this->fetch("SELECT id FROM [test_objects] WHERE 1", []));
        echo '.'; $test3->test(count($this->fetch("SELECT id FROM [test_objects] WHERE id=?", [2])));
        echo '.'; $test4->test(count($this->fetch("SELECT id FROM [test_objects] WHERE name=?", 'object')));
        echo '.'; $test5->test(count($this->fetch("SELECT id FROM [test_objects] WHERE description LIKE ?", '%object%')));
        echo '.'; $test6->test($this->next());
        echo '.'; $test6->test($this->next());
        echo '.'; $test7->test($this->next());
    }
    
    protected function _test_data() {
        return [
            'replaceVars' => [
                ['Test %DATABASE%', 'Test test'],
                ['SELECT * FROM [some_table]', 'SELECT * FROM t_some_table'],
                ['SELECT * FROM [some_table] WHERE %COND1% AND %COND2%', ['COND1' => '2=1', 'COND2' => 'id = "testing_id"'], 'SELECT * FROM t_some_table WHERE 2=1 AND id = "testing_id"'],
            ],
            'prepare' => '_test_prepare',
            'fetch' => '_test_fetch',
            'isOutOfRange' => [
                [true]
            ],
            'reset' => [
                [null],
            ],
            'next' => [
                [true],
                [true],
            ],
            '__get' => [
                ['id', 2],
                ['name', new \Exception('', -10003)],
            ]
        ];
    }
}

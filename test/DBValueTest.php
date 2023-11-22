<?php


namespace losthost\DB\test;
use PHPUnit\Framework\TestCase;
use losthost\DB\DB;
use losthost\DB\DBValue;

/**
 * Description of DBValueTest
 *
 * @author drweb_000
 */
class DBValueTest extends TestCase {
    
    protected function assertPreConditions(): void {
        if (!DB::PDO()) {
            DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
        }
        parent::assertPreConditions();
    }

    public function testGetValue() {
        
        $v1 = new test_object(['name' => 'value_test'], true);
        $v1->bool_field = false;
        $v1->write();
        
        $val = new DBValue('SELECT name FROM [objects] WHERE id = ?', $v1->id);
        $this->assertEquals('value_test', $val->name);
        
    }
    
    public function testGetValueByDateTimeAndBool() {
        
        $some_date = date_create("2018-11-10 15:16:10");
        $v1 = new test_object(['name' => 'value_test_complex', 'bool_field' => false, 'some_date' => $some_date], true);
        $v1->bool_field = false;
        $v1->write();
        
        $val = new DBValue('SELECT name FROM [objects] WHERE bool_field = ? AND some_date = ?', [false, $some_date]);
        $this->assertEquals('value_test_complex', $val->name);
    }
}

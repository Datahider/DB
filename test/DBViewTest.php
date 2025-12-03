<?php


namespace losthost\DB\test;
use PHPUnit\Framework\TestCase;
use losthost\DB\DB;
use losthost\DB\DBView;

/**
 * Description of DBValueTest
 *
 * @author drweb_000
 */
class DBViewTest extends TestCase {
    
    public static function setUpBeforeClass(): void
    {
        if (!DB::PDO()) {
            DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
        }
        
    }

    public function testNextReset() {
        
        $v1 = new test_object(['name' => 'test 1'], true);
        $v1->bool_field = false;
        $v1->write();
        
        $v2 = new test_object(['name' => 'test 2'], true);
        $v2->bool_field = false;
        $v2->write();
        
        $view = new DBView('SELECT * FROM [objects] WHERE id >= ? ORDER BY id', $v1->id);
        
        $this->assertTrue($view->next());
        $this->assertEquals($v1->id, $view->id);
        $this->assertTrue($view->next());
        $this->assertEquals($v2->id, $view->id);
        $this->assertFalse($view->next());
        
        $view->reset();
        
        $this->assertTrue($view->next());
        $this->assertEquals($v1->id, $view->id);
        
        $v1->delete();
        $v2->delete();
    }
    
    public function testAsArray() {
        
        $v1 = new test_object(['name' => 'test 1'], true);
        $v1->bool_field = false;
        $v1->write();
        
        $v2 = new test_object(['name' => 'test 2'], true);
        $v2->bool_field = false;
        $v2->write();
        
        $view = new DBView('SELECT * FROM [objects] WHERE id >= ? ORDER BY id', $v1->id);

        $array = $view->asArray();
        
        $this->assertTrue($array[0]['id'] == $v1->id);
        $this->assertTrue($array[1]['id'] == $v2->id);
    }
    
}

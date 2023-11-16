<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB\test;
use losthost\DB\DBList;
use PHPUnit\Framework\TestCase;
use losthost\DB\DB;

class DBListTest extends TestCase {

    protected function assertPreConditions(): void {
        if (!DB::PDO()) {
            DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
        }
        parent::assertPreConditions();
    }

    public function testGettingObjects2Args() {
        $uniq = 'a0001';
        
        $t1 = new test_object(['name' => $uniq. 1, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t1->write();
        $t2 = new test_object(['name' => $uniq. 2, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t2->write();
        $t3 = new test_object(['name' => $uniq. 3, 'bool_field' => false, 'description' => $uniq. ' do not select'], true);
        $t3->write();
        $t4 = new test_object(['name' => $uniq. 4, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t4->write();
        
        $dblist = new DBList(test_object::class, ['description' => $uniq. ' select']);
        $count = 0;
        while ($obj = $dblist->next()) {
            $this->assertEquals($uniq, substr($obj->name, 0, 5));
            $count++;
        }
        $this->assertEquals(3, $count);
    }
    
    public function testGettingObjects3Args() {
        $uniq = 'a0002';
        
        $t1 = new test_object(['name' => $uniq. 1, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t1->write();
        $t2 = new test_object(['name' => $uniq. 2, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t2->write();
        $t3 = new test_object(['name' => $uniq. 3, 'bool_field' => false, 'description' => $uniq. ' do not select'], true);
        $t3->write();
        $t4 = new test_object(['name' => $uniq. 4, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t4->write();
        
        $dblist = new DBList(test_object::class, 'name LIKE ?', $uniq. '%');
        $count = 0;
        while ($obj = $dblist->next()) {
            $this->assertEquals($uniq, substr($obj->name, 0, 5));
            $count ++;
        }
        $this->assertEquals(4, $count);
    }

    public function testAsArray() {
        $uniq = 'a0003';
        
        $t1 = new test_object(['name' => $uniq. 1, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t1->write();
        $t2 = new test_object(['name' => $uniq. 2, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t2->write();
        $t3 = new test_object(['name' => $uniq. 3, 'bool_field' => false, 'description' => $uniq. ' do not select'], true);
        $t3->write();
        $t4 = new test_object(['name' => $uniq. 4, 'bool_field' => false, 'description' => $uniq. ' select'], true);
        $t4->write();
        
        $dblist = new DBList(test_object::class, 'description = ? ORDER BY name', $uniq. ' select');
        $array = $dblist->asArray();
        
        $this->assertEquals(3, count($array));
        $this->assertEquals($t4, $array[2]);
    }
}

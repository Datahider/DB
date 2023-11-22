<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB\test;
use PHPUnit\Framework\TestCase;
use losthost\DB\DB;
use losthost\DB\DBObject;

/**
 * Description of test_objectTest
 *
 * @author drweb_000
 */
class test_objectTest extends TestCase {
    
    protected function assertPreConditions(): void {
        if (!DB::PDO()) {
            DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
        }
        parent::assertPreConditions();
    }
    
    public function testCorrectTableName() {
        $this->assertEquals('test_objects', test_object::tableName());
    }
    
    public function testCanCreateObject() {
        $t = new test_object();
        $this->assertInstanceOf(DBObject::class, $t);
    }
    
    public function testCanCreateNewObject() {
        $t1 = new test_object();
        $this->assertEquals(true, $t1->isNew());
        
        $t2 = new test_object(['name' => 'test 2'], true);
        $this->assertEquals('test 2', $t2->name);
    }
    
    public function testIsObjectModified() {
        $t1 = new test_object();
        $this->assertFalse($t1->isModified());
        $t1->name = 'name';
        $this->assertTrue($t1->isModified());
        $t2 = new test_object(['name' => 'name'], true);
        $this->assertTrue($t2->isModified());
        
    }


    public function testCanWriteReadAndRewriteObject() {
        $t1 = new test_object(['name' => 'test 1'], true);
        $t1->description = 'The description';
        $t1->bool_field = true;
        $t1->some_date = new \DateTimeImmutable();
        $t1->write();
        
        $t2 = new test_object(['id' => $t1->id]);
        $this->assertEquals($t1, $t2);
        
        $t2->description = "Another description";
        $t2->write();
        $t1->fetch();
        $this->assertEquals("Another description", $t1->description);
    }
    
    public function testCanDeleteObject() {
        $t1 = new test_object(['name' => 'test 2'], true);
        $t1->bool_field = false;
        $t1->write();
        
        $t2 = new test_object(['name' => 'test 2']);
        $t2->delete();
        
        $this->expectExceptionMessage('Not found');
        $t3 = new test_object(['name' => 'test 2']);
    }
    
    public function testUpdatingInInsertTransaction() {
        
        $t = new test_object(['name' => 'tmp'], true);
        $t->bool_field = true;
        $t->write();
        
        $this->assertFalse(DB::inTransaction());
        $this->assertEquals('persistent', $t->name);
        
    }
    
    public function testFetchingObjectByDatetimeField() {
        $some_date = new \DateTime('2022-01-01 21:16:18');
        $test = new test_object(['name' => 'datetime select', 'bool_field' => false, 'some_date' => $some_date], true);
        $test->write();
        
        $check = new test_object(['some_date' => $some_date]);
        
        $this->assertEquals($test, $check);
    }
    
    public function testFetchingObjectByFalseField() {
        
        $some_date = new \DateTime('2022-01-01 23:31:11');
        $test = new test_object(['name' => 'false select', 'bool_field' => false, 'some_date' => $some_date], true);
        $test->write();
        
        $check = new test_object(['name' => 'false select', 'bool_field' => false]);
        
        $this->assertEquals($test, $check);
    }
    
    public function testFetchinObjectByNullField() {
        $test = new test_object(['name' => 'null select', 'bool_field' => true], true);
        $test->write();
        
        $check = new test_object(['name' => 'null select', 'another_bool' => null]);
        
        $this->assertEquals($test, $check);
    }
    
    public function testChangingPrimaryKeyInConstructor() {
        
        $t1 = new test_object(['id' => null, 'bool_field' => false], true);
        $t1->write();
        
        $t2 = new test_object(['id' => $t1->id], true);
        $this->assertFalse($t2->isNew());
        $this->assertFalse($t2->isModified());
        
    }
}

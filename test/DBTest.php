<?php

namespace losthost\DB\test;

use PHPUnit\Framework\TestCase;
use losthost\DB\DB;
use losthost\DB\DBEvent;
/**
 * Description of DBTest
 *
 * @author drweb_000
 */
class DBTest extends TestCase {
    
    protected function assertPreConditions(): void {
        if (!DB::PDO()) {
            DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
        }
        parent::assertPreConditions();
    }
    
    public function testPDOisWorking() : void {
        $info = DB::PDO()->getAttribute(\PDO::ATTR_SERVER_INFO);
        $this->assertEquals('Uptime:', substr($info, 0, 7));
    }
    
    public function testTransaction() : void {
        DB::beginTransaction();
        $this->assertTrue(DB::inTransaction());
        DB::rollBack();
        DB::beginTransaction();
        DB::commit();
        $this->assertFalse(DB::inTransaction());
    }
    
    public function testPDOcanReconnect() : void {
        try {
            DB::query("KILL CONNECTION_ID()");
        } catch (\Exception $e) {}
        
        $info = DB::PDO()->getAttribute(\PDO::ATTR_SERVER_INFO);
        $this->assertEquals('Uptime:', substr($info, 0, 7));
    }
    
    public function testPDOdoesntReconnectWhileInTransaction() : void {
        
        DB::beginTransaction();
        try {
            DB::query("KILL CONNECTION_ID()");
        } catch (\Exception $e) {}

        $this->expectExceptionCode(-10013);
        DB::PDO()->getAttribute(\PDO::ATTR_SERVER_INFO);
    }
    
//    public function testDropAllTables() {
//        
//        DB::exec(<<<END
//                CREATE TABLE IF NOT EXISTS [test] (
//                    id BIGINT(20)
//                );
//            END);
//        $this->assertTrue(DB::dropAllTables(true, true));
//    }
//    
    public function testGetTables() {
        
        $test = DB::getTables("[table1], [table2]");
        $this->assertEquals(["test_table1", "test_table2"], $test);
    }
    
    public function testTrackers() {
        $tracker = new DBTestTracker();
        DB::addTracker(DBEvent::BEFORE_MODIFY, DBTestObject::class, $tracker);
        DB::addTracker(DBEvent::AFTER_MODIFY, DBTestObject::class, $tracker);
        DB::addTracker(DBEvent::ALL_EVENTS, '*', $tracker);
        $this->assertTrue(true);
        
        $result = DB::notify(new DBEvent(DBEvent::BEFORE_MODIFY, new test_object(), 'id', 1));
        
        DB::clearTrackers();
    }
}

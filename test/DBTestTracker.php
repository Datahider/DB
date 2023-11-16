<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB\test;
use losthost\DB\DBTracker;
use losthost\DB\DBEvent;

/**
 * Description of DBChildTrackerExample
 *
 * @author drweb
 */
class DBTestTracker extends DBTracker {
    //put your code here
    public function track(DBEvent $event) {
        global $test_events;
        
        switch ($event->type) {
            case DBEvent::BEFORE_MODIFY:
                $test_events[] = "BEFORE_MODIFY:$event->fields=$event->data";
                break;
            case DBEvent::BEFORE_INSERT:
                $test_events[] = "BEFORE_INSERT";
                break;
            case DBEvent::BEFORE_UPDATE:
                $test_events[] = "BEFORE_UPDATE:". implode(",", $event->fields);
                break;
            case DBEvent::BEFORE_DELETE:
                $test_events[] = "BEFORE_DELETE";
                break;
            
            case DBEvent::INTRAN_INSERT:
                $test_events[] = "INTRAN_INSERT";
                break;
            case DBEvent::INTRAN_UPDATE:
                $test_events[] = "INTRAN_UPDATE:". implode(",", $event->fields);
                break;
            case DBEvent::INTRAN_DELETE:
                $test_events[] = "INTRAN_DELETE";
                break;
            
            case DBEvent::AFTER_MODIFY:
                $test_events[] = "AFTER_MODIFY:$event->fields=$event->data";
                break;
            case DBEvent::AFTER_INSERT:
                $test_events[] = "AFTER_INSERT";
                break;
            case DBEvent::AFTER_UPDATE:
                $test_events[] = "AFTER_UPDATE:". implode(",", $event->fields);
                break;
            case DBEvent::AFTER_DELETE:
                $test_events[] = "AFTER_DELETE";
                break;

            default:
                throw new Exception('Получено непредусмотренное тестом событие типа '. DBEvent::typeName($event->type), -10003);
        }
    }
}

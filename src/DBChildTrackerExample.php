<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * Description of DBChildTrackerExample
 *
 * @author drweb
 */
class DBChildTrackerExample extends DBTracker {
    //put your code here
    public function track(DBEvent $event) {
        switch ($event->type) {
            case DBEvent::AFTER_INSERT:
                error_log("A new object is inserted\n". $event->object->asString(4));
                break;
            case DBEvent::AFTER_UPDATE:
                error_log("The object is updated.\n". $event->object->asString(4). "\nModified fields are: ". implode(", ", $event->fields));
                break;
            case DBEvent::AFTER_MODIFY:
                error_log("I see the Sun. And a new ". $event->fields. ': '. $event->data);
                break;
            case DBEvent::INTRAN_UPDATE:
                try {
                    // Trying to modify object in immutable state
                    $event->object->name = 'A new name';
                    throw new \Exception('ERROR! I can modify immutable object!');
                } catch (\Exception $ex) {
                    error_log("That's right! I can't modify object in immutable state");  
                }
                break;
            default:
                error_log('Этот трекер не умеет обрабатывать события типа '. DBEvent::typeName($event->type));
        }
    }
}

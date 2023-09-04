<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\DB;

/**
 * This class represents a DB event
 *
 * @author drweb
 */
class DBEvent {
    
    const BEFORE_MODIFY = 0; // Ocurrs before modification of any field.
    const BEFORE_INSERT = 1; // Ocurrs before inserting a DBObject into the Database
    const BEFORE_UPDATE = 2; // Ocurrs before updating an existing DBObject in the Database
    const BEFORE_DELETE = 3; // Ocurrs before deleting a DBObject from the Database
    
    const INTRAN_INSERT = 11; // Ocurrs in same transaction after inserting a DBObject 
    const INTRAN_UPDATE = 12; // Ocurrs in same transaction after updating a DBObject
    const INTRAN_DELETE = 13; // Ocurrs in same transaction after deleting a DBObject
    
    const AFTER_MODIFY = 20; // Ocurrs after modification of any field of DBObject
    const AFTER_INSERT = 21; // Ocurrs after inseritng a DBObject
    const AFTER_UPDATE = 22; // Ocurrs after updating a DBObject
    const AFTER_DELETE = 23; // Ocurrs after deleting a DBObject

    const ALL_EVENTS = -1; // Used to add event tracker for all events
    
    const TYPE_NAMES = [
        self::BEFORE_MODIFY => 'BEFORE_MODIFY',
        self::BEFORE_INSERT => 'BEFORE_INSERT',
        self::BEFORE_UPDATE => 'BEFORE_UPDATE',
        self::BEFORE_DELETE => 'BEFORE_DELETE',

        self::INTRAN_INSERT => 'INTRAN_INSERT',
        self::INTRAN_UPDATE => 'INTRAN_UPDATE',
        self::INTRAN_DELETE => 'INTRAN_DELETE',

        self::AFTER_MODIFY => 'AFTER_MODIFY',
        self::AFTER_INSERT => 'AFTER_INSERT',
        self::AFTER_UPDATE => 'AFTER_UPDATE',
        self::AFTER_DELETE => 'AFTER_DELETE',
    ];
    
    protected $type;
    protected $object;
    protected $fields;
    protected $comment;
    protected $data;
    protected $notified;

    public function __construct(int $type, DBObject $object, string|array|null $fields, mixed $data=null, string $comment='') {
        $this->type = $type;
        $this->object = $object;
        $this->fields = $fields;
        $this->comment = $comment;
        $this->data = $data;
        $this->notified = [];
    }
    
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }
    
    public static function typeName(int $event_type) {
        if (isset(self::TYPE_NAMES[$event_type])) {
            return self::TYPE_NAMES[$event_type];
        }
        throw new Exception('Unknown event type:'. $event_type);
    }
    
    public function addNotified(DBTracker $tracker) {
        if (array_search($tracker, $this->notified, true) === false) {
            $this->notified[] = $tracker;
            return true;
        }
        return false;
    }
    
    public function isNotified(DBTracker $tracker) {
        if (array_search($tracker, $this->notified, true) === false) {
            return false;
        }
        return true;
    }
}

# About DB
Database access with so called DB Objects (descendants of class DBObject)
that mirror data table structure to your project

## DB - making DB connection
There is an example class DBChildObjectExample that is a descendant of DBObject.
Before using DBObject descendant you have to open DB connection with and make some stuff:
    
```
    DB::connect($db_host, $db_user, $db_pass, $db_name, $db_prefix='', $db_encoding='utf8mb4');
    DB::setClassNamespace("\\your\\namespace\\");   /* use \\ at the begining and the end of namespace */
    DB::checkDataStructure("yourClassOne, yourClassTwo, andSoOn", true);
```

After that you can access a table with your DBObject descendant.

## DBObject - accessing data
Some useful operations:

```
    // Creating record:
    
    $new = new DBChildObjectExample();
    $new->name = "test";
    $new->description = "A test record";
    $new->write();
    $new_record_id = $new->id;

    // Accessing record:

    $existing = new DBChildObjectExample("id = ?", $new_record_id);

    if ($existing->isNew()) {

        throw new \Exception('Object not found');

    } else {
        // Modify object:

        $existing->description = 'New description';
        $existing->write();
    }
```

## Special stuff
If you need some special processing (like checking or filling data) you may override DBObject::fetch(...) and DBObject::write() methods.

## TODO
- добавить параметр формата к DBObject->asString();

## Epilogue
That's it. Have fun.
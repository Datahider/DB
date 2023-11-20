<?php
namespace losthost\DB;

/**
 * Description of DBBaseClass
 *
 * @author drweb_000
 */
class DBBaseClass {
    
    protected function filterTypes(array $filter) {
        $result = [];
        foreach ($filter as $key => $value) {
            if (is_a($value, \DateTimeInterface::class)) {
                $result[$key] = $value->format(DB::DATE_FORMAT);
            } elseif (is_bool($value)) {
                $result[$key] = (int)$value;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    
}

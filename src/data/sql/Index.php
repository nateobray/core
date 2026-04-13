<?php
namespace obray\data\sql;

class Index
{
    const UNIQUE = 'UNIQUE';
    const INDEX = '';

    static public function createSQL(mixed $columns, string $type=self::INDEX): string
    {
        if(gettype($columns) === 'string') $columns = [$columns];
        $columnSQL = '`' . implode('`,`', $columns) . '`';
        $prefix = !empty($type) ? 'uniq' : 'idx';
        $keyName = $prefix . '_' . implode('_', $columns);
        if(strlen($keyName) > 64){
            $hash = substr(md5($keyName), 0, 8);
            $keyName = substr($keyName, 0, 55) . '_' . $hash;
        }
        if(!empty($type)) $type = $type . ' ';
        $sql = $type . 'KEY `' . $keyName . '` (' . $columnSQL . ') USING BTREE';
        return $sql;
    }

    /**
     * Normalize a raw INDEXES constant entry into a comparable structure.
     * Returns ['columns' => string[], 'type' => string]
     *
     * Supports three formats:
     *   ['col']                        — single column, no type
     *   ['col', 'UNIQUE']              — single column + type
     *   [['col1','col2'], 'UNIQUE']    — explicit columns array + type
     *   ['col1', 'col2', 'col3']       — flat multi-column composite, no type
     */
    static public function normalize(array $entry): array
    {
        $validTypes = ['UNIQUE', ''];
        if(is_array($entry[0])){
            // Explicit [columns_array, type] format
            $columns = $entry[0];
            $type = isset($entry[1]) ? strtoupper(trim($entry[1])) : '';
        } else {
            $potentialType = isset($entry[1]) ? strtoupper(trim($entry[1])) : '';
            if(count($entry) === 2 && in_array($potentialType, $validTypes)){
                // ['col', 'UNIQUE'] or ['col', ''] format
                $columns = [$entry[0]];
                $type = $potentialType;
            } else {
                // Flat array of column names: ['col1', 'col2', 'col3']
                $columns = $entry;
                $type = '';
            }
        }
        return ['columns' => $columns, 'type' => $type];
    }
}
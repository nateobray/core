<?php
namespace obray\data\sql;

class Index
{
    const UNIQUE = 'UNIQUE';
    const INDEX = '';

    static public function createSQL(mixed $columns, string $type=self::INDEX, ?string $name=null): string
    {
        if(gettype($columns) === 'string') $columns = [$columns];
        $columnSQL = '`' . implode('`,`', $columns) . '`';
        $keyName = $name;
        if(empty($keyName)){
            $prefix = !empty($type) ? 'uniq' : 'idx';
            $keyName = $prefix . '_' . implode('_', $columns);
        }
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
     * Returns ['columns' => string[], 'type' => string, 'name' => ?string]
     *
     * Supports these formats:
     *   ['col']                        — single column, no type
     *   ['col', 'UNIQUE']              — single column + type
     *   [['col1','col2'], 'UNIQUE']    — explicit columns array + type
     *   ['col1', 'col2', 'col3']       — flat multi-column composite, no type
     *   ['name' => 'idx_name', 'columns' => ['col1','col2'], 'type' => 'UNIQUE']
     */
    static public function normalize(array $entry): array
    {
        $validTypes = ['UNIQUE', ''];
        if(array_key_exists('columns', $entry)){
            $columns = $entry['columns'];
            if(gettype($columns) === 'string') $columns = [$columns];
            $type = isset($entry['type']) ? strtoupper(trim($entry['type'])) : '';
            $name = isset($entry['name']) ? trim((string)$entry['name']) : null;
            if(!in_array($type, $validTypes)){
                $type = '';
            }
            return ['columns' => $columns, 'type' => $type, 'name' => $name ?: null];
        }
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
        return ['columns' => $columns, 'type' => $type, 'name' => null];
    }
}

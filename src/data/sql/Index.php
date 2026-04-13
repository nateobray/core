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
        if(!empty($type)) $type = $type . ' ';
        $sql = $type . 'KEY `' . $keyName . '` (' . $columnSQL . ') USING BTREE';
        return $sql;
    }

    /**
     * Normalize a raw INDEXES constant entry into a comparable structure.
     * Returns ['columns' => string[], 'type' => string]
     */
    static public function normalize(array $entry): array
    {
        $columns = is_array($entry[0]) ? $entry[0] : [$entry[0]];
        $type = isset($entry[1]) ? strtoupper(trim($entry[1])) : '';
        return ['columns' => $columns, 'type' => $type];
    }
}
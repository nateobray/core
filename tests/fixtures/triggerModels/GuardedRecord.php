<?php
namespace models;

class GuardedRecord extends \obray\data\DBO
{
    public const TABLE = 'GuardedRecords';
    public const FEATURE_SET = [1];
    public \obray\data\types\PrimaryKey $col_id;
    public const TRIGGERS = [
        'guarded_records_no_update' => [
            'timing' => 'BEFORE', 'event' => 'UPDATE',
            'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record is immutable'",
        ],
        'guarded_records_no_delete' => [
            'timing' => 'BEFORE', 'event' => 'DELETE',
            'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record is immutable'",
        ],
    ];
}

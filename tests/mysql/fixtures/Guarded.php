<?php
namespace models;

class Guarded extends \obray\data\DBO
{
    const TABLE = 'mysql_guarded';
    const FEATURE_SET = [1];
    const TRIGGERS = [
        'mysql_guarded_no_update' => ['timing' => 'BEFORE', 'event' => 'UPDATE',
            'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable test record'"],
        'mysql_guarded_no_delete' => ['timing' => 'BEFORE', 'event' => 'DELETE',
            'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable test record'"],
    ];
    public \obray\data\types\PrimaryKey $col_id;
    public \obray\data\types\Varchar64 $col_name;
}

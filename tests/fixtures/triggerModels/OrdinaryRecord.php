<?php
namespace models;

class OrdinaryRecord extends \obray\data\DBO
{
    public const TABLE = 'OrdinaryRecords';
    public const FEATURE_SET = [1];
    public \obray\data\types\PrimaryKey $col_id;
}

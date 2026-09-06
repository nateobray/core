<?php
namespace models;

class Setting extends \obray\data\DBO
{
    const TABLE = 'mysql_settings';
    const FEATURE_SET = [1];
    const SEED_FILE = 'settings.csv';
    const KEEP_SEEDS_CURRENT = true;
    const SEED_MATCH_COLUMNS = ['setting_key'];
    public \obray\data\types\PrimaryKey $col_setting_id;
    public \obray\data\types\Varchar64 $col_setting_key;
    public \obray\data\types\Varchar64 $col_setting_value;
}

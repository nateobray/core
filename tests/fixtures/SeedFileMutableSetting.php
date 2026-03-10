<?php
namespace tests\fixtures;

use obray\data\DBO;
use obray\data\types\PrimaryKey;
use obray\data\types\Varchar64;

class SeedFileMutableSetting extends DBO
{
    public const TABLE = 'seed_file_mutable_settings';
    public const KEEP_SEEDS_CURRENT = true;
    public const SEED_FILE = 'TestSeedSettings.csv';
    public const SEED_MATCH_COLUMNS = ['setting_key'];

    protected PrimaryKey $col_setting_id;
    protected Varchar64 $col_setting_key;
    protected Varchar64 $col_setting_value;
}

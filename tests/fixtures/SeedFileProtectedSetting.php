<?php
namespace tests\fixtures;

use obray\data\types\Varchar64;

class SeedFileProtectedSetting extends SeedFileMutableSetting
{
    public const TABLE = 'seed_file_protected_settings';
    public const SEED_PRESERVE_COLUMNS = ['setting_override'];

    protected Varchar64 $col_setting_override;
}

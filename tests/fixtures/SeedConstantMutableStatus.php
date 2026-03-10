<?php
namespace tests\fixtures;

use obray\data\DBO;
use obray\data\types\PrimaryKey;
use obray\data\types\Varchar64;

class SeedConstantMutableStatus extends DBO
{
    public const TABLE = 'seed_constant_mutable_statuses';
    public const KEEP_SEEDS_CURRENT = true;
    public const SEED_CONSTANTS = true;

    public const ACTIVE = 1;
    public const INACTIVE = 2;

    protected PrimaryKey $col_status_id;
    protected Varchar64 $col_status_name;
}

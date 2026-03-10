<?php
namespace tests\fixtures;

class SeedConstantInsertOnlyStatus extends SeedConstantMutableStatus
{
    public const TABLE = 'seed_constant_insert_only_statuses';
    public const SEED_INSERT_ONLY = true;
}

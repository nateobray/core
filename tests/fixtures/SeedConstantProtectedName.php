<?php
namespace tests\fixtures;

class SeedConstantProtectedName extends SeedConstantMutableStatus
{
    public const TABLE = 'seed_constant_protected_names';
    public const SEED_PRESERVE_COLUMNS = ['status_name'];
}

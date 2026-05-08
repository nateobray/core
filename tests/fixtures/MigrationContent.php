<?php
namespace tests\fixtures;

use obray\data\DBO;
use obray\data\types\PrimaryKey;

class MigrationContent extends DBO
{
    public const TABLE = 'migration_contents';

    public PrimaryKey $col_content_id;
    public CustomMediumText $col_content_html;
}

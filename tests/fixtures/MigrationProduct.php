<?php
namespace tests\fixtures;

use obray\data\DBO;
use obray\data\types\ForeignKey;
use obray\data\types\PrimaryKey;
use obray\data\types\Varchar64;

class MigrationProduct extends DBO
{
    public const TABLE = 'migration_products';

    public const INDEXES = [
        ['category_id'],
        ['product_name', 'UNIQUE'],
    ];

    public const FOREIGN_KEYS = [
        ['category_id', 'categories', 'category_id'],
    ];

    public PrimaryKey $col_product_id;
    public Varchar64 $col_product_name;
    public ForeignKey $col_category_id;
}

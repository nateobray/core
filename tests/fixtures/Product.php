<?php
namespace tests\fixtures;

use obray\data\DBO;
use obray\data\types\ForeignKey;
use obray\data\types\PrimaryKey;
use obray\data\types\Varchar64;

class Product extends DBO
{
    public const TABLE = 'products';

    protected PrimaryKey $col_product_id;
    protected Varchar64 $col_product_name;
    protected ForeignKey $col_category_id;
}

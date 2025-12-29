<?php
namespace tests\fixtures;

use obray\data\DBO;
use obray\data\types\PrimaryKey;
use obray\data\types\Varchar64;

class Category extends DBO
{
    public const TABLE = 'categories';

    protected PrimaryKey $col_category_id;
    protected Varchar64 $col_category_name;
}

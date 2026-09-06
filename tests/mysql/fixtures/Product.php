<?php
namespace models;

class Product extends \obray\data\DBO
{
    const TABLE = 'mysql_products';
    const FEATURE_SET = [1];
    const INDEXES = [['name' => 'product_name_unique', 'columns' => ['name'], 'type' => 'UNIQUE']];
    const FOREIGN_KEYS = [['category_id', 'mysql_categories', 'category_id']];
    public \obray\data\types\PrimaryKey $col_product_id;
    public \obray\data\types\Varchar64 $col_name;
    public \obray\data\types\ForeignKeyNullable $col_category_id;
}

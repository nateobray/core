<?php
namespace models;

class Category extends \obray\data\DBO
{
    const TABLE = 'mysql_categories';
    const FEATURE_SET = [1];
    public \obray\data\types\PrimaryKey $col_category_id;
    public \obray\data\types\Varchar64 $col_name;
}

<?php
namespace obray\data\types;

class DecimalNullable extends BaseType
{
    const TYPE = 'DECIMAL';
    const LENGTH = '10,2';
    const NULLABLE = true;
    const UNSIGNED = false;
    const DEFAULT = null;
}
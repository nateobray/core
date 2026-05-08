<?php
namespace obray\data\types;

class Decimal18_6 extends BaseType
{
    const TYPE = 'DECIMAL';
    const LENGTH = '18,6';
    const NULLABLE = false;
    const UNSIGNED = false;
    const DEFAULT = 0.00;
}

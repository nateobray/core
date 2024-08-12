<?php
namespace obray\data\types;

class Decimal5 extends BaseType
{
    const TYPE = 'DECIMAL';
    const LENGTH = '10,5';
    const NULLABLE = false;
    const UNSIGNED = false;
    const DEFAULT = 0.00;
}
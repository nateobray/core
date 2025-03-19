<?php
namespace obray\data\types;

class Decimal6 extends BaseType
{
    const TYPE = 'DECIMAL';
    const LENGTH = '10,6';
    const NULLABLE = false;
    const UNSIGNED = false;
    const DEFAULT = 0.00;
}
<?php
namespace obray\data\types;

class Decimal extends BaseType
{
    const TYPE = 'DECIMAL';
    const LENGTH = '10,2';
    const NULLABLE = false;
    const UNSIGNED = false;
    const DEFAULT = 0.00;
}
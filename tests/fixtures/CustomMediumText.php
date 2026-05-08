<?php
namespace tests\fixtures;

use obray\data\types\BaseType;

class CustomMediumText extends BaseType
{
    const TYPE = 'MEDIUMTEXT';
    const NULLABLE = true;
    const LENGTH = null;
    const UNSIGNED = null;
}

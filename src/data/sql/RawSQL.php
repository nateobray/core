<?php
namespace obray\data\sql;

class RawSQL
{
    private $value = '';

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
<?php
namespace obray\data\sql;

class Like
{
    private $value = '';

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
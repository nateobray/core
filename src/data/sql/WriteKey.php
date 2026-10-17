<?php
namespace obray\data\sql;

/** Validate the integer identifiers used by model update/delete operations. */
final class WriteKey
{
    public static function value($value): int|string
    {
        if ((is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/^[0-9]+$/D', $value))) {
            return $value;
        }
        throw new \InvalidArgumentException('A model write requires a non-negative integer primary key.');
    }
}

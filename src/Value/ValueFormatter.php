<?php
declare(strict_types=1);

namespace VilnisGr\EnvEditor\Value;

class ValueFormatter
{
    public function format(string $value): string
    {
        if (preg_match('/[\s#=]/', $value)) {
            return '"' . addslashes($value) . '"';
        }

        return $value;
    }
}
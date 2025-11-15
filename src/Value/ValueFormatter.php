<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Value;

class ValueFormatter
{
    public function format(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if ($this->requiresQuoting($value)) {
            return '"' . $this->escape($value) . '"';
        }

        return $value;
    }

    private function requiresQuoting(string $value): bool
    {
        return (bool) preg_match('/[\s#]/u', $value);
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\',   '"',   "\n",  "\r",  "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $value
        );
    }
}

<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Schema;

use VilnisGr\EnvEditor\Schema\Exceptions\EnvSchemaException;

class EnvRules
{
    /** @var array<string, array<int, callable(string):void>> */
    private array $rules = [];

    public function add(string $key, callable $rule): void
    {
        if (!isset($this->rules[$key])) {
            $this->rules[$key] = [];
        }

        $this->rules[$key][] = $rule;
    }

    public function validate(string $key, string $value): void
    {
        if (!isset($this->rules[$key])) {
            return;
        }

        foreach ($this->rules[$key] as $rule) {
            $rule($value);
        }
    }

    public function min(string $key, int $min): void
    {
        $this->add($key, function (string $value) use ($key, $min) {
            if (!is_numeric($value) || (int)$value < $min) {
                throw new EnvSchemaException("Value for '$key' must be >= $min");
            }
        });
    }

    public function max(string $key, int $max): void
    {
        $this->add($key, function (string $value) use ($key, $max) {
            if (!is_numeric($value) || (int)$value > $max) {
                throw new EnvSchemaException("Value for '$key' must be <= $max");
            }
        });
    }

    public function in(string $key, array $allowed): void
    {
        $this->add($key, function (string $value) use ($key, $allowed) {
            if (!in_array($value, $allowed, true)) {
                $list = implode(', ', $allowed);
                throw new EnvSchemaException("Value for '$key' must be one of: $list");
            }
        });
    }

    public function regex(string $key, string $pattern): void
    {
        $this->add($key, function (string $value) use ($key, $pattern) {
            if (!preg_match($pattern, $value)) {
                throw new EnvSchemaException("Value for '$key' does not match pattern $pattern");
            }
        });
    }

    public function length(string $key, int $min, ?int $max = null): void
    {
        $this->add($key, function (string $value) use ($key, $min, $max) {
            $len = strlen($value);

            if ($len < $min || ($max !== null && $len > $max)) {
                throw new EnvSchemaException("Value for '$key' must be length $min to $max");
            }
        });
    }

    public function export(): array
    {
        return $this->rules;
    }
}

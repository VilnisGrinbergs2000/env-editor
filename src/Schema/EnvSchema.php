<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Schema;

use VilnisGr\EnvEditor\Contracts\WriterInterface;
use VilnisGr\EnvEditor\Schema\Exceptions\EnvSchemaException;

class EnvSchema
{
    /** @var array<int,string> */
    private array $required = [];

    /** @var array<string,string> */
    private array $optional = [];

    /** @var array<string,string> */
    private array $casts = [];

    private EnvRules $rules;

    public function __construct()
    {
        $this->rules = new EnvRules();
    }

    public static function make(): self
    {
        return new self();
    }

    public function required(string ...$keys): self
    {
        foreach ($keys as $key) {
            $this->required[] = $key;
        }
        return $this;
    }

    public function optional(string $key, string $default): self
    {
        $this->optional[$key] = $default;
        return $this;
    }

    public function cast(string $key, string $type): self
    {
        $this->casts[$key] = $type;
        return $this;
    }

    public function bool(string $key): self  { return $this->cast($key, 'bool'); }
    public function int(string $key): self   { return $this->cast($key, 'int'); }
    public function float(string $key): self { return $this->cast($key, 'float'); }
    public function array(string $key): self { return $this->cast($key, 'array'); }
    public function json(string $key): self  { return $this->cast($key, 'json'); }

    public function castEnum(string $key, string $enumClass): self
    {
        $this->casts[$key] = 'enum:' . ltrim($enumClass, '\\');
        return $this;
    }

    public function group(string $prefix, callable $callback): self
    {
        $sub = new self();
        $callback($sub);

        foreach ($sub->required as $item) {
            $this->required[] = $prefix . $item;
        }

        foreach ($sub->optional as $key => $default) {
            $this->optional[$prefix . $key] = $default;
        }

        foreach ($sub->casts as $key => $type) {
            $this->casts[$prefix . $key] = $type;
        }

        $subRules = $sub->rules->export();
        foreach ($subRules as $key => $ruleList) {
            foreach ($ruleList as $rule) {
                $this->rules->add($prefix . $key, $rule);
            }
        }

        return $this;
    }

    public function rules(): EnvRules
    {
        return $this->rules;
    }

    /**
     * @return array<string,mixed>
     */
    public function validate(WriterInterface $writer): array
    {
        $env = $writer->toArray();

        $result = [];

        foreach ($this->optional as $key => $default) {
            if (!array_key_exists($key, $env)) {
                $writer->set($key, $default);
                $env[$key] = $default;
            }
        }

        foreach ($this->required as $key) {
            if (!array_key_exists($key, $env)) {
                throw new EnvSchemaException("Missing required environment key: $key");
            }
        }

        $expectedKeys = array_unique(array_merge($this->required, array_keys($this->optional)));

        foreach ($expectedKeys as $key) {

            $value = $env[$key];

            $this->rules->validate($key, $value);

            if (isset($this->casts[$key])) {
                $value = EnvCast::apply($value, $this->casts[$key]);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}

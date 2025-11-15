<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Schema;

use Throwable;
use VilnisGr\EnvEditor\Contracts\WriterInterface;
use VilnisGr\EnvEditor\Schema\Exceptions\EnvSchemaException;
use ReflectionClass;

readonly class EnvConfigFactory
{
    public function __construct(
        private EnvSchema       $schema,
        private WriterInterface $writer
    ) {}

    public function make(string $dtoClassName): object
    {
        $data = $this->schema->validate($this->writer);

        if (!class_exists($dtoClassName)) {
            throw new EnvSchemaException("DTO class '$dtoClassName' does not exist or autoload failed.");
        }

        $ref = new ReflectionClass($dtoClassName);
        $ctor = $ref->getConstructor();

        if ($ctor === null) {
            throw new EnvSchemaException("$dtoClassName must have a constructor");
        }

        $args = [];

        foreach ($ctor->getParameters() as $param) {
            $paramName = $param->getName();

            $envKey = strtoupper($paramName);

            if (!array_key_exists($envKey, $data)) {
                throw new EnvSchemaException(
                    "Missing env key '$envKey' required by constructor of $dtoClassName"
                );
            }

            $args[] = $data[$envKey];
        }

        try {
            return $ref->newInstanceArgs($args);
        } catch (Throwable $e) {
            throw new EnvSchemaException("Failed to construct '$dtoClassName': " . $e->getMessage());
        }
    }
}

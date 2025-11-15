<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Schema;

use ValueError;
use VilnisGr\EnvEditor\Schema\Exceptions\EnvSchemaException;

class EnvCast
{
    /**
     * @param string $value
     * @param string $type
     * @return mixed
     */
    public static function apply(string $value, string $type): mixed
    {
        $type = trim($type);

        if (str_starts_with($type, 'enum:')) {
            $enumClass = substr($type, 5);
            $enumClass = ltrim($enumClass, '\\');

            if (!class_exists($enumClass)) {
                throw new EnvSchemaException("Enum class '$enumClass' does not exist");
            }

            if (!enum_exists($enumClass)) {
                throw new EnvSchemaException("'$enumClass' is not a valid enum");
            }

            try {
                return $enumClass::from($value);
            } catch (ValueError) {
                throw new EnvSchemaException("Invalid value '$value' for enum $enumClass");
            }
        }

        switch ($type) {
            case 'string':
                return $value;

            case 'int':
                if (!is_numeric($value)) {
                    throw new EnvSchemaException("Cannot cast '$value' to int");
                }
                return (int) $value;

            case 'float':
                if (!is_numeric($value)) {
                    throw new EnvSchemaException("Cannot cast '$value' to float");
                }
                return (float) $value;

            case 'bool':
                return self::castBool($value);

            case 'array':
                if ($value === '') {
                    return [];
                }
                return array_map('trim', explode(',', $value));

            case 'json':
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new EnvSchemaException(
                        "Cannot cast '$value' to JSON: " . json_last_error_msg()
                    );
                }
                return $decoded;

            default:
                throw new EnvSchemaException("Unknown cast type: $type");
        }
    }

    private static function castBool(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on'   => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new EnvSchemaException("Cannot cast '$value' to bool"),
        };
    }
}

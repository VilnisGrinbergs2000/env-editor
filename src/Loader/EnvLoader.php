<?php

declare(strict_types=1);

namespace Vilnis\EnvEditor\Loader;

use Vilnis\EnvEditor\Exceptions\DotenvException;

class EnvLoader
{
    private string $path;

    public function __construct(string $path)
    {
        if (!is_file($path)) {
            throw new DotenvException("Env file not found: $path");
        }

        $this->path = $path;
    }

    public function load(bool $overrideExisting = false): array
    {
        $content = file_get_contents($this->path);
        if ($content === false) {
            throw new DotenvException("Unable to read env file: $this->path");
        }

        $lines = explode("\n", $content);
        $loaded = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            $value = $this->stripQuotes($value);
            $value = $this->expandVariables($value);

            if (!$overrideExisting && getenv($key) !== false) {
                continue;
            }

            $this->setEnv($key, $value);

            $loaded[$key] = $value;
        }

        return $loaded;
    }

    private function stripQuotes(string $value): string
    {
        $length = strlen($value);

        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, $length - 2);
            }
        }

        return $value;
    }

    private function expandVariables(string $value): string
    {
        return preg_replace_callback('/\$\{([A-Z0-9_]+)}/i', static function (array $matches): string {
            $var = $matches[1];
            $env = getenv($var);
            if ($env === false) {
                return $matches[0];
            }

            return $env;
        }, $value) ?? $value;
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;

        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
    }
}

<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Loader;

use VilnisGr\EnvEditor\Exceptions\DotenvException;
use VilnisGr\EnvEditor\Parser\DotenvParser;

class EnvLoader
{
    private string $path;
    private DotenvParser $parser;

    public function __construct(string $path, ?DotenvParser $parser = null)
    {
        if (!is_file($path)) {
            throw new DotenvException("Env file not found: $path");
        }

        $this->path = $path;
        $this->parser = $parser ?? new DotenvParser();
    }

    /**
     * @return array<string, string>
     */
    public function load(bool $overrideExisting = false): array
    {
        $content = file_get_contents($this->path);
        if ($content === false) {
            throw new DotenvException("Unable to read env file: $this->path");
        }

        $parsed = $this->parser->parse($content);
        $loaded = [];

        foreach ($parsed as $line) {
            if ($line['type'] !== 'entry') {
                continue;
            }

            $key = $line['key'];
            $value = $line['value'];

            if (!$overrideExisting && $this->envExists($key)) {
                continue;
            }

            $this->setEnv($key, $value);
            $loaded[$key] = $value;
        }

        return $loaded;
    }

    private function envExists(string $key): bool
    {
        return array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false;
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("$key=$value");

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

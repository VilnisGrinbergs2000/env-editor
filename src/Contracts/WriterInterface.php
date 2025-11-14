<?php

declare(strict_types=1);

namespace Vilnis\EnvEditor\Contracts;

interface WriterInterface
{
    public function load(string $filepath): void;

    public function set(
        string $key,
        string $value,
        mixed $position = 'bottom',
        int $spacing = 0
    ): void;

    public function remove(string $key): void;

    public function save(?string $filepath = null): void;

    public function all(): array;

    public function toArray(): array;

    public function import(array $values): void;

    public function has(string $key): bool;

    public function missingKeys(array $keys): array;

    public function backup(string $backupPath): void;

    public function restore(string $backupPath): void;

    public function diff(string $otherFile): array;

    public function merge(string $otherFile, bool $overrideExisting = false): void;

    public function preview(?string $filepath = null): string;
}

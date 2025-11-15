<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Contracts;

use Stringable;

interface WriterInterface
{
    public function load(string $filepath): void;

    /**
     * @param string $key
     * @param string $value
     * @param mixed  $position
     * @param int    $spacing
     */
    public function set(string $key, string $value, mixed $position = 'bottom', int $spacing = 0): void;

    public function remove(string $key): self;

    public function save(?string $filepath = null, bool $atomic = true): void;

    /**
     * @return array<string,string>
     */
    public function toArray(): array;

    /**
     * @param array<string,scalar|Stringable> $values
     */
    public function import(array $values): void;

    public function has(string $key): bool;

    /**
     * @param array<int,string> $keys
     * @return array<int,string>
     */
    public function missingKeys(array $keys): array;

    public function backup(string $backupPath): void;

    public function restore(string $backupPath): void;

    /**
     * @return array{
     *   missing_in_current: array<string,string>,
     *   extra_in_current: array<string,string>,
     *   changed: array<string,array{current:string,other:string}>
     * }
     */
    public function diff(string $otherFile): array;

    public function merge(string $otherFile, bool $overrideExisting = false): void;

    public function preview(): string;
}

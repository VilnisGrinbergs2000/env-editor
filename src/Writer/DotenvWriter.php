<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Writer;

use VilnisGr\EnvEditor\Contracts\WriterInterface;
use VilnisGr\EnvEditor\Exceptions\DotenvException;
use VilnisGr\EnvEditor\Parser\DotenvParser;
use VilnisGr\EnvEditor\Value\ValueFormatter;

class DotenvWriter implements WriterInterface
{
    private string $filepath;

    /** @var array<int, array<string, mixed>> */
    private array $lines = [];

    private DotenvParser $parser;
    private ValueFormatter $formatter;

    private ?string $pendingAfter = null;
    private ?string $pendingBefore = null;
    private ?string $pendingPosition = null;
    private int $pendingSpacing = 0;

    public function __construct(
        ?DotenvParser $parser = null,
        ?ValueFormatter $formatter = null
    ) {
        $this->parser  = $parser ?? new DotenvParser();
        $this->formatter = $formatter ?? new ValueFormatter();
    }

    public function load(string $filepath): void
    {
        if (!is_file($filepath)) {
            throw new DotenvException("File not found: $filepath");
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new DotenvException("Unable to read file: $filepath");
        }

        $this->filepath = $filepath;
        $this->lines    = $this->parser->parse($content);
    }

    public function set(string $key, string $value, mixed $position = 'bottom', int $spacing = 0): void {

        $finalPosition = $this->pendingPosition ?? $position;

        $finalAfter  = $this->pendingAfter  ?? (is_array($position) ? ($position['after'] ?? null) : null);
        $finalBefore = $this->pendingBefore ?? (is_array($position) ? ($position['before'] ?? null) : null);
        $finalSpacing = $this->pendingSpacing ?: $spacing;

        $formattedValue = $this->formatter->format($value);

        foreach ($this->lines as &$line) {
            if ($line['type'] === 'entry' && $line['key'] === $key) {
                $inlineComment = '';
                $raw = $line['raw'];
                if (($pos = strpos($raw, '#')) !== false) {
                    $inlineComment = ' ' . substr($raw, $pos);
                }

                $line['value'] = $value;
                $line['raw']   = "$key=$formattedValue$inlineComment";

                $this->resetPending();
                return;
            }
        }

        $entry = [
            'type'  => 'entry',
            'key'   => $key,
            'value' => $value,
            'raw'   => "$key=$formattedValue",
        ];

        $insertAt = $this->resolvePosition(
            is_string($finalPosition) || $finalPosition === null ? $finalPosition : null,
            $finalAfter,
            $finalBefore,
            is_array($finalPosition) ? $finalPosition : null
        );

        while ($finalSpacing > 0) {
            array_splice($this->lines, $insertAt, 0, [[
                'type' => 'comment',
                'raw'  => '',
            ]]);
            $insertAt++;
            $finalSpacing--;
        }

        array_splice($this->lines, $insertAt, 0, [$entry]);

        $this->resetPending();
    }


    public function remove(string $key): self
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (array $line): bool =>
            !($line['type'] === 'entry' && $line['key'] === $key)
        ));

        return $this;
    }

    public function save(?string $filepath = null, bool $atomic = true): void
    {
        $target = $filepath ?? ($this->filepath ?? null);

        if ($target === null) {
            throw new DotenvException('No target filepath set for save().');
        }

        $output = implode("\n", array_map(
            static fn (array $line): string => $line['raw'],
            $this->lines
        ));

        if ($atomic === false) {
            file_put_contents($target, $output);
            return;
        }

        $dir = dirname($target);
        $tempFile = tempnam($dir, 'envtmp_');

        if ($tempFile === false) {
            throw new DotenvException("Failed to create temporary file in: $dir");
        }

        $written = file_put_contents($tempFile, $output, LOCK_EX);

        if ($written === false) {
            @unlink($tempFile);
            throw new DotenvException("Failed to write to temporary file: $tempFile");
        }

        if (function_exists('fsync')) {
            $handle = fopen($tempFile, 'c');
            if ($handle !== false) {
                @fsync($handle);
                fclose($handle);
            }
        }

        if (!@rename($tempFile, $target)) {
            @unlink($tempFile);
            throw new DotenvException("Failed to replace $target atomically");
        }
    }

    public function toArray(): array
    {
        $out = [];
        foreach ($this->lines as $line) {
            if ($line['type'] === 'entry') {
                $out[$line['key']] = $line['value'];
            }
        }
        return $out;
    }

    public function import(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $this->set($key, (string)$value);
            }
        }
    }

    public function has(string $key): bool
    {
        foreach ($this->lines as $line) {
            if ($line['type'] === 'entry' && $line['key'] === $key) {
                return true;
            }
        }
        return false;
    }

    public function missingKeys(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (is_string($k) && !$this->has($k)) {
                $out[] = $k;
            }
        }
        return $out;
    }

    public function backup(string $backupPath): void
    {
        if (!copy($this->filepath, $backupPath)) {
            throw new DotenvException("Failed to backup to $backupPath");
        }
    }

    public function restore(string $backupPath): void
    {
        if (!is_file($backupPath)) {
            throw new DotenvException("Backup not found: $backupPath");
        }

        if (!copy($backupPath, $this->filepath)) {
            throw new DotenvException("Failed to restore from $backupPath");
        }

        $this->load($this->filepath);
    }

    public function diff(string $otherFile): array
    {
        if (!is_file($otherFile)) {
            throw new DotenvException("File not found: $otherFile");
        }

        $content = file_get_contents($otherFile);
        $otherLines = $this->parser->parse($content ?: '');

        $current = $this->toArray();
        $other = [];

        foreach ($otherLines as $line) {
            if ($line['type'] === 'entry') {
                $other[$line['key']] = $line['value'];
            }
        }

        $diff = [
            'missing_in_current' => [],
            'extra_in_current'   => [],
            'changed'            => [],
        ];

        foreach ($other as $key => $value) {
            if (!isset($current[$key])) {
                $diff['missing_in_current'][$key] = $value;
            } elseif ($current[$key] !== $value) {
                $diff['changed'][$key] = [
                    'current' => $current[$key],
                    'other'   => $value,
                ];
            }
        }

        foreach ($current as $key => $value) {
            if (!isset($other[$key])) {
                $diff['extra_in_current'][$key] = $value;
            }
        }

        return $diff;
    }

    public function merge(string $otherFile, bool $overrideExisting = false): void
    {
        if (!is_file($otherFile)) {
            throw new DotenvException("File not found: $otherFile");
        }

        $content = file_get_contents($otherFile);
        $otherLines = $this->parser->parse($content ?: '');

        foreach ($otherLines as $line) {
            if ($line['type'] !== 'entry') continue;

            $key = $line['key'];
            $value = $line['value'];

            if ($this->has($key) && !$overrideExisting) {
                continue;
            }

            $this->set($key, $value);
        }
    }

    public function preview(): string
    {
        return implode("\n", array_map(
            static fn ($line) => $line['raw'],
            $this->lines
        ));
    }

    public function after(string $key): self
    {
        $this->pendingAfter = $key;
        $this->pendingBefore = null;
        return $this;
    }

    public function before(string $key): self
    {
        $this->pendingBefore = $key;
        $this->pendingAfter  = null;
        return $this;
    }

    public function top(): self
    {
        $this->pendingPosition = 'top';
        $this->pendingAfter = null;
        $this->pendingBefore = null;
        return $this;
    }

    public function bottom(): self
    {
        $this->pendingPosition = 'bottom';
        $this->pendingAfter = null;
        $this->pendingBefore = null;
        return $this;
    }

    public function spacing(int $lines): self
    {
        $this->pendingSpacing = $lines;
        return $this;
    }

    private function resetPending(): void
    {
        $this->pendingAfter = null;
        $this->pendingBefore = null;
        $this->pendingPosition = null;
        $this->pendingSpacing = 0;
    }

    private function resolvePosition(
        ?string $position,
        ?string $after,
        ?string $before,
        ?array $positionArray = null
    ): int {
        if ($after !== null) {
            return $this->findAfterKey($after);
        }

        if ($before !== null) {
            return $this->findBeforeKey($before);
        }

        if ($positionArray !== null) {
            if (isset($positionArray['after'])) {
                return $this->findAfterKey($positionArray['after']);
            }
            if (isset($positionArray['before'])) {
                return $this->findBeforeKey($positionArray['before']);
            }
        }

        if ($position === 'top') {
            return 0;
        }

        return count($this->lines);
    }

    private function findAfterKey(string $key): int
    {
        foreach ($this->lines as $i => $line) {
            if ($line['type'] === 'entry' && $line['key'] === $key) {
                return $i + 1;
            }
        }
        return count($this->lines);
    }

    private function findBeforeKey(string $key): int
    {
        foreach ($this->lines as $i => $line) {
            if ($line['type'] === 'entry' && $line['key'] === $key) {
                return $i;
            }
        }
        return count($this->lines);
    }
}

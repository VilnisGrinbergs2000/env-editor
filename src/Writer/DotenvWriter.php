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

    public function __construct(
        ?DotenvParser $parser = null,
        ?ValueFormatter $formatter = null
    ) {
        $this->parser = $parser ?? new DotenvParser();
        $this->formatter = $formatter ?? new ValueFormatter();
    }

    public function load(string $filepath): void
    {
        if (!is_file($filepath)) {
            throw new DotenvException("File not found: $filepath");
        }

        $this->filepath = $filepath;

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new DotenvException("Unable to read file: $filepath");
        }

        $this->lines = $this->parser->parse($content);
    }

    public function set(
        string $key,
        string $value,
        mixed $position = 'bottom',
        int $spacing = 0
    ): void {
        $formattedValue = $this->formatter->format($value);

        foreach ($this->lines as &$line) {
            if ($line['type'] === 'entry' && $line['key'] === $key) {
                $line['value'] = $formattedValue;
                $line['raw'] = "$key=$formattedValue";
                return;
            }
        }

        $newEntry = [
            'type'  => 'entry',
            'key'   => $key,
            'value' => $formattedValue,
            'raw'   => "$key=$formattedValue",
        ];

        $insertAt = $this->resolvePosition($position);

        while ($spacing > 0) {
            array_splice($this->lines, $insertAt, 0, [[
                'type' => 'comment',
                'raw'  => '',
            ]]);
            $insertAt++;
            $spacing--;
        }

        array_splice($this->lines, $insertAt, 0, [$newEntry]);
    }

    public function remove(string $key): void
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            static fn ($line): bool => !($line['type'] === 'entry' && $line['key'] === $key)
        ));
    }

    public function save(?string $filepath = null): void
    {
        $target = $filepath ?? $this->filepath;

        $output = implode("\n", array_map(
            static fn ($line): string => $line['raw'],
            $this->lines
        ));

        file_put_contents($target, $output);
    }

    public function all(): array
    {
        $values = [];

        foreach ($this->lines as $line) {
            if ($line['type'] === 'entry') {
                $values[$line['key']] = $line['value'];
            }
        }

        return $values;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->lines as $line) {
            if ($line['type'] === 'entry') {
                $result[$line['key']] = $line['value'];
            }
        }

        return $result;
    }

    public function import(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $this->set($key, (string) $value);
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
        $missing = [];

        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }

            if (!$this->has($key)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public function backup(string $backupPath): void
    {
        if (!isset($this->filepath)) {
            throw new DotenvException('No file loaded to backup.');
        }

        if (!copy($this->filepath, $backupPath)) {
            throw new DotenvException("Failed to backup to $backupPath");
        }
    }

    public function restore(string $backupPath): void
    {
        if (!is_file($backupPath)) {
            throw new DotenvException("Backup file not found: $backupPath");
        }

        if (!isset($this->filepath)) {
            throw new DotenvException('No original file path set to restore to.');
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
        if ($content === false) {
            throw new DotenvException("Unable to read file: $otherFile");
        }

        $otherLines = $this->parser->parse($content);

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
            if (!array_key_exists($key, $current)) {
                $diff['missing_in_current'][$key] = $value;
            } elseif ($current[$key] !== $value) {
                $diff['changed'][$key] = [
                    'current' => $current[$key],
                    'other'   => $value,
                ];
            }
        }

        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $other)) {
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
        if ($content === false) {
            throw new DotenvException("Unable to read file: $otherFile");
        }

        $otherLines = $this->parser->parse($content);

        foreach ($otherLines as $line) {
            if ($line['type'] !== 'entry') {
                continue;
            }

            $key = $line['key'];
            $value = $line['value'];

            if ($this->has($key) && !$overrideExisting) {
                continue;
            }

            $this->set($key, $value);
        }
    }

    public function preview(?string $filepath = null): string
    {
        $target = $filepath ?? ($this->filepath ?? null);

        $output = implode("\n", array_map(
            static fn ($line): string => $line['raw'],
            $this->lines
        ));

        if ($target === null) {
            return $output;
        }

        return $output;
    }

    private function resolvePosition(mixed $position): int
    {
        if ($position === 'top') {
            return 0;
        }

        if ($position === 'bottom') {
            return count($this->lines);
        }

        if (is_array($position)) {
            if (isset($position['after'])) {
                return $this->findAfterKey($position['after']);
            }

            if (isset($position['before'])) {
                return $this->findBeforeKey($position['before']);
            }
        }

        return count($this->lines);
    }

    private function findAfterKey(string $key): int
    {
        foreach ($this->lines as $index => $line) {
            if ($line['type'] === 'entry' && $line['key'] === $key) {
                return $index + 1;
            }
        }

        return count($this->lines);
    }

    private function findBeforeKey(string $key): int
    {
        foreach ($this->lines as $index => $line) {
            if ($line['type'] === 'entry' && $line['key'] === $key) {
                return $index;
            }
        }

        return count($this->lines);
    }
}

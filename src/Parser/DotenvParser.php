<?php
declare(strict_types=1);

namespace VilnisGr\EnvEditor\Parser;

class DotenvParser
{
    public function parse(string $contents): array
    {
        $lines = explode("\n", $contents);
        $parsed = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if($trimmed === '' || str_starts_with($trimmed, '#')) {
                $parsed[] = [
                    'type' => 'comment',
                    'raw' => $line
                ];
                continue;
            }

            if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/i', $line, $matches)) {
                $parsed[] = [
                    'type'  => 'entry',
                    'key'   => $matches[1],
                    'value' => $matches[2],
                    'raw'   => $line
                ];
                continue;
            }

            $parsed[] = [
                'type' => 'raw',
                'raw' => $line
            ];
        }

        return $parsed;
    }
}
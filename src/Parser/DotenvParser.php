<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Parser;

use VilnisGr\EnvEditor\Exceptions\DotenvException;

class DotenvParser
{
    private const string REGEX_EXPORT          = '/^\s*export\s+/i';
    private const string REGEX_KEY             = '/^\s*([^\s=]+)\s*=\s*/';
    private const string REGEX_KEY_VALID       = '/^[A-Za-z0-9_.:-]+$/';
    private const string REGEX_INLINE_COMMENT  = '/\s+#.*$/';
    private ?array $multiline                  = null;

    /**
     * @return array<int, array<string,mixed>>
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        $lines     = explode("\n", $contents);
        $parsed    = [];

        foreach ($lines as $rawLine) {
            if ($this->multiline !== null) {
                $this->multiline['value'] .= "\n" . $rawLine;
                $this->multiline['raw']   .= "\n" . $rawLine;

                if ($this->endsWithClosingQuote($rawLine, $this->multiline['quote'])) {
                    $parsed[]  = $this->finalizeMultiline($this->multiline);
                    $this->multiline = null;
                }

                continue;
            }

            if ($rawLine === '') {
                $parsed[] = [
                    'type' => 'comment',
                    'raw'  => '',
                ];
                continue;
            }

            if (str_starts_with(ltrim($rawLine), '#')) {
                $parsed[] = [
                    'type' => 'comment',
                    'raw'  => $rawLine,
                ];
                continue;
            }

            $line = $rawLine;

            if (preg_match(self::REGEX_EXPORT, $line)) {
                $line = preg_replace(self::REGEX_EXPORT, '', $line) ?? $line;
            }

            if (preg_match(self::REGEX_KEY, $line, $match)) {
                $key = $match[1];

                if (!preg_match(self::REGEX_KEY_VALID, $key)) {
                    throw new DotenvException("Invalid .env key: $key");
                }

                $valueStart = strlen($match[0]);
                $valuePart = substr($line, $valueStart);

                $meta = $this->parseValuePart($valuePart);

                if ($meta['quoted']) {
                    if ($meta['complete']) {
                        $parsed[] = [
                            'type'    => 'entry',
                            'key'     => $key,
                            'value'   => $this->unquote($meta['value'], $meta['quote']),
                            'comment' => $meta['comment'],
                            'raw'     => $rawLine,
                        ];
                    } else {
                        $this->multiline = [
                            'key'     => $key,
                            'value'   => $meta['value'],
                            'quote'   => $meta['quote'],
                            'comment' => $meta['comment'],
                            'raw'     => $rawLine,
                        ];
                    }
                } else {
                    $parsed[] = [
                        'type'    => 'entry',
                        'key'     => $key,
                        'value'   => $meta['value'],
                        'comment' => $meta['comment'],
                        'raw'     => $rawLine,
                    ];
                }

                continue;
            }

            $parsed[] = [
                'type' => 'raw',
                'raw'  => $rawLine,
            ];
        }

        if ($this->multiline !== null) {
            throw new DotenvException("Unterminated multiline value for key {$this->multiline['key']}");
        }

        return $parsed;
    }

    /**
     * @return array{
     *   value: string,
     *   comment: string,
     *   quote: ?string,
     *   complete: bool,
     *   quoted: bool
     * }
     */
    private function parseValuePart(string $valuePart): array
    {
        $raw = $valuePart;
        $trimLeft = ltrim($raw);

        if ($trimLeft === '') {
            return [
                'value'    => '',
                'comment'  => '',
                'quote'    => null,
                'complete' => true,
                'quoted'   => false,
            ];
        }

        $first = $trimLeft[0];

        if ($first === '"' || $first === "'") {
            $quote = $first;
            $len = strlen($trimLeft);

            $closingPos = null;
            for ($i = 1; $i < $len; $i++) {
                if ($trimLeft[$i] === $quote && !$this->isEscaped($trimLeft, $i)) {
                    $closingPos = $i;
                    break;
                }
            }

            if ($closingPos !== null) {
                $valueWithQuotes = substr($trimLeft, 0, $closingPos + 1);
                $rest = substr($trimLeft, $closingPos + 1);

                $comment = '';
                if (preg_match(self::REGEX_INLINE_COMMENT, $rest, $m)) {
                    $comment = $m[0];
                }

                return [
                    'value'    => $valueWithQuotes,
                    'comment'  => $comment,
                    'quote'    => $quote,
                    'complete' => true,
                    'quoted'   => true,
                ];
            }

            return [
                'value'    => rtrim($trimLeft),
                'comment'  => '',
                'quote'    => $quote,
                'complete' => false,
                'quoted'   => true,
            ];
        }

        $valueTrim = rtrim($raw);
        $comment = '';

        if (preg_match(self::REGEX_INLINE_COMMENT, $valueTrim, $m)) {
            $comment = $m[0];
            $valueTrim = rtrim(substr($valueTrim, 0, -strlen($m[0])));
        }

        return [
            'value'    => $valueTrim,
            'comment'  => $comment,
            'quote'    => null,
            'complete' => true,
            'quoted'   => false,
        ];
    }


    private function isEscaped(string $str, int $pos): bool
    {
        $backslashes = 0;
        $i = $pos - 1;

        while ($i >= 0 && $str[$i] === '\\') {
            $backslashes++;
            $i--;
        }

        return ($backslashes % 2) === 1;
    }

    private function endsWithClosingQuote(string $line, string $quote): bool
    {
        $i = strlen($line) - 1;

        while ($i >= 0 && ctype_space($line[$i])) {
            $i--;
        }

        if ($i < 0 || $line[$i] !== $quote) {
            return false;
        }

        $backslashes = 0;
        $j = $i - 1;

        while ($j >= 0 && $line[$j] === '\\') {
            $backslashes++;
            $j--;
        }

        return ($backslashes % 2) === 0;
    }

    private function unquote(string $str, string $quote): string
    {
        $str = ltrim($str);

        if ($str === '') {
            return $str;
        }

        if ($str[0] === $quote) {
            $str = substr($str, 1);
        }

        $len = strlen($str);
        $i = $len - 1;

        while ($i >= 0 && ctype_space($str[$i])) {
            $i--;
        }

        if ($i >= 0 && $str[$i] === $quote) {
            $str = substr($str, 0, $i);
        }

        if ($quote === '"') {
            $str = str_replace(
                ['\\"', '\\n', '\\r', '\\t'],
                ['"', "\n", "\r", "\t"],
                $str
            );
        } else {
            $str = str_replace("\\'", "'", $str);
        }

        return $str;
    }

    /**
     * @param array<string,mixed> $m
     * @return array<string,mixed>
     */
    private function finalizeMultiline(array $m): array
    {
        $value = $m['value'];

        $value = ltrim($value, "\n");

        $value = $this->unquote($value, $m['quote']);

        return [
            'type'    => 'entry',
            'key'     => $m['key'],
            'value'   => $value,
            'comment' => $m['comment'],
            'raw'     => $m['raw'],
        ];
    }
}

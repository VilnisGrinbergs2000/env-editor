<?php

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Tests\Writer;

use PHPUnit\Framework\TestCase;
use VilnisGr\EnvEditor\Writer\DotenvWriter;

class DotenvWriterTest extends TestCase
{
    private string $file;
    private string $otherFile;
    private string $backupFile;

    protected function setUp(): void
    {
        $this->file = __DIR__ . '/main.env';
        $this->otherFile = __DIR__ . '/other.env';
        $this->backupFile = __DIR__ . '/backup.env';

        file_put_contents($this->file, implode("\n", [
            'APP_NAME=MyApp',
            'APP_ENV=production',
            'DB_HOST=localhost',
        ]));

        file_put_contents($this->otherFile, implode("\n", [
            'APP_NAME=MyApp',
            'APP_ENV=staging',
            'DB_USER=root',
        ]));
    }

    protected function tearDown(): void
    {
        foreach ([$this->file, $this->otherFile, $this->backupFile] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    public function testToArray(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $arr = $writer->toArray();

        $this->assertSame([
            'APP_NAME' => 'MyApp',
            'APP_ENV'  => 'production',
            'DB_HOST'  => 'localhost',
        ], $arr);
    }

    public function testImport(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->import([
            'FOO' => 'bar',
            'APP_ENV' => 'dev'
        ]);

        $this->assertTrue($writer->has('FOO'));
        $this->assertTrue($writer->has('APP_ENV'));

        $arr = $writer->toArray();
        $this->assertSame('dev', $arr['APP_ENV']);
    }

    public function testHas(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $this->assertTrue($writer->has('APP_NAME'));
        $this->assertFalse($writer->has('MISSING'));
    }

    public function testMissingKeys(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $missing = $writer->missingKeys(['APP_ENV', 'DB_USER', 'SECRET']);

        $this->assertSame(['DB_USER', 'SECRET'], array_values($missing));
    }

    public function testBackupAndRestore(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->backup($this->backupFile);

        $this->assertFileExists($this->backupFile);

        $writer->set('APP_ENV', 'dev');
        $writer->save();

        $writer->restore($this->backupFile);

        $contents = file_get_contents($this->file);

        $this->assertTrue(str_contains($contents, 'APP_ENV=production'));
    }

    public function testDiff(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $diff = $writer->diff($this->otherFile);

        $this->assertSame([
            'missing_in_current' => ['DB_USER' => 'root'],
            'extra_in_current' => ['DB_HOST' => 'localhost'],
            'changed' => [
                'APP_ENV' => [
                    'current' => 'production',
                    'other'   => 'staging',
                ],
            ],
        ], $diff);
    }

    public function testMergeWithoutOverride(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->merge($this->otherFile, overrideExisting: false);

        $arr = $writer->toArray();

        $this->assertSame('production', $arr['APP_ENV']);
        $this->assertSame('root', $arr['DB_USER']);
    }

    public function testMergeWithOverride(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->merge($this->otherFile, overrideExisting: true);

        $arr = $writer->toArray();

        $this->assertSame('staging', $arr['APP_ENV']);
    }

    public function testPreview(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->set('NEWKEY', '123');

        $preview = $writer->preview();

        $this->assertTrue(str_contains($preview, 'NEWKEY=123'));
        $this->assertFalse(str_contains($preview, 'NONEXISTENT_KEY'));

        $content = file_get_contents($this->file);
        $this->assertFalse(str_contains($content, 'NEWKEY=123'));
    }
}

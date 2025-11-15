<?php /** @noinspection PhpUnitAnnotationToAttributeInspection */

declare(strict_types=1);

namespace VilnisGr\EnvEditor\Tests;

use PHPUnit\Framework\TestCase;
use VilnisGr\EnvEditor\Writer\DotenvWriter;
use VilnisGr\EnvEditor\Loader\EnvLoader;
use VilnisGr\EnvEditor\Schema\EnvSchema;
use VilnisGr\EnvEditor\Schema\EnvCast;
use VilnisGr\EnvEditor\Schema\EnvConfigFactory;

/**
 * @covers \VilnisGr\EnvEditor\Writer\DotenvWriter
 * @covers \VilnisGr\EnvEditor\Loader\EnvLoader
 * @covers \VilnisGr\EnvEditor\Schema\EnvSchema
 * @covers \VilnisGr\EnvEditor\Schema\EnvConfigFactory
 */
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

        // main.env
        file_put_contents($this->file, implode("\n", [
            "# Comment A",
            "",
            "APP_NAME=MyApp # inline",
            "APP_ENV=production",
            "",
            "# DB Section",
            "DB_HOST=localhost",
            "DB_PORT=3306",
            "DEBUG=true",
            "",
            "PRIVATE_KEY=\"-----BEGIN---\nLINE1\nLINE2\n-----END---\"",
        ]));

        // other.env
        file_put_contents($this->otherFile, implode("\n", [
            "APP_NAME=MyApp",
            "APP_ENV=staging",
            "DB_USER=root",
            "REDIS_HOST=127.0.0.1",
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

    public function testLoadAndParse(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $arr = $writer->toArray();

        $this->assertSame('MyApp', $arr['APP_NAME']);
        $this->assertSame('production', $arr['APP_ENV']);
        $this->assertSame('localhost', $arr['DB_HOST']);
    }

    public function testInlineCommentsArePreserved(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $preview = $writer->preview();
        $this->assertStringContainsString("# inline", $preview);
    }

    public function testMultilineValues(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $arr = $writer->toArray();

        $this->assertTrue(isset($arr['PRIVATE_KEY']));
        $this->assertStringContainsString("LINE1", $arr['PRIVATE_KEY']);
        $this->assertStringContainsString("LINE2", $arr['PRIVATE_KEY']);
    }

    public function testFluentInsertAfterWithSpacing(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->after('DB_HOST')->spacing(2)->set('DB_PORT_EXTRA', '3307');

        $preview = $writer->preview();

        $this->assertStringContainsString("DB_PORT_EXTRA=3307", $preview);

        $this->assertMatchesRegularExpression(
            "/DB_HOST=localhost\n\n\nDB_PORT_EXTRA/",
            $preview
        );
    }

    public function testInsertTopAndBottom(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->top()->set('FIRST', '1');
        $writer->bottom()->set('LAST', '9');

        $preview = $writer->preview();

        $this->assertTrue(str_starts_with($preview, "FIRST=1"));
        $this->assertTrue(str_ends_with($preview, "LAST=9"));
    }

    public function testRemoveKey(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->remove('DB_HOST');
        $preview = $writer->preview();

        $this->assertFalse(str_contains($preview, 'DB_HOST'));
    }

    public function testImportExport(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->import([
            'A' => '1',
            'B' => '2'
        ]);

        $arr = $writer->toArray();

        $this->assertSame('1', $arr['A']);
        $this->assertSame('2', $arr['B']);
    }

    public function testHasMissingKeys(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $this->assertTrue($writer->has('APP_ENV'));
        $missing = $writer->missingKeys(['APP_ENV', 'XYZ']);

        $this->assertSame(['XYZ'], $missing);
    }

    public function testBackupRestore(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->backup($this->backupFile);
        $this->assertFileExists($this->backupFile);

        $writer->set('APP_ENV', 'dev');
        $writer->save();

        $writer->restore($this->backupFile);

        $this->assertStringContainsString('production', (string) file_get_contents($this->file));
    }

    public function testDiff(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $diff = $writer->diff($this->otherFile);

        $this->assertArrayHasKey('missing_in_current', $diff);
        $this->assertArrayHasKey('extra_in_current', $diff);
        $this->assertArrayHasKey('changed', $diff);

        $this->assertSame('root', $diff['missing_in_current']['DB_USER']);
        $this->assertSame('localhost', $diff['extra_in_current']['DB_HOST']);
        $this->assertSame('staging', $diff['changed']['APP_ENV']['other']);
    }

    public function testMergeNoOverride(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->merge($this->otherFile);
        $arr = $writer->toArray();

        $this->assertSame('production', $arr['APP_ENV']);
        $this->assertSame('root', $arr['DB_USER']);
    }

    public function testMergeOverride(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->merge($this->otherFile, overrideExisting: true);
        $arr = $writer->toArray();

        $this->assertSame('staging', $arr['APP_ENV']);
    }

    public function testPreviewDoesNotSave(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->set('TEMP', '123');

        $preview = $writer->preview();
        $this->assertStringContainsString("TEMP=123", $preview);

        $content = (string) file_get_contents($this->file);
        $this->assertFalse(str_contains($content, 'TEMP='));
    }

    public function testAtomicSaveAndNonAtomic(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $writer->set('X', '1');
        $writer->save();

        $this->assertStringContainsString('X=1', (string) file_get_contents($this->file));

        $writer->set('Y', '2');
        $writer->save(atomic: false);

        $this->assertStringContainsString('Y=2', (string) file_get_contents($this->file));
    }

    public function testLoader(): void
    {
        $loader = new EnvLoader($this->file);
        $loader->load(true);

        $this->assertSame('MyApp', getenv('APP_NAME'));
        $this->assertSame('production', $_ENV['APP_ENV']);
        $this->assertSame('production', $_SERVER['APP_ENV']);
    }

    public function testEnvSchemaValidateAndCast(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $schema = EnvSchema::make()
            ->required('APP_NAME', 'APP_ENV', 'DB_HOST', 'DB_PORT')
            ->optional('DEBUG', 'false')
            ->cast('DB_PORT', 'int')
            ->cast('DEBUG', 'bool');

        $typed = $schema->validate($writer);

        $this->assertArrayHasKey('DB_PORT', $typed);
        $this->assertArrayHasKey('DEBUG', $typed);

        $this->assertIsInt($typed['DB_PORT']);
        $this->assertSame(3306, $typed['DB_PORT']);

        $this->assertIsBool($typed['DEBUG']);
        $this->assertTrue($typed['DEBUG']);
    }

    public function testEnvCastEnumAndArray(): void
    {
        $enum = EnvCast::apply('production', 'enum:' . TestEnvEnum::class);
        $this->assertInstanceOf(TestEnvEnum::class, $enum);
        $this->assertSame(TestEnvEnum::Production, $enum);

        $arr = EnvCast::apply('127.0.0.1,10.0.0.1', 'array');
        $this->assertSame(['127.0.0.1', '10.0.0.1'], $arr);

        $empty = EnvCast::apply('', 'array');
        $this->assertSame([], $empty);
    }

    public function testEnvConfigFactoryBuildsDtoFromSchemaAndWriter(): void
    {
        $writer = new DotenvWriter();
        $writer->load($this->file);

        $schema = EnvSchema::make()
            ->required('APP_NAME', 'APP_ENV', 'DB_PORT', 'DEBUG')
            ->cast('APP_ENV', 'enum:' . TestEnvEnum::class)
            ->cast('DB_PORT', 'int')
            ->cast('DEBUG', 'bool');

        $factory = new EnvConfigFactory($schema, $writer);

        $config = $factory->make(TestEnvConfig::class);

        $this->assertInstanceOf(TestEnvConfig::class, $config);

        $this->assertSame('MyApp', $config->APP_NAME);
        $this->assertSame(TestEnvEnum::Production, $config->APP_ENV);
        $this->assertSame(3306, $config->DB_PORT);
        $this->assertTrue($config->DEBUG);
    }
}

enum TestEnvEnum: string
{
    case Local = 'local';
    case Production = 'production';
}

readonly class TestEnvConfig
{
    public function __construct(
        public string      $APP_NAME,
        public TestEnvEnum $APP_ENV,
        public int         $DB_PORT,
        public bool        $DEBUG,
    ) {
    }
}

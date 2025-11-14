EnvEditor
=========
Modern PHP 8.3+ library for safe and programmatic editing of .env files.
Preserves formatting, variable order, and comments while providing tools for diffing, merging, validating, and managing environment files.

------------------------------------------------------------
Features
------------------------------------------------------------
- Edit .env files programmatically
- Preserve comments, spacing, and order
- Insert variables at top, bottom, before or after another key
- Add blank line spacing automatically
- Remove keys safely
- Merge environment files (.env.example → .env)
- Generate diffs between env files
- Backup and restore .env files
- Convert to arrays or import arrays
- Preview changes without saving (dry-run)
- Load .env variables into runtime (putenv, $_ENV, $_SERVER)
- Fully PSR-4 compliant
- Tested with PHPUnit

Requires PHP 8.3 or newer.

------------------------------------------------------------
Installation
------------------------------------------------------------
```
composer require vilnisGr/env-editor
```
------------------------------------------------------------
Basic Usage
------------------------------------------------------------
Load a .env file:
```
use Vilnis\EnvEditor\Writer\DotenvWriter;

$writer = new DotenvWriter();
$writer->load(__DIR__ . '/.env');
```
------------------------------------------------------------
Editing Variables
------------------------------------------------------------
Set or update:
```
$writer->set('APP_ENV', 'production');
$writer->save();
```
Insert after key:
```
$writer->set('DB_PASS', 'secret', ['after' => 'DB_USER']);
$writer->save();
```
Insert with spacing:
```
$writer->set('REDIS_HOST', '127.0.0.1', position: 'bottom', spacing: 1);
```
Remove variable:
```
$writer->remove('APP_DEBUG');
$writer->save();
```
------------------------------------------------------------
Exporting & Importing
------------------------------------------------------------
Convert to array:
```
$arr = $writer->toArray();
```
Import from array:
```
$writer->import([
    'APP_ENV' => 'local',
    'CACHE_DRIVER' => 'redis',
]);
```
------------------------------------------------------------
Backup & Restore
------------------------------------------------------------
```
$writer->backup('.env.bak');

$writer->set('APP_ENV', 'dev');
$writer->save();
```
// Restore original
```
$writer->restore('.env.bak');
```
------------------------------------------------------------
Diff Environment Files
------------------------------------------------------------
```
$diff = $writer->diff('.env.example');
print_r($diff);
```
Example diff output:
missing_in_current: DB_USER => root
extra_in_current: REDIS_PORT => 6379
changed: APP_ENV (production → staging)

------------------------------------------------------------
Merge Environment Files
------------------------------------------------------------
Merge without overwrite:
```
$writer->merge('.env.example', overrideExisting: false);
```
Merge with overwrite:
```
$writer->merge('.env.example', overrideExisting: true);
```
------------------------------------------------------------
Preview (Dry Run)
------------------------------------------------------------
Preview changes without saving:
```
echo $writer->preview();
```
------------------------------------------------------------
Environment Loader
------------------------------------------------------------
```
use Vilnis\EnvEditor\Loader\EnvLoader;

$loader = new EnvLoader(__DIR__ . '/.env');
$loader->load(); // or load(true) to override
```
------------------------------------------------------------
Testing
------------------------------------------------------------
```
vendor/bin/phpunit
```
------------------------------------------------------------
License
------------------------------------------------------------
MIT License — free for personal and commercial use.

------------------------------------------------------------
Support
------------------------------------------------------------
Open issues or PRs on GitHub.

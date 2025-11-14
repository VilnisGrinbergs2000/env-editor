EnvEditor
=========
Modern PHP 8.3+ library for safe, structured, and programmatic editing of .env files.  
Preserves formatting, variable order, comments, and provides advanced tools for diffing, merging, validating, and managing environment files.

------------------------------------------------------------
Features
------------------------------------------------------------
- Edit .env files programmatically
- Preserve comments, spacing, and original order
- Insert variables at:
  - top of file
  - bottom of file
  - before another key
  - after another key
- Add automatic blank-line spacing
- Remove keys safely
- Check if key exists
- Export .env to an associative array
- Import arrays into .env
- Detect missing keys
- Backup and restore .env files
- Generate diffs between environment files
- Merge .env.example → .env (with optional override)
- Preview changes without saving (dry-run mode)
- Load .env variables into PHP runtime (putenv, $_ENV, $_SERVER)
- Fully PSR-4 compliant
- Tested with PHPUnit

Requires PHP 8.3 or newer.

------------------------------------------------------------
Installation
------------------------------------------------------------
composer require vilnisgr/env-editor

------------------------------------------------------------
Basic Usage
------------------------------------------------------------
Load a .env file:
```php
use VilnisGr\EnvEditor\Writer\DotenvWriter;

$writer = new DotenvWriter();
$writer->load(__DIR__ . '/.env');
```

------------------------------------------------------------
Editing Variables
------------------------------------------------------------

--- Set or update a value:
```php
$writer->set('APP_ENV', 'production');
$writer->save();
```
--- Insert AFTER another key:
```php
$writer->set('DB_PASS', 'secret', ['after' => 'DB_USER'], spacing: 1);
```
--- Insert BEFORE another key:
```php
$writer->set('NEW_BEFORE', 'ok', ['before' => 'CACHE_DRIVER']);
```
--- Insert at TOP:
```php
$writer->set('FIRST_ENTRY', 'start', position: 'top');
```
--- Insert at BOTTOM:
```php
$writer->set('LAST_ENTRY', 'end', position: 'bottom');
```
--- Remove a variable:
```php
$writer->remove('APP_DEBUG');
$writer->save();
```

------------------------------------------------------------
Exporting & Importing
------------------------------------------------------------

--- Export to array:
```php
$arr = $writer->toArray();
```
--- Import values from an array:
```php
$writer->import([
    'APP_ENV' => 'local',
    'LOG_CHANNEL' => 'stack'
]);
```
--- Update using export + import (real-world usage):
```php
$config = $writer->toArray();
$config['APP_ENV'] = 'testing';
$writer->import($config)->save();
```

------------------------------------------------------------
Key Checking
------------------------------------------------------------
Check if key exists:
```php
$writer->has('APP_NAME'); // true or false
```

------------------------------------------------------------
Missing Keys Detection
------------------------------------------------------------
```php
$missing = $writer->missingKeys([
    'APP_NAME',
    'APP_ENV',
    'DB_PASS',
]);

print_r($missing);
// Returns: [ 'DB_PASS' ]
```

------------------------------------------------------------
Backup & Restore
------------------------------------------------------------
```php
$writer->backup('.env.bak');

$writer->set('BROKEN', 'xxx');
$writer->save();

// Restore original
$writer->restore('.env.bak');
```

------------------------------------------------------------
Diff Environment Files
------------------------------------------------------------
```php
$diff = $writer->diff('.env.example');
print_r($diff);
```
Example output:
missing_in_current:
  DB_PASS => secret
extra_in_current:
  CACHE_DRIVER => file
changed:
  APP_ENV:
    current => production
    other   => staging

------------------------------------------------------------
Merge Environment Files
------------------------------------------------------------

--- Merge without overwriting:
```php
$writer->merge('.env.example', overrideExisting: false);
```
--- Merge WITH overwriting:
```php
$writer->merge('.env.example', overrideExisting: true);
```

------------------------------------------------------------
Preview (Dry Run)
------------------------------------------------------------
Preview changes without writing to disk:
```php
echo $writer->preview();
```

------------------------------------------------------------
Environment Loader
------------------------------------------------------------
Load .env variables into PHP runtime:
```php
use VilnisGr\EnvEditor\Loader\EnvLoader;

$loader = new EnvLoader(__DIR__ . '/.env');
```
--- Load without overriding existing variables:
```php
$loader->load();
```
--- Load WITH overriding:
```php
$loader->load(true);
```
Access values:
```php
getenv('APP_NAME');
$_ENV['APP_ENV'];
```

------------------------------------------------------------
Testing
------------------------------------------------------------
```php
vendor/bin/phpunit
```

------------------------------------------------------------
License
------------------------------------------------------------
MIT License — free for personal and commercial use.


------------------------------------------------------------
Support
------------------------------------------------------------
Open issues or pull requests on GitHub.

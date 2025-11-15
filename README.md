# EnvEditor
### Advanced **.env Parser**, **Writer**, **Schema Validator**, and **Config Factory** for PHP

<p>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-blue?style=for-the-badge"  alt=""/>
  <img src="https://img.shields.io/badge/Tested-100%25-success?style=for-the-badge"  alt=""/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge"  alt=""/>

</p>

EnvEditor is a **full-featured environment management toolkit** for modern PHP projects—  
designed to replace fragile string-based `.env` handling with a **structured, validated, type-safe**, and **developer-friendly** system.

This documentation covers every part of the library in depth:

🔹 **Writer** (editing `.env` files with order control & multiline support)  
🔹 **Reader / Parser** (accurately parses any real-world dotenv file)  
🔹 **Formatter** (ensures valid dotenv formatting & escaping)  
🔹 **Schema Validator** (required/optional keys, casting, rules, grouping)  
🔹 **Rules Engine** (min/max/regex/length/in/custom)  
🔹 **Config Factory (DTO Generator)** for type-safe config objects  
🔹 **Vanilla PHP Integration Guide**  
🔹 **Full examples for every method**

---
# Features

| Feature Category       | Description                                                                                   |
|------------------------|-----------------------------------------------------------------------------------------------|
| **Parser**             | High-precision dotenv parser with strict key validation, escape handling, and export removal. |
| **Writer**             | Smart writer with ordering, spacing, top/bottom, before/after insertion.                      |
| **Multiline Support**  | Fully supports multiline values with proper quote detection and unescaping.                   |
| **Comments**           | Preserves inline comments and full line comments exactly as they appear.                      |
| **Atomic Saving**      | Safer writes using temp files to prevent corruption.                                          |
| **Casting**            | Built-in casting: `array`, `json`, `bool`, `float`, `int`.                                    |
| **Enum Casting**       | Convert values into backed PHP enums automatically.                                           |
| **Validation Rules**   | `min`, `max`, `regex`, `in`, `length`, custom callbacks.                                      |
| **Schema Groups**      | Namespaced schemas: e.g., `group('DB_', fn($db) => … )`.                                      |
| **DTO Factory**        | Build typed objects from env via `EnvConfigFactory`.                                          |
| **Env Loader**         | Loads validated env values into `putenv()`, `$_ENV`, and `$_SERVER`.                          |
| **Test Coverage**      | Comprehensive test suite validating all core features.                                        |

---

# Installation

```
composer require vilnisgr/env-editor
```

---

# Usage Guide

```php
use VilnisGr\EnvEditor\Writer\DotenvWriter;

$writer = new DotenvWriter();
$writer->load('.env');

$writer->set('APP_ENV', 'production');
$writer->save();
```
---

# 1. DOTENV WRITER – FULL DOCUMENTATION & TUTORIAL


## 1.1 Creating the writer

```php
$writer = new DotenvWriter();
$writer->load('.env');
```

---

## 1.2 Setting / updating a key

```php
$writer->set('APP_NAME', 'MyApp');
```

If the key exists, it's updated.  
If not, it's added at the bottom.

---

## 1.3 Insert AFTER another key

```php
$writer->after('DB_HOST')->set('DB_PORT', '3306');
```

---

## 1.4 Insert BEFORE a key

```php
$writer->before('APP_ENV')->set('APP_NAME', 'NewName');
```

---

## 1.5 Spacing (adds blank lines before inserting)

```php
$writer->after('APP_ENV')->spacing(2)->set('API_KEY', 'xyz');
```

---

## 1.6 Insert at TOP or BOTTOM

```php
$writer->top()->set('HEADER', '1');
$writer->bottom()->set('FOOTER', '9');
```

---

## 1.7 Array-style positioning

```php
$writer->set('TOKEN', 'abc', position: ['after' => 'API_KEY'], spacing: 1);
```

---

## 1.8 Remove a key

```php
$writer->remove('DB_USER');
```

---

## 1.9 Import multiple keys

```php
$writer->import([
    'A' => '1',
    'B' => '2'
]);
```

---

## 1.10 Export env to array

```php
$array = $writer->toArray();
```

---

## 1.11 Save (atomic & non-atomic)

```php
$writer->save();                  // atomic
$writer->save(atomic: false);     // simple write
```

---

## 1.12 Preview without saving

```php
echo $writer->preview();
```

---

## 1.13 Backup & Restore

```php
$writer->backup('.env.bak');
$writer->restore('.env.bak');
```

---

## 1.14 Diff

```php
$diff = $writer->diff('.env.example');
```

Returns:

```
[
  'missing_in_current' => [...],
  'extra_in_current' => [...],
  'changed' => [
      KEY => ['current' => X, 'other' => Y]
  ]
]
```

---

## 1.15 Merge

```php
$writer->merge('.env.example', overrideExisting: false);
```
overrideExisting:
- **false** (default): keep the value already in your current .env file.
- **true:** replace the value in your current .env with the value from the other file.

Example:  
Current .env has : APP_ENV=production  
other file has :   APP_ENV=local

**merge(..., false)** -> keeps the value from your current .env ("production")  
**merge(..., true)**  -> replaces it with the value from the other file ("local")

---

# 2. DOTENV PARSER

## Inline comments preserved

```
APP_NAME=MyApp # comment
```

## export` keyword support

```
export APP_KEY=123
```

## Multiline values

```
PRIVATE_KEY="-----BEGIN-----
LINE1
LINE2
-----END-----"
```

## Escapes recognized

- \n
- \r
- \t
- \\
- \"

## Key validation

Key must match:

```
/^[A-Za-z0-9_.:-]+$/
```

---

# 3. VALUE FORMATTER

Ensures correct `.env` formatting.

## Auto-quotes values containing:

- spaces
- tabs
- newline
- `#`

## Escape rules

```
\ → \
" → \"  


 → \n  

 → \r  
	 → \t
```

---

# 4. ENV LOADER
Loads parsed env into:

- putenv()
- \$_ENV
- \$_SERVER

### Example:

```php
$loader = new EnvLoader('.env');
$loader->load(true);
```

---

# 5. ENV SCHEMA – COMPLETE VALIDATOR

```php
$schema = EnvSchema::make()
    ->required('APP_NAME', 'DB_HOST')
    ->optional('DEBUG', 'false')
    ->int('DB_PORT')
    ->bool('DEBUG');
```

---

## Type Casts

| Method       | Result                  |
|--------------|-------------------------|
| `int()`      | converts "123" → 123    |
| `float()`    | "1.5" → 1.5             |
| `bool()`     | "true" → true           |
| `array()`    | "a,b,c" → ['a','b','c'] |
| `json()`     | '{"a":1}' → ['a'=>1]    |
| `castEnum()` | "A" → Enum::A           |

---

## Validation Rules

```php
$rules = $schema->rules();

$rules->min('PORT', 1000);
$rules->max('PORT', 9000);
$rules->regex('API_KEY', '/^[A-Z]+$/');
$rules->in('APP_ENV', ['local','prod']);
$rules->length('TOKEN', 32);
```

---

## Schema Groups

```php
$schema->group('DB_', function (EnvSchema $db) {
    $db->required('HOST', 'USER');
    $db->optional('PORT', '3306')->int('PORT');
});
```

Generates:

- DB_HOST
- DB_USER
- DB_PORT

---

# 6. CONFIG FACTORY – DTO BUILDER

```php
$factory = new EnvConfigFactory($schema, $writer);
$config = $factory->make(AppConfig::class);
```


```php
final class AppConfig
{
    public function __construct(
      public string $appName,
      public int $dbPort,
      public bool $debug
    ) {}
}
```

---

# 7. VANILLA PHP INTEGRATION

## AppConfig.php (DTO class)
```php
final class AppConfig
{
    public function __construct(
        public string $appName,  // maps to APP_NAME
        public int    $dbPort,   // maps to DB_PORT
        public bool   $debug     // maps to DEBUG
    ) {}
}
```
## Loading .env and creating config
```php
require 'vendor/autoload.php';

use VilnisGr\EnvEditor\Writer\DotenvWriter;
use VilnisGr\EnvEditor\Schema\EnvSchema;
use VilnisGr\EnvEditor\Schema\EnvConfigFactory;

$writer = new DotenvWriter();
$writer->load('.env');

$schema = EnvSchema::make()
    ->required('APP_NAME')
    ->int('DB_PORT')
    ->bool('DEBUG');

$config = (new EnvConfigFactory($schema, $writer))
    ->make(AppConfig::class);
```
## Retrieving env values (3 different ways)

----------------------------------------------------
**1. From the writer (raw .env, uncasted)**
```php
$env = $writer->toArray();  
echo $env['APP_NAME'];
```
----------------------------------------------------
**2. From the typed DTO**
```php
echo $config->appName;   // string  
echo $config->dbPort;    // int  
echo $config->debug;     // bool  
```
----------------------------------------------------
**3. From PHP runtime environment (after EnvLoader)**
```php
use VilnisGr\EnvEditor\Loader\EnvLoader;

$loader = new EnvLoader('.env');
$loader->load(true); // true = overwrite system env vars

echo getenv('APP_NAME');   // string
echo $_ENV['APP_NAME'];    // string
echo $_SERVER['APP_NAME']; // string
```
## When to use which?

- **Writer** → for manipulating the .env file itself.
- **Config DTO** → for application configuration, type-safe, validated.
- **getenv() / $_ENV** → when you need global system-level env variables.

---

# 8. FULL LIST OF ALL PUBLIC METHODS

## Writer Methods

| Method          | Description                                           |
|-----------------|-------------------------------------------------------|
| `load()`        | Load a `.env` file into the writer.                   |
| `set()`         | Add or update a key with optional position & spacing. |
| `remove()`      | Remove a key from the file.                           |
| `save()`        | Save the file (atomic or non-atomic).                 |
| `preview()`     | Get the output without writing to disk.               |
| `toArray()`     | Export all env entries as `key => value`.             |
| `import()`      | Import an associative array of values.                |
| `has()`         | Check if a key exists.                                |
| `missingKeys()` | Return keys missing from the file.                    |
| `backup()`      | Create a backup of the env file.                      |
| `restore()`     | Restore from a backup.                                |
| `diff()`        | Compare against another env file.                     |
| `merge()`       | Merge another env file into the current one.          |
| `after()`       | Insert the next `set()` after a specific key.         |
| `before()`      | Insert the next `set()` before a specific key.        |
| `top()`         | Insert the next `set()` at the top.                   |
| `bottom()`      | Insert the next `set()` at the bottom.                |
| `spacing()`     | Add blank lines before inserting with `set()`.        |


## Schema Methods

| Method       | Description                                             |
|--------------|---------------------------------------------------------|
| `required()` | Define keys that must appear in the env.                |
| `optional()` | Define optional keys with default values.               |
| `cast()`     | Cast a key using a specific type (`int`, `bool`, etc.). |
| `bool()`     | Convenience method: cast to boolean.                    |
| `int()`      | Convenience method: cast to integer.                    |
| `float()`    | Cast to float.                                          |
| `array()`    | Cast comma-separated lists to arrays.                   |
| `json()`     | Cast values to decoded JSON arrays.                     |
| `castEnum()` | Cast value into a PHP backed enum.                      |
| `rules()`    | Get the rules object for adding validation rules.       |
| `group()`    | Create grouped/namespaced schemas (e.g., `DB_HOST`).    |
| `validate()` | Validate & cast writer values based on schema.          |


## Rules Methods

| Method       | Description                                      |
|--------------|--------------------------------------------------|
| `add()`      | Add a custom validation rule (callable).         |
| `validate()` | Validate a value against all rules for a key.    |
| `min()`      | Require numeric value >= minimum.                |
| `max()`      | Require numeric value <= maximum.                |
| `in()`       | Require value to be in a fixed allow-list.       |
| `regex()`    | Require value to match a regular expression.     |
| `length()`   | Require string length to be between `[min,max]`. |
| `export()`   | Export all rules for use in grouped schemas.     |


## ConfigFactory Methods

| Method   | Description                                                           |
|----------|-----------------------------------------------------------------------|
| `make()` | Build a typed configuration DTO from validated environment variables. |

---

# License, Author & Support
EnvEditor - Complete .env Toolkit for PHP  
Maintained by: VilnisGrinbergs2000  
License: MIT (Free for commercial and private use)  
Source Code & Issues: https://github.com/VilnisGrinbergs2000/env-editor  


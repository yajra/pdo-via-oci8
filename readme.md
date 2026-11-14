# Oracle PDO Userspace Driver for OCI8

## PDO via Oci8

[![Continuous Integration](https://github.com/yajra/pdo-via-oci8/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/yajra/pdo-via-oci8/actions/workflows/continuous-integration.yml)
[![Coverage](https://raw.githubusercontent.com/yajra/pdo-via-oci8/coverage-report/badges/coverage.svg)](https://github.com/yajra/pdo-via-oci8/actions/workflows/continuous-integration.yml)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/stable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/downloads)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/unstable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

The [yajra/pdo-via-oci8](https://github.com/yajra/pdo-via-oci8) package is a simple userspace driver for PDO that uses the tried and
tested [OCI8](http://php.net/oci8) functions instead of using the still experimental and not all that functional.
[PDO_OCI](http://www.php.net/manual/en/ref.pdo-oci.php) library.

**Please report any bugs you may find.**

- [Installation](#installation)
- [Credits](#credits)

## Installation

Add `yajra/laravel-pdo-via-oci8` as a requirement to composer.json:

```json
{
    "require": {
        "yajra/laravel-pdo-via-oci8": "2.*"
    }
}
```
And then run `composer update`

## PHP 8 Support

When using PHP 8, please use version 3: `"yajra/laravel-pdo-via-oci8": "3.*"`.

## Fetching BLOBs as streams

BLOBs are returned as strings by default. Enable `Oci8::ATTR_BLOB_AS_STREAM`
to receive seekable PHP streams instead:

```php
use Yajra\Pdo\Oci8;

$pdo = new Oci8($dsn, $username, $password, [
    Oci8::ATTR_BLOB_AS_STREAM => true,
]);

$stream = $pdo->query('SELECT content FROM documents WHERE id = 1')->fetchColumn();
if (is_resource($stream)) {
    fpassthru($stream);
    fclose($stream);
}
```

The flag can also be set with `$pdo->setAttribute()` before preparing a statement,
passed in `prepare()` options, or changed with `$statement->setAttribute()`.
An explicit `false` on a statement overrides the connection setting.

Only BLOB columns become streams; CLOB and NCLOB columns remain strings, and SQL
NULL remains `null`. Empty BLOBs produce empty streams. Streams are read-only and
read directly from the Oracle LOB on demand, without copying it into memory or a
temporary file. They support `fread()`, `fseek()`, `rewind()`, and `fstat()`, and
start at position zero. The stream retains the LOB and connection after the
statement is closed; keep the Oracle session and transaction valid until reading
is complete. Call `fclose()` to release the LOB locator when finished. In
`PDO::FETCH_BOTH`, the numeric and named keys refer to the same stream.

Memory usage depends on how much the caller reads at once. For large BLOBs, use
`fread()` in a loop, `fpassthru()`, or `stream_copy_to_stream()`;
`stream_get_contents()` without a length still allocates the full contents.

## Testing

There is a test suite (using `PHPUnit` with a version bigger than 6.x) on the `test` directory. If you want to
test (you must test your code!), create a table called `people` with two
columns:

1. `name` as `varchar2(50)`
2. `email` as `varchar2(30)`

And some environment variables:

1. `OCI_USER` with the database user name
2. `OCI_PWD` with the database password
3. `OCI_STR` with the database connection string

And then go to the `test` dir and run `PHPUnit` like:

```
phpunit --colors .
```
Example to get it up and running on docker DB container-registry.oracle.com/database/enterprise:12.2.0.1

    create pluggable database testpdb admin user oracle identified by system file_name_convert = ('/pdbseed/', '/testpdb01/');
    alter pluggable database testpdb open;

    ALTER SESSION SET CONTAINER=testpdb;

    CREATE TABLE person (name NVARCHAR2(50), email NVARCHAR2(30));

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [crazycodr/pdo-via-oci8](https://github.com/crazycodr/pdo-via-oci8)
- [ramsey/pdo_oci8](https://github.com/ramsey/pdo_oci8)
- To all contributors of this project

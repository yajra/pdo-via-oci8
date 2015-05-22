# PDO Userspace Driver for Oracle (oci8)

###PDO via Oci8

[![Build Status](https://travis-ci.org/yajra/pdo-via-oci8.png)](https://travis-ci.org/yajra/pdo-via-oci8) [![Latest Stable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/stable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8) [![Total Downloads](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/downloads)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8) [![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/unstable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8) [![License](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/license)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)

The [yajra/pdo-via-oci8](https://github.com/yajra/pdo-via-oci8) package is a simple userspace driver for PDO that uses the tried and
tested [OCI8](http://php.net/oci8) functions instead of using the still experimental and not all that functionnal
[PDO_OCI](http://www.php.net/manual/en/ref.pdo-oci.php) library.

**Please report any bugs you may find.**

- [Installation](#installation)
- [Credits](#credits)

###Installation

Add `yajra/laravel-pdo-via-oci8` as a requirement to composer.json:

```json
{
    "require": {
        "yajra/laravel-pdo-via-oci8": "~0.11"
    }
}
```
And then run `composer update`

### Change Logs
- **0.12.0**
	- added support for procedure returning a cursor

- **0.11.0**
	- Rename github package name to pdo-via-oci8 from laravel-pdo-via-oci8 making the package not specific to Laravel.
	- Sequence name can now be passed in the `lastInsertId` function. Table name that will be used by default will be based on the last table used on insert query.
	```php
	$pdo->lastInsertId(); // will use TABLE_ID_SEQ
	$pdo->lastInsertId('TABLE_SEQ'); // will use TABLE_SEQ
	```

###Credits

- [crazycodr/pdo-via-oci8](https://github.com/crazycodr/pdo-via-oci8)

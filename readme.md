# PDO Userspace Driver for Oracle (oci8)

###PDO via Oci8

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/stable.png)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8) [![Total Downloads](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/downloads.png)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8) [![Build Status](https://travis-ci.org/yajra/laravel-pdo-via-oci8.png)](https://travis-ci.org/yajra/laravel-pdo-via-oci8)

The [yajra/laravel-pdo-via-oci8](https://github.com/yajra/laravel-pdo-via-oci8) package is a simple userspace driver for PDO that uses the tried and
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
        "yajra/laravel-pdo-via-oci8": "~0.9"
    }
}
```
And then run `composer update`

***Note:***
lastInsertId function returns the current value of the sequence related to the table where record is inserted.
The sequence name should follow this format ```{$table}.'_'.{$column}.'_seq'``` for it to work properly.



###Credits

- [crazycodr/pdo-via-oci8](https://github.com/crazycodr/pdo-via-oci8)
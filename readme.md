## Oracle PDO Userspace Driver for OCI8

###PDO via Oci8

[![Build Status](https://img.shields.io/travis/yajra/pdo-via-oci8.svg)](https://travis-ci.org/yajra/pdo-via-oci8)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/stable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/downloads)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/unstable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/yajra/laravel-pdo-via-oci8/blob/master/LICENSE)


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
        "yajra/laravel-pdo-via-oci8": "1.*"
    }
}
```
And then run `composer update`

### Buy me a beer
<a href='https://pledgie.com/campaigns/29542'><img alt='Click here to lend your support to: PDO-via-OCI8 and make a donation at pledgie.com !' src='https://pledgie.com/campaigns/29542.png?skin_name=chrome' border='0' ></a>

### Change Logs
- **1.0.1**
    - PHP 7 fix for bindValue. PR #23 by @snelg.

- **1.0.0**
    - Change of namespace from yajra to Yajra (capital Y).
    - Enhance error dump with proper bindings.
    - Convert to PSR-2 & PSR-4 standard.
    - Safe refactoring of variable names.
    - Auto-saving of BLOB objects.

- **0.15.0**
    - Account for PDO::PARAM_INPUT_OUTPUT in bindParam.

- **0.14.0**
    - Add bindArray special non-pdo function for extensive support for oci_bind_array_by_name.

- **0.13.0**
    - Add support for oci_bind_array_by_name.

- **0.12.0**
    - Add support for procedure returning a cursor.

- **0.11.0**
    - Rename github package name to pdo-via-oci8 from laravel-pdo-via-oci8 making the package not specific to Laravel.
    - Sequence name can now be passed in the `lastInsertId` function. Table name that will be used by default will be based on the last table used on insert query.
    ```php
    $pdo->lastInsertId(); // will use TABLE_ID_SEQ
    $pdo->lastInsertId('TABLE_SEQ'); // will use TABLE_SEQ
    ```

### License

Licensed under the [MIT License](https://github.com/yajra/pdo-via-oci8/blob/master/LICENSE).

### Credits

- [crazycodr/pdo-via-oci8](https://github.com/crazycodr/pdo-via-oci8)
- [ramsey/pdo_oci8](https://github.com/ramsey/pdo_oci8)
- To all contributors of this project

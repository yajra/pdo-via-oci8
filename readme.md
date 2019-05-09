# Oracle PDO Userspace Driver for OCI8

## PDO via Oci8

[![Build Status](https://img.shields.io/travis/yajra/pdo-via-oci8.svg)](https://travis-ci.org/yajra/pdo-via-oci8)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/stable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/downloads)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-pdo-via-oci8/v/unstable)](https://packagist.org/packages/yajra/laravel-pdo-via-oci8)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)


The [yajra/pdo-via-oci8](https://github.com/yajra/pdo-via-oci8) package is a simple userspace driver for PDO that uses the tried and
tested [OCI8](http://php.net/oci8) functions instead of using the still experimental and not all that functionnal
[PDO_OCI](http://www.php.net/manual/en/ref.pdo-oci.php) library.

**Please report any bugs you may find.**

- [Installation](#installation)
- [Credits](#credits)

## Installation

Add `yajra/laravel-pdo-via-oci8` as a requirement to composer.json:

```json
{
    "require": {
        "yajra/laravel-pdo-via-oci8": "1.*"
    }
}
```
And then run `composer update`

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
Examle to get it up and running on docker DB container-registry.oracle.com/database/enterprise:12.2.0.1

    create pluggable database testpdb admin user oracle identified by system file_name_convert = ('/pdbseed/', '/testpdb01/');
    alter pluggable database testpdb open;

    ALTER SESSION SET CONTAINER=testpdb;

    CREATE TABLE person (name NVARCHAR2(50), email NVARCHAR2(30));

## Buy me a coffee
[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.me/yajra)
<a href='https://www.patreon.com/bePatron?u=4521203'><img alt='Become a Patron' src='https://s3.amazonaws.com/patreon_public_assets/toolbox/patreon.png' border='0' width='200px' ></a>

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [crazycodr/pdo-via-oci8](https://github.com/crazycodr/pdo-via-oci8)
- [ramsey/pdo_oci8](https://github.com/ramsey/pdo_oci8)
- To all contributors of this project

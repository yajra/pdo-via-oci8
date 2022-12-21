# PDO-VIA-OCI8 Changelog

## [UNRELEASED]

## v3.2.5 - 2022-12-21

- fix: Don't suppress exceptions when connecting #121
- fix https://github.com/yajra/laravel-oci8/issues/662

## v3.2.4 - 2022-06-12

- Fix #109 PHP8.1 support #110
- Sync type hints, return type and var names.

## v3.2.3 - 2022-06-11

- Fix Warning reset(): Calling reset() on an object is deprecated #104
- Fix yajra/laravel-oci8#692 
- Fix #108

## v3.2.2 - 2022-05-21

- Fix #106: return object collection instead of oci8 statement resource #107

## v3.2.1 - 2022-01-31

- Add missing property types and return types for PHP 8.1 #95

## v3.2.0 - 2022-01-31

- Fix #96, add method to get db resource.
- Add github action / tests for php8.1.

## v3.1.0 - 2022-01-15

- Added support for oci_set_call_timeout function for v3 (PHP 8 support) #93

## v3.0.0 - 2020-12-08

- Add PHP 8 support. [#83](https://github.com/yajra/pdo-via-oci8/pull/83)
- Fix #82
- Move tests to github actions.

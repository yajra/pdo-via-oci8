# PDO-VIA-OCI8 Changelog

## [UNRELEASED]

## v3.7.1 - 2025-01-20

- fix: Silence PHP8.4's deprecation warnings.

## v3.7.0 - 2025-01-04

- feat: Add Support oci_new_connect #150
- fix: Use oci_new_connect instead oci_connect #137

## v3.6.0 - 2024-12-17

- feat: add support for PHP8.4 #151

## v3.5.0 - 2024-11-28

- feat: Add options to fix ignore "expire within" error #148

## v3.4.4 - 2024-05-25

- fix: oci_new_collection implementation #139

## v3.4.3 - 2024-04-19

- fix: do not report password expiration error #138
- fix: yajra/laravel-oci8#839

## v3.4.2 - 2024-01-05

- fix: #135
- fix: oci_connect() warning errors. (Revert code lines as v2.0) #136

## v3.4.1 - 2023-11-30

- fix: OCICollection class name reference to match PHP 8 naming convention #134
- fix: #133

## v3.4.0 - 2023-06-15

- feat: Added SQLT_BOL to bind type #128
- fix: #127

## v3.3.1 - 2023-03-15

- fix: getAttribute returning array instead of null #124
- fix #123

## v3.3.0 - 2023-02-20

- feat: stringify support in pdo via oci 8 #122

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

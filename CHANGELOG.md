#PDO-VIA-OCI8 Change Log

- **1.2.1**
- Change regex replacement pattern in prepare. PR #38, credits to @jjware.
- Fix #37.
- Refactor regex replacement using preg_replace_callback.

- **1.2.0**
- Implement closeCursor. 
- Add oci8 connection options getter.

- **1.1.1**
- 3rd argument in Statement::fetchAll(), $ctorArgs, should allow null. 
- PR #34, credits to @nhowell.

- **1.1.0**
    - Add support for CLOB data types. 
    - PR #31, credits to @Tylerian.

- **1.0.5**
    - fetchAll also checks if fetch() returning object.
    - PR #30, credits to @apit

- **1.0.4**
    - Fix non-FETCH_COLUMN queries.
    - PR #29, credits to @snelg

- **1.0.3**
    - Function fetchAll() bug fix. Fix #27
    - PR #28, credits to @dkochnov.

- **1.0.2**
    - Parse DSN - check charset option in connection string and clean "PDO style".
    - PR #26, credits to @eisberg.

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

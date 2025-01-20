<?php

namespace Yajra\Pdo\Oci8;

use Exception;
use OCILob;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionException;
use Yajra\Pdo\Oci8;
use Yajra\Pdo\Oci8\Exceptions\Oci8Exception;

/**
 * Oci8 Statement class to mimic the interface of the PDOStatement class
 * This class extends PDOStatement but overrides all of its methods. It does
 * this so that instanceof check and type-hinting of existing code will work
 * seamlessly.
 */
class Statement extends PDOStatement
{
    /**
     * Statement handler.
     *
     * @var resource
     */
    private $sth;

    /**
     * PDO Oci8 connection.
     *
     * @var Oci8
     */
    private Oci8 $connection;

    /**
     * Flag to convert LOB to string or not.
     *
     * @var bool
     */
    private bool $returnLobs = true;

    /**
     * Statement options.
     *
     * @var array
     */
    private array $options = [];

    /**
     * Fetch mode selected via setFetchMode().
     *
     * @var int
     */
    private int $fetchMode = PDO::FETCH_BOTH;

    /**
     * Column number for PDO::FETCH_COLUMN fetch mode.
     *
     * @var int
     */
    private int $fetchColNo = 0;

    /**
     * Class name for PDO::FETCH_CLASS fetch mode.
     *
     * @var string
     */
    private string $fetchClassName = '\ArrayIterator';

    /**
     * Constructor arguments for PDO::FETCH_CLASS.
     *
     * @var array
     */
    private array $fetchCtorArgs = [];

    /**
     * Object reference for PDO::FETCH_INTO fetch mode.
     *
     * @var ?object
     */
    private ?object $fetchIntoObject = null;

    /**
     * PDO result set.
     *
     * @var array
     */
    private array $results = [];

    /**
     * Lists of binding values.
     *
     * @var array
     */
    private array $bindings = [];

    /**
     * Lists of LOB variables.
     *
     * @var array
     */
    private array $blobObjects = [];

    /**
     * Lists of LOB object binding values.
     *
     * @var array
     */
    private array $blobBindings = [];

    /**
     * Constructor.
     *
     * @param  resource  $sth  Statement handle created with oci_parse()
     * @param  Oci8  $connection  The Pdo_Oci8 object for this statement
     * @param  array  $options  Options for the statement handle
     *
     * @throws Oci8Exception
     */
    public function __construct($sth, Oci8 $connection, array $options = [])
    {
        if (strtolower(get_resource_type($sth)) != 'oci8 statement') {
            throw new Oci8Exception(
                'Resource expected of type oci8 statement; '.get_resource_type($sth).' received instead'
            );
        }

        $this->sth = $sth;
        $this->connection = $connection;
        $this->options = $options;

        $fetchMode = $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
        if ($fetchMode) {
            $this->setFetchMode($fetchMode);
        }
    }

    /**
     * Set the default fetch mode for this statement.
     *
     * @link https://php.net/manual/en/pdostatement.setfetchmode.php
     *
     * @param  int  $mode  <p>
     *                     The fetch mode must be one of the PDO::FETCH_* constants.
     *                     </p>
     * @param  null  $className
     * @param  ?string  ...$params  <p> Constructor arguments. </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    #[\ReturnTypeWillChange]
    public function setFetchMode($mode, $className = null, ...$params): bool
    {
        $modeArg = $params;
        // See which fetch mode we have
        switch ($mode) {
            case PDO::ATTR_DEFAULT_FETCH_MODE:
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_NUM:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_OBJ:
                $this->fetchMode = $mode;
                $this->fetchColNo = 0;
                $this->fetchClassName = '\stdClass';
                $this->fetchCtorArgs = [];
                $this->fetchIntoObject = null;
                break;
            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
                $this->fetchMode = $mode;
                $this->fetchColNo = 0;
                $this->fetchClassName = '\stdClass';
                if ($modeArg) {
                    $this->fetchClassName = $modeArg;
                }
                // $this->fetchCtorArgs   = $ctorArgs;
                $this->fetchIntoObject = null;
                break;
            case PDO::FETCH_INTO:
                if (! is_object($modeArg)) {
                    throw new Oci8Exception(
                        '$modeArg must be instance of an object'
                    );
                }
                $this->fetchMode = $mode;
                $this->fetchColNo = 0;
                $this->fetchClassName = '\stdClass';
                $this->fetchCtorArgs = [];
                $this->fetchIntoObject = $modeArg;
                break;
            case PDO::FETCH_COLUMN:
                $this->fetchMode = $mode;
                $this->fetchColNo = (int) $modeArg;
                $this->fetchClassName = '\stdClass';
                $this->fetchCtorArgs = [];
                $this->fetchIntoObject = null;
                break;
            default:
                throw new Oci8Exception('Requested fetch mode is not supported by this implementation');
        }

        return true;
    }

    /**
     * Binds a column to a PHP variable.
     *
     * @param  mixed  $column  Number of the column (1-indexed) or name of the
     *                         column in the result set. If using the column name, be aware that the
     *                         name should match the case of the column, as returned by the driver.
     * @param  mixed  $var
     * @param  int  $type
     * @param  int|null  $maxLength  A hint for pre-allocation.
     * @param  mixed|null  $driverOptions
     * @return bool TRUE on success or FALSE on failure.
     *
     * @todo Implement this functionality by creating a table map of the
     *       variables passed in here, and, when iterating over the values
     *       of the query or fetching rows, assign data from each column
     *       to their respective variable in the map.
     */
    public function bindColumn(
        string|int $column,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int|null $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        throw new Oci8Exception('bindColumn has not been implemented');
    }

    /**
     * Binds a value to a parameter.
     *
     * @param  int|string  $parameter  Parameter identifier. For a prepared statement
     *                                 using named placeholders, this will be a parameter name of the form
     *                                 :name. For a prepared statement using question mark placeholders, this
     *                                 will be the 1-indexed position of the parameter.
     * @param  mixed  $variable  The value to bind to the parameter.
     * @param  int  $dataType  Explicit data type for the parameter using the
     *                         PDO::PARAM_* constants.
     * @return bool TRUE on success or FALSE on failure.
     */
    public function bindValue(int|string $parameter, mixed $variable, int $dataType = PDO::PARAM_STR): bool
    {
        return $this->bindParam($parameter, $variable, $dataType);
    }

    /**
     * Binds a parameter to the specified variable name.
     *
     * @param  int|string  $parameter  Parameter identifier. For a prepared statement
     *                                 using named placeholders, this will be a parameter name of the form
     *                                 :name. For a prepared statement using question mark placeholders, this
     *                                 will be the 1-indexed position of the parameter.
     * @param  mixed  $variable  Name of the PHP variable to bind to the SQL
     *                           statement parameter.
     * @param  int  $dataType  Explicit data type for the parameter using the
     *                         PDO::PARAM_* constants.
     * @param  int|null  $maxLength  Length of the data type. To indicate that a
     *                               parameter is an OUT parameter from a stored procedure, you must
     *                               explicitly set the length.
     * @param  array  $options  [optional]
     * @return bool TRUE on success or FALSE on failure.
     *
     * @todo Map PDO datatypes to oci8 datatypes and implement support for
     *   datatypes and length.
     */
    public function bindParam(
        int|string $parameter,
        mixed &$variable,
        int $dataType = PDO::PARAM_STR,
        ?int $maxLength = null,
        mixed $options = null
    ): bool {
        // strip INOUT type for oci
        $dataType &= ~PDO::PARAM_INPUT_OUTPUT;

        // Replace the first @oci8param to a pseudo named parameter
        if (is_numeric($parameter)) {
            $parameter = ':p'.intval($parameter - 1);
        }

        // Adapt the type
        switch ($dataType) {
            case PDO::PARAM_BOOL:
                $ociType = SQLT_INT;
                break;

            case PDO::PARAM_NULL:
                $ociType = SQLT_CHR;
                break;

            case PDO::PARAM_INT:
                $ociType = SQLT_INT;
                break;

            case PDO::PARAM_STR:
                $ociType = SQLT_CHR;
                break;

            case PDO::PARAM_LOB:
                $ociType = OCI_B_BLOB;

                $this->blobBindings[$parameter] = $variable;

                $variable = $this->connection->getNewDescriptor();
                $variable->writeTemporary($this->blobBindings[$parameter], OCI_TEMP_BLOB);

                $this->blobObjects[$parameter] = &$variable;
                break;

            case PDO::PARAM_STMT:
                $ociType = OCI_B_CURSOR;

                // Result sets require a cursor
                $variable = $this->connection->getNewCursor();
                break;

            case SQLT_NTY:
                $ociType = SQLT_NTY;

                if (strtoupper(get_class($variable)) != 'OCICOLLECTION') {
                    $schema = $options['schema'] ?? null;
                    $type_name = $options['type_name'] ?? '';

                    if (! $type_name) {
                        throw new Oci8Exception('Type name is required for custom types');
                    }

                    // set params required to use custom type.
                    $variable = $this->connection->getNewCollection($type_name, $schema);
                }
                break;

            case SQLT_CLOB:
                $ociType = OCI_B_CLOB;

                $this->blobBindings[$parameter] = $variable;

                $variable = $this->connection->getNewDescriptor();
                $variable->writeTemporary($this->blobBindings[$parameter], OCI_TEMP_CLOB);

                $this->blobObjects[$parameter] = &$variable;
                break;

            case SQLT_BOL:
                $ociType = SQLT_BOL;
                break;

            default:
                $ociType = SQLT_CHR;
                break;
        }

        if (is_array($variable)) {
            return $this->bindArray($parameter, $variable, count($variable), $maxLength, $ociType);
        }

        $this->bindings[] = &$variable;

        if ($maxLength === null) {
            // PDOStatement->bindParam(param: int|string, &var: mixed, [type: int = PDO::PARAM_STR], [maxLength: int = null], [driverOptions: mixed = null])
            // function oci_bind_by_name($statement, $bv_name, &$variable, $maxlength = -1, $type = SQLT_CHR) {}
            $maxLength = -1;
        }

        return oci_bind_by_name($this->sth, $parameter, $variable, $maxLength, $ociType);
    }

    /**
     * Special non-PDO function that binds an array parameter to the specified variable name.
     *
     * @see  http://php.net/manual/en/function.oci-bind-array-by-name.php
     *
     * @param  int|string  $parameter  The Oracle placeholder.
     * @param  array  $variable  An array.
     * @param  int  $maxTableLength  Sets the maximum length both for incoming and result arrays.
     * @param  int|null  $maxItemLength  Sets maximum length for array items.
     *                                   If not specified or equals to -1, oci_bind_array_by_name() will find
     *                                   the longest element in the incoming array and will use it as the maximum
     *                                   length.
     * @param  int  $type  Explicit data type for the parameter using the
     * @return bool TRUE on success or FALSE on failure.
     */
    public function bindArray(
        int|string $parameter,
        array &$variable,
        int $maxTableLength,
        ?int $maxItemLength = null,
        int $type = SQLT_CHR
    ): bool {
        $this->bindings[] = $variable;

        return oci_bind_array_by_name($this->sth, $parameter, $variable, $maxTableLength, $maxItemLength, $type);
    }

    /**
     * Returns the number of rows affected by the last executed statement.
     *
     * @return int The number of rows.
     */
    public function rowCount(): int
    {
        return oci_num_rows($this->sth);
    }

    /**
     * Returns a single column from the next row of a result set.
     *
     * @param  int|null  $colNumber  0-indexed number of the column you wish to retrieve
     *                               from the row. If no value is supplied, it fetches the first column.
     * @return string Returns a single column in the next row of a result set.
     */
    public function fetchColumn(?int $colNumber = null): string
    {
        $this->setFetchMode(PDO::FETCH_COLUMN, $colNumber);

        try {
            return $this->fetch();
        } catch (ReflectionException $e) {
        }
    }

    /**
     * Fetches the next row from a result set.
     *
     * @param  int|null  $fetchMode  Controls how the next row will be returned to
     *                               the caller. This value must be one of the PDO::FETCH_* constants,
     *                               defaulting to value of PDO::ATTR_DEFAULT_FETCH_MODE (which defaults to
     *                               PDO::FETCH_BOTH).
     * @param  int  $cursorOrientation  For a PDOStatement object representing a
     *                                  scrollable cursor, this value determines which row will be returned to
     *                                  the caller. This value must be one of the PDO::FETCH_ORI_* constants,
     *                                  defaulting to PDO::FETCH_ORI_NEXT. To request a scrollable cursor for
     *                                  your PDOStatement object, you must set the PDO::ATTR_CURSOR attribute
     *                                  to PDO::CURSOR_SCROLL when you prepare the SQL statement with
     *                                  PDO::prepare.
     * @param  int  $cursorOffset  [optional]
     * @return mixed The return value of this function on success depends on the
     *               fetch type. In all cases, FALSE is returned on failure.
     *
     * @throws \ReflectionException
     * @throws \ReflectionException
     *
     * @todo Implement cursorOrientation and cursorOffset
     */
    public function fetch(
        ?int $fetchMode = null,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        // If not fetchMode was specified, used the default value of or the mode
        // set by the last call to setFetchMode()
        if ($fetchMode === null) {
            $fetchMode = $this->fetchMode;
        }

        // Convert array keys (or object properties) to lowercase
        $toLowercase = ($this->getAttribute(PDO::ATTR_CASE) == PDO::CASE_LOWER);
        // Convert null value to empty string
        $nullToString = ($this->getAttribute(PDO::ATTR_ORACLE_NULLS) == PDO::NULL_TO_STRING);
        // Convert empty string to null
        $nullEmptyString = ($this->getAttribute(PDO::ATTR_ORACLE_NULLS) == PDO::NULL_EMPTY_STRING);

        $stringifyFetch = $this->getStringify();

        // Determine the fetch mode
        switch ($fetchMode) {
            case PDO::FETCH_BOTH:
                $rs = oci_fetch_array($this->sth); // Fetches both; nice!
                if ($rs === false) {
                    return false;
                }
                if ($toLowercase) {
                    $rs = array_change_key_case($rs);
                }
                if ($this->returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value)) {
                            $rs[$field] = $this->loadLob($value);
                        }
                    }
                }

                return $rs;

            case PDO::FETCH_ASSOC:
                $rs = oci_fetch_assoc($this->sth);
                if ($rs === false) {
                    return false;
                }
                if ($toLowercase) {
                    $rs = array_change_key_case($rs);
                }
                if ($this->returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value)) {
                            $rs[$field] = $this->loadLob($value);
                        }
                    }
                }

                return $rs;

            case PDO::FETCH_NUM:
                $rs = oci_fetch_row($this->sth);
                if ($rs === false) {
                    return false;
                }
                if ($this->returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value)) {
                            $rs[$field] = $this->loadLob($value);
                        }
                    }
                }

                return $rs;

            case PDO::FETCH_COLUMN:
                $rs = oci_fetch_row($this->sth);
                $colNo = $this->fetchColNo;
                if (is_array($rs) && array_key_exists($colNo, $rs)) {
                    $value = $rs[$colNo];
                    if (is_object($value)) {
                        return $this->loadLob($value);
                    }

                    return $value;
                } else {
                    return false;
                }

            case PDO::FETCH_OBJ:
            case PDO::FETCH_INTO:
            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
                $rs = oci_fetch_assoc($this->sth);
                if ($rs === false) {
                    return false;
                }
                if ($toLowercase) {
                    $rs = array_change_key_case($rs);
                }

                if ($fetchMode === PDO::FETCH_INTO) {
                    if (is_object($this->fetchIntoObject)) {
                        $object = $this->fetchIntoObject;
                    } else {
                        // Object to set into has not been set
                        return false;
                    }
                } else {
                    if ($fetchMode === PDO::FETCH_OBJ) {
                        $className = '\stdClass';
                        $ctorargs = [];
                    } else {
                        $className = $this->fetchClassName;
                        $ctorargs = $this->fetchCtorArgs ? array_values($this->fetchCtorArgs) : [];
                    }

                    $object = $fetchMode === PDO::FETCH_CLASS ? (new ReflectionClass(
                        $className
                    ))->newInstanceWithoutConstructor() : new $className(...$ctorargs);
                }

                // Format recordsets values depending on options
                foreach ($rs as $field => $value) {
                    // convert null to empty string
                    if (is_null($value) && $nullToString) {
                        $rs[$field] = '';
                    }

                    // convert empty string to null
                    if (empty($rs[$field]) && $nullEmptyString) {
                        $rs[$field] = null;
                    }

                    // convert LOB to string
                    if ($this->returnLobs && is_object($value)) {
                        $ociFieldIndex = is_int($field) ? $field : array_search($field, array_keys($rs));
                        // oci field type index is base 1.
                        if (oci_field_type($this->sth, $ociFieldIndex + 1) == 'ROWID') {
                            throw new Oci8Exception(
                                'ROWID output is not yet supported. Please use ROWIDTOCHAR(ROWID) function as workaround.'
                            );
                        } else {
                            $object->$field = $this->loadLob($value);
                        }
                    } else {
                        $ociFieldIndex = is_int($field) ? $field : array_search($field, array_keys($rs));
                        if ($stringifyFetch) {
                            $object->$field = $value;
                        } else {
                            if (oci_field_type($this->sth, $ociFieldIndex + 1) == 'NUMBER') {
                                $object->$field = $this->castToNumeric($value);
                            } else {
                                $object->$field = $value;
                            }
                        }
                    }
                }

                if ($fetchMode === PDO::FETCH_CLASS && method_exists($object, '__construct')) {
                    $object->__construct(...$ctorargs);
                }

                return $object;
        }

        return false;
    }

    /**
     * Retrieve stringify boolean in attribute .
     *
     * @return bool The attribute value.
     */
    public function getStringify(): bool
    {
        if (is_array($this->getAttribute(PDO::ATTR_STRINGIFY_FETCHES)) && empty($this->getAttribute(PDO::ATTR_STRINGIFY_FETCHES))) {
            return true;
        } elseif ($this->getAttribute(PDO::ATTR_STRINGIFY_FETCHES)) {
            return true;
        } elseif (! $this->getAttribute(PDO::ATTR_STRINGIFY_FETCHES)) {
            return false;
        }

        return true;
    }

    /**
     * number value return as string from oracle.
     *
     * @param  $value
     * @return float|int|string
     */
    private function castToNumeric($value)
    {
        if (is_numeric($value)) {
            return $val = $value + 0;
        }

        return $value;
    }

    /**
     * Retrieve a statement attribute.
     *
     * @param  int  $attribute
     * @return mixed The attribute value.
     */
    public function getAttribute(int $attribute): mixed
    {
        if (isset($this->options[$attribute])) {
            return $this->options[$attribute];
        }

        return [];
    }

    /**
     * Load a LOB object value.
     *
     * @param  mixed  $lob
     * @return mixed
     */
    private function loadLob(mixed $lob): mixed
    {
        try {
            return $lob->load();
        } catch (Exception $e) {
            return $lob;
        }
    }

    /**
     * Returns an array containing all of the result set rows.
     *
     * @link https://php.net/manual/en/pdostatement.fetchall.php
     *
     * @param  int|null  $mode  [optional] <p>
     *                          Controls the contents of the returned array as documented in
     *                          <b>PDOStatement::fetch</b>.
     *                          Defaults to value of <b>PDO::ATTR_DEFAULT_FETCH_MODE</b>
     *                          (which defaults to <b>PDO::FETCH_BOTH</b>)
     *                          </p>
     *                          <p>
     *                          To return an array consisting of all values of a single column from
     *                          the result set, specify <b>PDO::FETCH_COLUMN</b>. You
     *                          can specify which column you want with the
     *                          <i>column-index</i> parameter.
     *                          </p>
     *                          <p>
     *                          To fetch only the unique values of a single column from the result set,
     *                          bitwise-OR <b>PDO::FETCH_COLUMN</b> with
     *                          <b>PDO::FETCH_UNIQUE</b>.
     *                          </p>
     *                          <p>
     *                          To return an associative array grouped by the values of a specified
     *                          column, bitwise-OR <b>PDO::FETCH_COLUMN</b> with
     *                          <b>PDO::FETCH_GROUP</b>.
     *                          </p>
     * @param  mixed  ...$args  <p>
     *                          Arguments of custom class constructor when the <i>fetch_style</i>
     *                          parameter is <b>PDO::FETCH_CLASS</b>.
     *                          </p>
     * @return array <b>PDOStatement::fetchAll</b> returns an array containing
     *               all of the remaining rows in the result set. The array represents each
     *               row as either an array of column values or an object with properties
     *               corresponding to each column name.
     *               </p>
     *               <p>
     *               Using this method to fetch large result sets will result in a heavy
     *               demand on system and possibly network resources. Rather than retrieving
     *               all of the data and manipulating it in PHP, consider using the database
     *               server to manipulate the result sets. For example, use the WHERE and
     *               ORDER BY clauses in SQL to restrict results before retrieving and
     *               processing them with PHP.
     *
     * @throws \ReflectionException
     */
    public function fetchAll($mode = PDO::FETCH_OBJ, $fetch_argument = null, ...$args): array
    {
        if (is_null($mode)) {
            $mode = $this->fetchMode;
        }

        $this->setFetchMode($mode, $args);

        $this->results = [];
        while ($row = $this->fetch()) {
            $mangledObj = get_mangled_object_vars((object) $row);

            if ((is_array($row) || is_object($row)) && is_resource(reset($mangledObj))) {
                $stmt = new self(reset($mangledObj), $this->connection, $this->options);
                $stmt->execute();
                $stmt->setFetchMode($mode, $args);
                while ($rs = $stmt->fetch()) {
                    $this->results[] = $rs;
                }
            } else {
                $this->results[] = $row;
            }
        }

        return $this->results;
    }

    /**
     * Executes a prepared statement.
     *
     * @param  array|null  $inputParams  An array of values with as many elements as
     *                                   there are bound parameters in the SQL statement being executed.
     * @return bool TRUE on success or FALSE on failure
     */
    public function execute(?array $inputParams = null): bool
    {
        $mode = OCI_COMMIT_ON_SUCCESS;
        if ($this->connection->inTransaction() || count($this->blobObjects) > 0) {
            $mode = OCI_DEFAULT;
        }

        // Set up bound parameters, if passed in.
        if (is_array($inputParams)) {
            foreach ($inputParams as $key => $value) {
                $this->bindings[] = $value;
                $this->bindParam($key, $inputParams[$key]);
            }
        }

        $result = @oci_execute($this->sth, $mode);

        // Save blob objects if set.
        if ($result && count($this->blobObjects) > 0) {
            foreach ($this->blobObjects as $param => $blob) {
                /* @var OCILob $blob */
                $blob->save($this->blobBindings[$param]);
            }
        }

        if (! $this->connection->inTransaction() && count($this->blobObjects) > 0) {
            $this->connection->commit();
        }

        if ($result != true) {
            $e = oci_error($this->sth);

            $message = '';
            $message = $message.'Error Code    : '.$e['code'].PHP_EOL;
            $message = $message.'Error Message : '.$e['message'].PHP_EOL;
            $message = $message.'Position      : '.$e['offset'].PHP_EOL;
            $message = $message.'Statement     : '.$e['sqltext'].PHP_EOL;
            $message = $message.'Bindings      : ['.$this->displayBindings().']'.PHP_EOL;

            throw new Oci8Exception($message, $e['code']);
        }

        return $result;
    }

    /**
     * Special not PDO function to format display of query bindings.
     *
     * @return string
     */
    private function displayBindings(): string
    {
        $bindings = [];
        foreach ($this->bindings as $binding) {
            if (is_object($binding)) {
                $bindings[] = get_class($binding);
            } elseif (is_array($binding)) {
                $bindings[] = 'Array';
            } else {
                $bindings[] = (string) $binding;
            }
        }

        return implode(',', $bindings);
    }

    /**
     * Fetches the next row and returns it as an object.
     *
     * @param  string|null  $className
     * @param  array|null  $ctorArgs
     * @return false|object
     *
     * @throws \ReflectionException
     */
    public function fetchObject(?string $className = null, ?array $ctorArgs = []): false|object
    {
        $this->setFetchMode(PDO::FETCH_CLASS, $className, $ctorArgs);

        return $this->fetch();
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the resource handle.
     * While this returns an error code, it merely emulates the action. If
     * there are no errors, it returns the success SQLSTATE code (00000).
     * If there are errors, it returns HY000. See errorInfo() to retrieve
     * the actual Oracle error code and message.
     *
     * @return string Error code
     */
    public function errorCode(): string
    {
        $error = $this->errorInfo();

        return $error[0];
    }

    /**
     * Fetch extended error information associated with the last operation on
     * the resource handle.
     *
     * @return array Array of error information about the last operation
     *               performed
     */
    public function errorInfo(): array
    {
        $e = oci_error($this->sth);

        if (is_array($e)) {
            return [
                'HY000',
                $e['code'],
                $e['message'],
            ];
        }

        return ['00000', null, null];
    }

    /**
     * Sets a statement attribute.
     *
     * @param  int  $attribute
     * @param  mixed  $value
     * @return true on success or FALSE on failure.
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        $this->options[$attribute] = $value;

        return true;
    }

    /**
     * Returns the number of columns in the result set.
     *
     * @return int The number of columns in the statement result set. If there
     *             is no result set, it returns 0.
     */
    public function columnCount(): int
    {
        return oci_num_fields($this->sth);
    }

    /**
     * Returns metadata for a column in a result set.
     * The array returned by this function is patterned after that
     * returned by \PDO::getColumnMeta(). It includes the following
     * elements:
     *     native_type
     *     driver:decl_type
     *     flags
     *     name
     *     table
     *     len
     *     precision
     *     pdo_type.
     *
     * @param  int  $column  The 0-indexed column in the result set.
     * @return array An associative array containing the above metadata values
     *               for a single column.
     */
    public function getColumnMeta(int $column): array
    {
        // Columns in oci8 are 1-based; add 1 if it's a number
        $column++;

        $meta = [];
        $meta['native_type'] = oci_field_type($this->sth, $column);
        $meta['driver:decl_type'] = oci_field_type_raw($this->sth, $column);
        $meta['flags'] = [];
        $meta['name'] = oci_field_name($this->sth, $column);
        $meta['table'] = null;
        $meta['len'] = oci_field_size($this->sth, $column);
        $meta['precision'] = oci_field_precision($this->sth, $column);
        $meta['pdo_type'] = null;
        $meta['is_null'] = oci_field_is_null($this->sth, $column);

        return $meta;
    }

    /**
     * Advances to the next rowset in a multi-rowset statement handle.
     *
     * @return bool TRUE on success or FALSE on failure.
     *
     * @throws Oci8Exception
     *
     * @todo Implement method
     */
    public function nextRowset(): bool
    {
        throw new Oci8Exception('setFetchMode has not been implemented');
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function closeCursor(): bool
    {
        return oci_free_cursor($this->sth);
    }

    /**
     * Dump a SQL prepared command.
     *
     * @return bool TRUE on success or FALSE on failure.
     *
     * @throws Oci8Exception
     *
     * @todo Implement method
     */
    public function debugDumpParams(): bool
    {
        throw new Oci8Exception('setFetchMode has not been implemented');
    }
}

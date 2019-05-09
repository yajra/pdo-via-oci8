<?php

namespace Yajra\Pdo\Oci8;

use PDO;
use PDOStatement;
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
     * @var \Yajra\Pdo\Oci8
     */
    private $connection;

    /**
     * Flag to convert LOB to string or not.
     *
     * @var bool
     */
    private $returnLobs = true;

    /**
     * Statement options.
     *
     * @var array
     */
    private $options = [];

    /**
     * Fetch mode selected via setFetchMode().
     *
     * @var int
     */
    private $fetchMode = PDO::FETCH_BOTH;

    /**
     * Column number for PDO::FETCH_COLUMN fetch mode.
     *
     * @var int
     */
    private $fetchColNo = 0;

    /**
     * Class name for PDO::FETCH_CLASS fetch mode.
     *
     * @var string
     */
    private $fetchClassName = '\stdClass';

    /**
     * Constructor arguments for PDO::FETCH_CLASS.
     *
     * @var array
     */
    private $fetchCtorArgs = [];

    /**
     * Object reference for PDO::FETCH_INTO fetch mode.
     *
     * @var object
     */
    private $fetchIntoObject = null;

    /**
     * PDO result set.
     *
     * @var array
     */
    private $results = [];

    /**
     * Lists of binding values.
     *
     * @var array
     */
    private $bindings = [];

    /**
     * Lists of LOB variables.
     *
     * @var array
     */
    private $blobObjects = [];

    /**
     * Lists of LOB object binding values.
     *
     * @var array
     */
    private $blobBindings = [];

    /**
     * Constructor.
     *
     * @param resource $sth Statement handle created with oci_parse()
     * @param Oci8 $connection The Pdo_Oci8 object for this statement
     * @param array $options Options for the statement handle
     * @throws Oci8Exception
     */
    public function __construct($sth, Oci8 $connection, array $options = [])
    {
        if (strtolower(get_resource_type($sth)) != 'oci8 statement') {
            throw new Oci8Exception(
                'Resource expected of type oci8 statement; '
                . (string) get_resource_type($sth) . ' received instead'
            );
        }

        $this->sth        = $sth;
        $this->connection = $connection;
        $this->options    = $options;

        $fetchMode = $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
        if ($fetchMode) {
            $this->setFetchMode($fetchMode);
        }
    }

    /**
     * Executes a prepared statement.
     *
     * @param array $inputParams An array of values with as many elements as
     *   there are bound parameters in the SQL statement being executed.
     * @throws Oci8Exception
     * @return bool TRUE on success or FALSE on failure
     */
    public function execute($inputParams = null)
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
                /* @var \OCI_Lob $blob */
                $blob->save($this->blobBindings[$param]);
            }
        }

        if (! $this->connection->inTransaction() && count($this->blobObjects) > 0) {
            $this->connection->commit();
        }

        if ($result != true) {
            $e = oci_error($this->sth);

            $message = '';
            $message = $message . 'Error Code    : ' . $e['code'] . PHP_EOL;
            $message = $message . 'Error Message : ' . $e['message'] . PHP_EOL;
            $message = $message . 'Position      : ' . $e['offset'] . PHP_EOL;
            $message = $message . 'Statement     : ' . $e['sqltext'] . PHP_EOL;
            $message = $message . 'Bindings      : [' . $this->displayBindings() . ']' . PHP_EOL;

            throw new Oci8Exception($message, $e['code']);
        }

        return $result;
    }

    /**
     * Special not PDO function to format display of query bindings.
     *
     * @return string
     */
    private function displayBindings()
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
     * Fetches the next row from a result set.
     *
     * @param int|null $fetchMode Controls how the next row will be returned to
     *   the caller. This value must be one of the PDO::FETCH_* constants,
     *   defaulting to value of PDO::ATTR_DEFAULT_FETCH_MODE (which defaults to
     *   PDO::FETCH_BOTH).
     * @param int $cursorOrientation For a PDOStatement object representing a
     *   scrollable cursor, this value determines which row will be returned to
     *   the caller. This value must be one of the PDO::FETCH_ORI_* constants,
     *  defaulting to PDO::FETCH_ORI_NEXT. To request a scrollable cursor for
     *   your PDOStatement object, you must set the PDO::ATTR_CURSOR attribute
     *   to PDO::CURSOR_SCROLL when you prepare the SQL statement with
     *   PDO::prepare.
     * @param int $cursorOffset [optional]
     * @return mixed The return value of this function on success depends on the
     *   fetch type. In all cases, FALSE is returned on failure.
     * @todo Implement cursorOrientation and cursorOffset
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
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
                $rs    = oci_fetch_row($this->sth);
                $colNo = (int) $this->fetchColNo;
                if (is_array($rs) && array_key_exists($colNo, $rs)) {
                    $value = $rs[$colNo];
                    if (is_object($value)) {
                        return $this->loadLob($value);
                    }

                    return $value;
                } else {
                    return false;
                }
                break;

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
                        $ctorargs  = [];
                    } else {
                        $className = $this->fetchClassName;
                        $ctorargs  = $this->fetchCtorArgs;
                    }

                    if ($ctorargs) {
                        $reflectionClass = new \ReflectionClass($className);
                        $object          = $reflectionClass->newInstanceArgs($ctorargs);
                    } else {
                        $object = new $className();
                    }
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
                            throw new Oci8Exception('ROWID output is not yet supported. Please use ROWIDTOCHAR(ROWID) function as workaround.');
                        } else {
                            $object->$field = $this->loadLob($value);
                        }
                    } else {
                        $object->$field = $value;
                    }
                }

                return $object;
        }

        return false;
    }

    /**
     * Load a LOB object value.
     *
     * @param mixed $lob
     * @return mixed
     */
    private function loadLob($lob)
    {
        try {
            return $lob->load();
        } catch (\Exception $e) {
            return $lob;
        }
    }

    /**
     * Binds a parameter to the specified variable name.
     *
     * @param string $parameter Parameter identifier. For a prepared statement
     *   using named placeholders, this will be a parameter name of the form
     *   :name. For a prepared statement using question mark placeholders, this
     *   will be the 1-indexed position of the parameter.
     * @param mixed $variable Name of the PHP variable to bind to the SQL
     *   statement parameter.
     * @param int $dataType Explicit data type for the parameter using the
     *   PDO::PARAM_* constants.
     * @param int $maxLength Length of the data type. To indicate that a
     *   parameter is an OUT parameter from a stored procedure, you must
     *   explicitly set the length.
     * @param array $options [optional]
     * @return bool TRUE on success or FALSE on failure.
     * @todo Map PDO datatypes to oci8 datatypes and implement support for
     *   datatypes and length.
     */
    public function bindParam($parameter, &$variable, $dataType = PDO::PARAM_STR, $maxLength = -1, $options = null)
    {
        // strip INOUT type for oci
        $dataType &= ~PDO::PARAM_INPUT_OUTPUT;

        // Replace the first @oci8param to a pseudo named parameter
        if (is_numeric($parameter)) {
            $parameter = ':p' . $parameter;
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

                $schema    = isset($options['schema']) ? $options['schema'] : '';
                $type_name = isset($options['type_name']) ? $options['type_name'] : '';

                // set params required to use custom type.
                $variable = $this->connection->getNewCollection($type_name, $schema);
                break;

            case SQLT_CLOB:
                $ociType = OCI_B_CLOB;

                $this->blobBindings[$parameter] = $variable;

                $variable = $this->connection->getNewDescriptor();
                $variable->writeTemporary($this->blobBindings[$parameter], OCI_TEMP_CLOB);

                $this->blobObjects[$parameter] = &$variable;
                break;

            default:
                $ociType = SQLT_CHR;
                break;
        }

        if (is_array($variable)) {
            return $this->bindArray($parameter, $variable, count($variable), $maxLength, $ociType);
        }

        $this->bindings[] = &$variable;

        return oci_bind_by_name($this->sth, $parameter, $variable, $maxLength, $ociType);
    }

    /**
     * Special non-PDO function that binds an array parameter to the specified variable name.
     *
     * @see  http://php.net/manual/en/function.oci-bind-array-by-name.php
     * @param string $parameter The Oracle placeholder.
     * @param array $variable An array.
     * @param int $maxTableLength Sets the maximum length both for incoming and result arrays.
     * @param int $maxItemLength Sets maximum length for array items.
     *                           If not specified or equals to -1, oci_bind_array_by_name() will find
     *                           the longest element in the incoming array and will use it as the maximum length.
     * @param int $type Explicit data type for the parameter using the
     * @return bool TRUE on success or FALSE on failure.
     */
    public function bindArray($parameter, &$variable, $maxTableLength, $maxItemLength = -1, $type = SQLT_CHR)
    {
        $this->bindings[] = $variable;

        return oci_bind_array_by_name($this->sth, $parameter, $variable, $maxTableLength, $maxItemLength, $type);
    }

    /**
     * Binds a column to a PHP variable.
     *
     * @param mixed $column Number of the column (1-indexed) or name of the
     *   column in the result set. If using the column name, be aware that the
     *   name should match the case of the column, as returned by the driver.
     * @param mixed $variable The PHP to which the column should be bound.
     * @param int $dataType Data type of the parameter, specified by the
     *   PDO::PARAM_* constants.
     * @param int $maxLength A hint for pre-allocation.
     * @param array $options [optional] Optional parameter(s) for the driver.
     * @throws Oci8Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement this functionality by creating a table map of the
     *       variables passed in here, and, when iterating over the values
     *       of the query or fetching rows, assign data from each column
     *       to their respective variable in the map.
     */
    public function bindColumn($column, &$variable, $dataType = null, $maxLength = -1, $options = null)
    {
        throw new Oci8Exception('bindColumn has not been implemented');
    }

    /**
     * Binds a value to a parameter.
     *
     * @param string $parameter Parameter identifier. For a prepared statement
     *   using named placeholders, this will be a parameter name of the form
     *   :name. For a prepared statement using question mark placeholders, this
     *   will be the 1-indexed position of the parameter.
     * @param mixed $variable The value to bind to the parameter.
     * @param int $dataType Explicit data type for the parameter using the
     *   PDO::PARAM_* constants.
     * @return bool TRUE on success or FALSE on failure.
     */
    public function bindValue($parameter, $variable, $dataType = PDO::PARAM_STR)
    {
        return $this->bindParam($parameter, $variable, $dataType);
    }

    /**
     * Returns the number of rows affected by the last executed statement.
     *
     * @return int The number of rows.
     */
    public function rowCount()
    {
        return oci_num_rows($this->sth);
    }

    /**
     * Returns a single column from the next row of a result set.
     *
     * @param int $colNumber 0-indexed number of the column you wish to retrieve
     *   from the row. If no value is supplied, it fetches the first column.
     * @return string Returns a single column in the next row of a result set.
     */
    public function fetchColumn($colNumber = null)
    {
        $this->setFetchMode(PDO::FETCH_COLUMN, $colNumber);

        return $this->fetch();
    }

    /**
     * Returns an array containing all of the result set rows.
     *
     * @param int $fetchMode Controls the contents of the returned array as
     *   documented in PDOStatement::fetch.
     * @param mixed $fetchArgument This argument has a different meaning
     *   depending on the value of the fetchMode parameter.
     * @param array $ctorArgs [optional] Arguments of custom class constructor
     *   when the fetch_style parameter is PDO::FETCH_CLASS.
     * @return array Array containing all of the remaining rows in the result
     *   set. The array represents each row as either an array of column values
     *   or an object with properties corresponding to each column name.
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = [])
    {
        if (is_null($fetchMode)) {
            $fetchMode = $this->fetchMode;
        }

        $this->setFetchMode($fetchMode, $fetchArgument, $ctorArgs);

        $this->results = [];
        while ($row = $this->fetch()) {
            if ((is_array($row) || is_object($row)) && is_resource(reset($row))) {
                $stmt = new self(reset($row), $this->connection, $this->options);
                $stmt->execute();
                $stmt->setFetchMode($fetchMode, $fetchArgument, $ctorArgs);
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
     * Fetches the next row and returns it as an object.
     *
     * @param string $className
     * @param array $ctorArgs
     * @return mixed
     */
    public function fetchObject($className = null, $ctorArgs = [])
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
    public function errorCode()
    {
        $error = $this->errorInfo();

        return $error[0];
    }

    /**
     * Fetch extended error information associated with the last operation on
     * the resource handle.
     *
     * @return array Array of error information about the last operation
     *   performed
     */
    public function errorInfo()
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
     * @param int $attribute
     * @param mixed $value
     * @return true on success or FALSE on failure.
     */
    public function setAttribute($attribute, $value)
    {
        $this->options[$attribute] = $value;

        return true;
    }

    /**
     * Retrieve a statement attribute.
     *
     * @param int $attribute
     * @return mixed The attribute value.
     */
    public function getAttribute($attribute)
    {
        if (isset($this->options[$attribute])) {
            return $this->options[$attribute];
        }
    }

    /**
     * Returns the number of columns in the result set.
     *
     * @return int The number of columns in the statement result set. If there
     *   is no result set, it returns 0.
     */
    public function columnCount()
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
     * @param int $column The 0-indexed column in the result set.
     * @return array An associative array containing the above metadata values
     *   for a single column.
     */
    public function getColumnMeta($column)
    {
        // Columns in oci8 are 1-based; add 1 if it's a number
        if (is_numeric($column)) {
            $column++;
        }

        $meta                     = [];
        $meta['native_type']      = oci_field_type($this->sth, $column);
        $meta['driver:decl_type'] = oci_field_type_raw($this->sth, $column);
        $meta['flags']            = [];
        $meta['name']             = oci_field_name($this->sth, $column);
        $meta['table']            = null;
        $meta['len']              = oci_field_size($this->sth, $column);
        $meta['precision']        = oci_field_precision($this->sth, $column);
        $meta['pdo_type']         = null;
        $meta['is_null']          = oci_field_is_null($this->sth, $column);

        return $meta;
    }

    /**
     * Set the default fetch mode for this statement.
     *
     * @param int|null $fetchMode The fetch mode must be one of the
     *   PDO::FETCH_* constants.
     * @param mixed|null $modeArg Column number, class name or object.
     * @param array|null $ctorArgs Constructor arguments.
     * @throws Oci8Exception
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setFetchMode($fetchMode, $modeArg = null, $ctorArgs = [])
    {
        // See which fetch mode we have
        switch ($fetchMode) {
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_NUM:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_OBJ:
                $this->fetchMode       = $fetchMode;
                $this->fetchColNo      = 0;
                $this->fetchClassName  = '\stdClass';
                $this->fetchCtorArgs   = [];
                $this->fetchIntoObject = null;
                break;
            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
                $this->fetchMode      = $fetchMode;
                $this->fetchColNo     = 0;
                $this->fetchClassName = '\stdClass';
                if ($modeArg) {
                    $this->fetchClassName = $modeArg;
                }
                $this->fetchCtorArgs   = $ctorArgs;
                $this->fetchIntoObject = null;
                break;
            case PDO::FETCH_INTO:
                if (! is_object($modeArg)) {
                    throw new Oci8Exception(
                        '$modeArg must be instance of an object'
                    );
                }
                $this->fetchMode       = $fetchMode;
                $this->fetchColNo      = 0;
                $this->fetchClassName  = '\stdClass';
                $this->fetchCtorArgs   = [];
                $this->fetchIntoObject = $modeArg;
                break;
            case PDO::FETCH_COLUMN:
                $this->fetchMode       = $fetchMode;
                $this->fetchColNo      = (int) $modeArg;
                $this->fetchClassName  = '\stdClass';
                $this->fetchCtorArgs   = [];
                $this->fetchIntoObject = null;
                break;
            default:
                throw new Oci8Exception('Requested fetch mode is not supported ' .
                    'by this implementation');
                break;
        }

        return true;
    }

    /**
     * Advances to the next rowset in a multi-rowset statement handle.
     *
     * @throws Oci8Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement method
     */
    public function nextRowset()
    {
        throw new Oci8Exception('setFetchMode has not been implemented');
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function closeCursor()
    {
        return oci_free_cursor($this->sth);
    }

    /**
     * Dump a SQL prepared command.
     *
     * @throws Oci8Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement method
     */
    public function debugDumpParams()
    {
        throw new Oci8Exception('setFetchMode has not been implemented');
    }
}

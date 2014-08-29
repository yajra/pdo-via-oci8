<?php
/**
 * PDO userspace driver proxying calls to PHP OCI8 driver
 *
 * @category Database
 * @package yajra/PDO-via-OCI8
 * @author Mathieu Dumoulin <crazyone@crazycoders.net>
 * @copyright Copyright (c) 2013 Mathieu Dumoulin (http://crazycoders.net/)
 * @license MIT
 */
namespace yajra\Pdo\Oci8;

use yajra\Pdo\Oci8;
use yajra\Pdo\Oci8\Exceptions\SqlException;

/**
 * Oci8 Statement class to mimic the interface of the PDOStatement class
 *
 * This class extends PDOStatement but overrides all of its methods. It does
 * this so that instanceof check and type-hinting of existing code will work
 * seamlessly.
 */
class Statement
    extends \PDOStatement
{

    /**
     * Statement handler
     *
     * @var resource
     */
    protected $_sth;

    /**
     * PDO Oci8 driver
     *
     * @var \yajra\Pdo\Oci8
     */
    protected $_pdoOci8;

    /**
     * Contains the current data
     *
     * @var array
     */
    protected $_current;

    /**
     * Contains the current key
     *
     * @var mixed
     */
    protected $_key;

    /**
     * flag to convert BLOB to string or not
     *
     * @var boolean
     */
    protected $returnLobs = true;

    /**
     * Statement options
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Constructor
     *
     * @param resource $sth Statement handle created with oci_parse()
     * @param Oci8 $pdoOci8 The Pdo_Oci8 object for this statement
     * @param array $options Options for the statement handle
     * @throws \PDOException
     */
    public function __construct($sth,
                                Oci8 $pdoOci8,
                                array $options = array())
    {

        if (strtolower(get_resource_type($sth)) != 'oci8 statement') {
            throw new \PDOException(
                'Resource expected of type oci8 statement; '
                . (string) get_resource_type($sth) . ' received instead');
        }

        $this->_sth = $sth;
        $this->_pdoOci8 = $pdoOci8;
        $this->_options = $options;
    }

    /**
     * Executes a prepared statement
     *
     * @param array $inputParams An array of values with as many elements as
     *   there are bound parameters in the SQL statement being executed.
     * @throws SqlException
     * @return bool TRUE on success or FALSE on failure
     */
    public function execute($inputParams = null)
    {
        $mode = OCI_COMMIT_ON_SUCCESS;
        if ($this->_pdoOci8->inTransaction()) {
            $mode = OCI_DEFAULT;
        }

        // Set up bound parameters, if passed in
        if (is_array($inputParams)) {
            foreach ($inputParams as $key => $value) {
                $this->bindParam($key, $inputParams[$key]);
            }
        }

        $result = @oci_execute($this->_sth, $mode);
        if($result != true)
        {
            $e = oci_error($this->_sth);
            
            $message = '';
            $message = $message . 'Error Code    : ' . $e['code'] . PHP_EOL;
            $message = $message . 'Error Message : ' . $e['message'] . PHP_EOL;
            $message = $message . 'Position      : ' . $e['offset'] . PHP_EOL;
            $message = $message . 'Statement     : ' . $e['sqltext'];
            
            throw new SqlException($message, $e['code']);
        }
        return $result;
    }

    /**
     * Fetches the next row from a result set
     *
     * @param int|null $fetchStyle Controls how the next row will be returned to
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
     * @return mixed
     * @todo Implement cursorOrientation and cursorOffset
     * @todo Fix PDO::FETCH_CLASS with specified class name and constructor
     *       arguments
     * @todo Implement PDO::FETCH_OBJECT
     */
    public function fetch($fetchStyle = \PDO::FETCH_BOTH,
                          $cursorOrientation = \PDO::FETCH_ORI_NEXT,
                          $cursorOffset = 0)
    {
        // Convert array keys (or object properties) to lowercase
        $toLowercase = ($this->getAttribute(\PDO::ATTR_CASE) == \PDO::CASE_LOWER);
        switch($fetchStyle)
        {
            case \PDO::FETCH_BOTH:
                $rs = oci_fetch_array($this->_sth); // add OCI_BOTH?
                if($rs === false) {
                    return false;
                }
                if($toLowercase) $rs = array_change_key_case($rs);
                if ($this->returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value) ) {
                            $rs[$field] = $value->load();
                        }
                    }
                }

                return $rs;

            case \PDO::FETCH_ASSOC:
                $rs = oci_fetch_assoc($this->_sth);
                if($rs === false) {
                    return false;
                }
                if($toLowercase) $rs = array_change_key_case($rs);
                if ($this->returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value) ) {
                            $rs[$field] = $value->load();
                        }
                    }
                }

                return $rs;

            case \PDO::FETCH_NUM:
                return oci_fetch_row($this->_sth);

            case \PDO::FETCH_CLASS:
                $rs = oci_fetch_assoc($this->_sth);
                if($rs === false) {
                    return false;
                }
                if($toLowercase) $rs = array_change_key_case($rs);

                if ($this->returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value) ) {
                            $rs[$field] = $value->load();
                        }
                    }
                }

                return (object) $rs;
        }
    }

    /**
     * Binds a parameter to the specified variable name
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
    public function bindParam($parameter,
                              &$variable,
                              $dataType = \PDO::PARAM_STR,
                              $maxLength = -1,
                              $options = null)
    {

        //Replace the first @oci8param to a pseudo named parameter
        if(is_numeric($parameter))
        {
            $parameter = ':autoparam'.$parameter;
        }

        //Adapt the type
        switch($dataType)
        {
            case \PDO::PARAM_BOOL:
                $oci_type =  SQLT_INT;
                break;

            case \PDO::PARAM_NULL:
                $oci_type =  SQLT_CHR;
                break;

            case \PDO::PARAM_INT:
                $oci_type =  SQLT_INT;
                break;

            case \PDO::PARAM_STR:
                $oci_type =  SQLT_CHR;
                break;

            case \PDO::PARAM_LOB:
                $oci_type =  OCI_B_BLOB;

                // create a new descriptor for blob
                $variable = $this->_pdoOci8->getNewDescriptor();
                break;

            case \PDO::PARAM_STMT:
                $oci_type =  OCI_B_CURSOR;

                //Result sets require a cursor
                $variable = $this->_pdoOci8->getNewCursor();
                break;

            default:
                $oci_type =  SQLT_CHR;
                break;
        }

        //Bind the parameter
        $result = oci_bind_by_name($this->_sth, $parameter, $variable, $maxLength, $oci_type);
        return $result;

    }

    /**
     * Binds a column to a PHP variable
     *
     * @param mixed $column Number of the column (1-indexed) or name of the
     *   column in the result set. If using the column name, be aware that the
     *   name should match the case of the column, as returned by the driver.
     * @param mixed $variable The PHP to which the column should be bound.
     * @param int $dataType Data type of the parameter, specified by the
     *   PDO::PARAM_* constants.
     * @param int $maxLength A hint for pre-allocation.
     * @param array $options [optional] Optional parameter(s) for the driver.
     * @throws \Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement this functionality by creating a table map of the
     *       variables passed in here, and, when iterating over the values
     *       of the query or fetching rows, assign data from each column
     *       to their respective variable in the map.
     */
    public function bindColumn($column,
                               &$variable,
                               $dataType = null,
                               $maxLength = -1,
                               $options = null)
    {
        throw new \Exception("bindColumn has not been implemented");
    }

    /**
     * Binds a value to a parameter
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
    public function bindValue($parameter,
                              $variable,
                              $dataType = \PDO::PARAM_STR)
    {
        return $this->bindParam($parameter, $variable, $dataType);
    }

    /**
     * Returns the number of rows affected by the last executed statement
     *
     * @return int The number of rows.
     */
    public function rowCount()
    {
        return oci_num_rows($this->_sth);
    }

    /**
     * Returns a single column from the next row of a result set
     *
     * @param int $colNumber 0-indexed number of the column you wish to retrieve
     *   from the row. If no value is supplied, it fetches the first column.
     * @return string Returns a single column in the next row of a result set.
     * @todo Implement colNumber
     */
    public function fetchColumn($colNumber = 0)
    {
        return reset($this->fetch());
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @param int $fetchStyle Controls the contents of the returned array as
     *   documented in PDOStatement::fetch.
     * @param mixed $fetchArgument This argument has a different meaning
     *   depending on the value of the fetchStyle parameter.
     * @param array $ctorArgs [optional] Arguments of custom class constructor
     *   when the fetch_style parameter is PDO::FETCH_CLASS.
     * @return array Array containing all of the remaining rows in the result
     *   set. The array represents each row as either an array of column values
     *   or an object with properties corresponding to each column name.
     */
    public function fetchAll($fetchStyle = \PDO::FETCH_BOTH,
                             $fetchArgument = null,
                             $ctorArgs = null)
    {
        $results = array();
        while($row = $this->fetch($fetchStyle, $fetchArgument, $ctorArgs))
        {
            $results[] = $row;
        }
        return $results;
    }

    /**
     * Fetches the next row and returns it as an object
     *
     * @param string $className
     * @param array $ctorArgs
     * @return mixed
     * @todo Implement className and ctorArgs; easiest implementation will be
     *       by implementing in fetch() and calling it with proper parameters
     */
    public function fetchObject($className = null, $ctorArgs = null)
    {
        return (object)$this->fetch();
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the resource
     * handle
     *
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
        $e = oci_error($this->_sth);

        if (is_array($e)) {
            return array(
                'HY000',
                $e['code'],
                $e['message']
            );
        }

        return array('00000', null, null);
    }

    /**
     * Sets a statement attribute
     *
     * @param int $attribute
     * @param mixed $value
     * @return TRUE on success or FALSE on failure.
     */
    public function setAttribute($attribute, $value)
    {
        $this->_options[$attribute] = $value;
        return true;
    }

    /**
     * Retrieve a statement attribute
     *
     * @param int $attribute
     * @return mixed The attribute value.
     */
    public function getAttribute($attribute)
    {
        if (isset($this->_options[$attribute])) {
            return $this->_options[$attribute];
        }
        return null;
    }

    /**
     * Returns the number of columns in the result set
     *
     * @return int The number of columns in the statement result set. If there
     *   is no result set, it returns 0.
     */
    public function columnCount()
    {
        return oci_num_fields($this->_sth);
    }

    /**
     * Returns metadata for a column in a result set
     *
     * The array returned by this function is patterned after that
     * returned by \PDO::getColumnMeta(). It includes the following
     * elements:
     *
     *     native_type
     *     driver:decl_type
     *     flags
     *     name
     *     table
     *     len
     *     precision
     *     pdo_type
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

        $meta = array();
        $meta['native_type'] = oci_field_type($this->_sth, $column);
        $meta['driver:decl_type'] = oci_field_type_raw($this->_sth, $column);
        $meta['flags'] = array();
        $meta['name'] = oci_field_name($this->_sth, $column);
        $meta['table'] = null;
        $meta['len'] = oci_field_size($this->_sth, $column);
        $meta['precision'] = oci_field_precision($this->_sth, $column);
        $meta['pdo_type'] = null;

        return $meta;
    }

    /**
     * Set the default fetch mode for this statement
     *
     * @param int|null $fetchMode The fetch mode must be one of the
     *   PDO::FETCH_* constants.
     * @param mixed|null $modeArg Column number, class name or object.
     * @param array|null $ctorArgs Constructor arguments.
     * @throws \Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement method
     */
    public function setFetchMode($fetchMode,
                                 $modeArg = null,
                                 array $ctorArgs = array())
    {
        throw new \Exception("seteFetchMode has not been implemented");
    }

    /**
     * Advances to the next rowset in a multi-rowset statement handle
     *
     * @throws \Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement method
     */
    public function nextRowset()
    {
        throw new \Exception("seteFetchMode has not been implemented");
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @throws \Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement method
     */
    public function closeCursor()
    {
        throw new \Exception("seteFetchMode has not been implemented");
    }

    /**
     * Dump a SQL prepared command
     *
     * @throws \Exception
     * @return bool TRUE on success or FALSE on failure.
     * @todo Implement method
     */
    public function debugDumpParams()
    {
        throw new \Exception("seteFetchMode has not been implemented");
    }

}

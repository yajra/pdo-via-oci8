<?php
/**
 * PDO Userspace Driver for Oracle (oci8)
 *
 * @category Database
 * @package Pdo
 * @subpackage Oci8
 * @author Ben Ramsey <ramsey@php.net>
 * @copyright Copyright (c) 2009 Ben Ramsey (http://benramsey.com/)
 * @license http://open.benramsey.com/license/mit  MIT License
 */

/**
 * Oci8 Statement class to mimic the interface of the PDOStatement class
 *
 * This class extends PDOStatement but overrides all of its methods. It does
 * this so that instanceof check and type-hinting of existing code will work
 * seamlessly.
 */
class Pdo_Oci8_Statement extends PDOStatement
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
     * @var Pdo_Oci8
     */
    protected $_pdoOci8;

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
     * @param Pdo_Oci8 $pdoOci8 The Pdo_Oci8 object for this statement
     * @param array $options Options for the statement handle
     * @return void
     */
    public function __construct($sth,
                                Pdo_Oci8 $pdoOci8,
                                array $options = array())
    {
        if (strtolower(get_resource_type($sth)) != 'oci8 statement') {
            throw new PDOException(
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
     * @param array $inputParams
     * @return bool
     */
    public function execute($inputParams = null)
    {
        $mode = OCI_COMMIT_ON_SUCCESS;
        if ($this->_pdoOci8->isTransaction()) {
            $mode = OCI_DEFAULT;
        }

        // Set up bound parameters, if passed in
        if (is_array($inputParams)) {
            foreach ($inputParams as $key => $value) {
                $this->bindParam($key, $value);
            }
        }

        return oci_execute($this->_sth, $mode);
    }

    /**
     * Fetches the next row from a result set
     *
     * @param int $fetchStyle
     * @param int $cursorOrientation
     * @param int $cursorOffset
     * @return mixed
     */
    public function fetch($fetchStyle = PDO::FETCH_BOTH,
                          $cursorOrientation = PDO::FETCH_ORI_NEXT,
                          $offset = 0)
    {
    }

    /**
     * Binds a parameter to the specified variable name
     *
     * @param string $parameter
     * @param mixed $variable
     * @param int $dataType
     * @param int $maxLength
     * @param array $options
     * @return bool
     * @todo Map PDO datatypes to oci8 datatypes and implement support for datatypes and length.
     */
    public function bindParam($parameter,
                              &$variable,
                              $dataType = PDO::PARAM_STR,
                              $maxLength = -1,
                              $options = null)
    {
        if (is_array($variable)) {
            return oci_bind_array_by_name(
                $this->_sth,
                $parameter,
                $variable,
                count($variable)
            );
        }

        return oci_bind_by_name($this->_sth, $parameter, $variable);
    }

    /**
     * Binds a column to a PHP variable
     *
     * @param mixed $column The number of the column or name of the column
     * @param mixed $variable The PHP to which the column should be bound
     * @param int $dataType
     * @param int $maxLength
     * @param array $options
     * @return bool
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
    }

    /**
     * Binds a value to a parameter
     *
     * @param string $parameter
     * @param mixed $variable
     * @param int $dataType
     * @return bool
     */
    public function bindValue($parameter,
                              $variable,
                              $dataType = PDO::PARAM_STR)
    {
        return $this->bindParam($parameter, $variable, $dataType);
    }

    /**
     * Returns the number of rows affected by the last executed statement
     *
     * @return int
     */
    public function rowCount()
    {
        return oci_num_rows($this->_sth);
    }

    /**
     * Returns a single column from the next row of a result set
     *
     * @param int $colNumber
     * @return string
     */
    public function fetchColumn($colNumber = 0)
    {
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @param int $fetchType
     * @param mixed $idxOrClass
     * @param array $ctorArgs
     * @return mixed
     */
    public function fetchAll($fetchType = PDO::FETCH_BOTH,
                             $idxOrClass = null,
                             $ctorArgs = null)
    {
    }

    /**
     * Fetches the next row and returns it as an object
     *
     * @param string $className
     * @param array $ctorArgs
     * @return mixed
     */
    public function fetchObject($className = null, $ctorArgs = null)
    {
    }

    /**
     * Returns the error code associated with the last operation
     *
     * While this returns an error code, it merely emulates the action. If
     * there are no errors, it returns the success SQLSTATE code (00000).
     * If there are errors, it returns HY000. See errorInfo() to retrieve
     * the actual Oracle error code and message.
     *
     * @return string
     */
    public function errorCode()
    {
        $error = $this->errorInfo();
        return $error[0];
    }

    /**
     * Returns extended error information for the last operation on the database
     *
     * @return array
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
     * Sets an attribute on the statement handle
     *
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute($attribute, $value)
    {
        $this->_options[$attribute] = $value;
        return true;
    }

    /**
     * Retrieve a statement handle attribute
     *
     * @return mixed
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
     * @return int
     */
    public function columnCount()
    {
        return oci_num_fields($this->_sth);
    }

    /**
     * Returns metadata for a column in a result set
     *
     * The array returned by this function is patterned after that
     * returned by PDO::getColumnMeta(). It includes the following
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
     * @param int $column Zero-based column index
     * @return array
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
     * @param int $fetchType
     * @param mixed $colClassOrObj
     * @param array $ctorArgs
     * @return bool
     */
    public function setFetchMode($fetchType,
                                 $colClassOrObj = null,
                                 array $ctorArgs = array())
    {
    }

    /**
     * Advances to the next rowset in a multi-rowset statement handle
     *
     * @return bool
     */
    public function nextRowset()
    {
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool
     */
    public function closeCursor()
    {
    }

    /**
     * Dump a SQL prepared command
     *
     * @return bool
     */
    public function debugDumpParams()
    {
    }

    /**
     * Returns the current row from the rowset
     *
     * @return array
     */
    public function current()
    {
    }

    /**
     * Returns the key for the current row
     *
     * @return mixed
     */
    public function key()
    {
    }

    /**
     * Advances the cursor forward and returns the next row
     *
     * @return void
     */
    public function next()
    {
    }

    /**
     * Rewinds the cursor to the beginning of the rowset
     *
     * @return void
     */
    public function rewind()
    {
    }

    /**
     * Checks whether there is a current row
     *
     * @return bool
     */
    public function valid()
    {
    }
}

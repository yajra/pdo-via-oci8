<?php namespace yajra\Pdo\Oci8;

use PDO;
use PDOStatement;
use yajra\Pdo\Oci8;
use yajra\Pdo\Oci8\Exceptions\Oci8Exception;

/**
 * Oci8 Statement class to mimic the interface of the PDOStatement class
 * This class extends PDOStatement but overrides all of its methods. It does
 * this so that instanceof check and type-hinting of existing code will work
 * seamlessly.
 */
class Statement extends PDOStatement {

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
	 * flag to convert LOB to string or not
	 *
	 * @var boolean
	 */
	protected $_returnLobs = true;

	/**
	 * Statement options
	 *
	 * @var array
	 */
	protected $_options = array();

	/**
	 * Fetch mode selected via setFetchMode()
	 *
	 * @var int
	 */
	protected $_fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE;

	/**
	 * Column number for PDO::FETCH_COLUMN fetch mode
	 *
	 * @var int
	 */
	protected $_fetchColno = 0;

	/**
	 * Class name for PDO::FETCH_CLASS fetch mode
	 *
	 * @var string
	 */
	protected $_fetchClassName = '\stdClass';

	/**
	 * Constructor arguments for PDO::FETCH_CLASS
	 *
	 * @var array
	 */
	protected $_fetchCtorargs = array();

	/**
	 * Object reference for PDO::FETCH_INTO fetch mode
	 *
	 * @var object
	 */
	protected $_fetchIntoObject = null;

	/**
	 * PDO result set
	 *
	 * @var array
	 */
	protected $_results = array();

	/**
	 * Constructor
	 *
	 * @param resource $sth Statement handle created with oci_parse()
	 * @param Oci8 $pdoOci8 The Pdo_Oci8 object for this statement
	 * @param array $options Options for the statement handle
	 * @throws Oci8Exception
	 */
	public function __construct($sth, Oci8 $pdoOci8, array $options = array())
	{

		if (strtolower(get_resource_type($sth)) != 'oci8 statement')
		{
			throw new Oci8Exception(
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
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure
	 */
	public function execute($inputParams = null)
	{
		$mode = OCI_COMMIT_ON_SUCCESS;
		if ($this->_pdoOci8->inTransaction())
		{
			$mode = OCI_DEFAULT;
		}

		// Set up bound parameters, if passed in
		if (is_array($inputParams))
		{
			foreach ($inputParams as $key => $value)
			{
				$this->bindParam($key, $inputParams[$key]);
			}
		}

		$result = @oci_execute($this->_sth, $mode);
		if ($result != true)
		{
			$e = oci_error($this->_sth);

			$message = '';
			$message = $message . 'Error Code    : ' . $e['code'] . PHP_EOL;
			$message = $message . 'Error Message : ' . $e['message'] . PHP_EOL;
			$message = $message . 'Position      : ' . $e['offset'] . PHP_EOL;
			$message = $message . 'Statement     : ' . $e['sqltext'] . PHP_EOL;
			$message = $message . 'Bindings      : [' . implode(',', (array) $inputParams) . ']' . PHP_EOL;

			throw new Oci8Exception($message, $e['code']);
		}

		return $result;
	}

	/**
	 * Fetches the next row from a result set
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
	public function fetch(
		$fetchMode = null,
		$cursorOrientation = PDO::FETCH_ORI_NEXT,
		$cursorOffset = 0)
	{
		// If not fetchMode was specified, used the default value of or the mode
		// set by the last call to setFetchMode()
		if ($fetchMode === null)
		{
			$fetchMode = $this->_fetchMode;
		}

		// Convert array keys (or object properties) to lowercase
		$toLowercase = ($this->getAttribute(PDO::ATTR_CASE) == PDO::CASE_LOWER);
		// Convert null value to empty string
		$nullToString = ($this->getAttribute(PDO::ATTR_ORACLE_NULLS) == PDO::NULL_TO_STRING);
		// Convert empty string to null
		$nullEmptyString = ($this->getAttribute(PDO::ATTR_ORACLE_NULLS) == PDO::NULL_EMPTY_STRING);

		// Determine the fetch mode
		switch ($fetchMode)
		{
			case PDO::FETCH_BOTH:
				$rs = oci_fetch_array($this->_sth); // Fetches both; nice!
				if ($rs === false)
				{
					return false;
				}
				if ($toLowercase)
				{
					$rs = array_change_key_case($rs);
				}
				if ($this->_returnLobs && is_array($rs))
				{
					foreach ($rs as $field => $value)
					{
						if (is_object($value))
						{
							$rs[$field] = $value->load();
						}
					}
				}

				return $rs;

			case PDO::FETCH_ASSOC:
				$rs = oci_fetch_assoc($this->_sth);
				if ($rs === false)
				{
					return false;
				}
				if ($toLowercase)
				{
					$rs = array_change_key_case($rs);
				}
				if ($this->_returnLobs && is_array($rs))
				{
					foreach ($rs as $field => $value)
					{
						if (is_object($value))
						{
							$rs[$field] = $value->load();
						}
					}
				}

				return $rs;

			case PDO::FETCH_NUM:
				$rs = oci_fetch_row($this->_sth);
				if ($rs === false)
				{
					return false;
				}
				if ($this->_returnLobs && is_array($rs))
				{
					foreach ($rs as $field => $value)
					{
						if (is_object($value))
						{
							$rs[$field] = $value->load();
						}
					}
				}

				return $rs;

			case PDO::FETCH_COLUMN:
				$rs = oci_fetch_row($this->_sth);
				$colno = (int) $this->_fetchColno;
				if (is_array($rs) && array_key_exists($colno, $rs))
				{
					$value = $rs[$colno];
					if (is_object($value))
					{
						return $value->load();
					}
					else
					{
						return $value;
					}
				}
				else
				{
					return false;
				}
				break;

			case PDO::FETCH_OBJ:
			case PDO::FETCH_INTO:
			case PDO::FETCH_CLASS:
			case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
				$rs = oci_fetch_assoc($this->_sth);
				if ($rs === false)
				{
					return false;
				}
				if ($toLowercase)
				{
					$rs = array_change_key_case($rs);
				}

				if ($fetchMode === PDO::FETCH_INTO)
				{
					if (is_object($this->_fetchIntoObject))
					{
						$object = $this->_fetchIntoObject;
					}
					else
					{
						// Object to set into has not been set
						return false;
					}
				}
				else
				{
					if ($fetchMode === PDO::FETCH_OBJ)
					{
						$className = '\stdClass';
						$ctorargs = array();
					}
					else
					{
						$className = $this->_fetchClassName;
						$ctorargs = $this->_fetchCtorargs;
					}

					if ($ctorargs)
					{
						$reflectionClass = new \ReflectionClass($className);
						$object = $reflectionClass->newInstanceArgs($ctorargs);
					}
					else
					{
						$object = new $className();
					}
				}

				// Format recordsets values depending on options
				foreach ($rs as $field => $value)
				{
					// convert null to empty string
					if (is_null($value) && $nullToString)
					{
						$rs[$field] = '';
					}

					// convert empty string to null
					if (empty($rs[$field]) && $nullEmptyString)
					{
						$rs[$field] = null;
					}

					// convert LOB to string
					if ($this->_returnLobs && is_object($value))
					{
						$object->$field = $value->load();
					}
					else
					{
						$object->$field = $value;
					}
				}

				return $object;
		}

		return false;
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
	public function bindParam(
		$parameter,
		&$variable,
		$dataType = PDO::PARAM_STR,
		$maxLength = -1,
		$options = null)
	{

		//Replace the first @oci8param to a pseudo named parameter
		if (is_numeric($parameter))
		{
			$parameter = ':autoparam' . $parameter;
		}

		//Adapt the type
		switch ($dataType)
		{
			case PDO::PARAM_BOOL:
				$oci_type = SQLT_INT;
				break;

			case PDO::PARAM_NULL:
				$oci_type = SQLT_CHR;
				break;

			case PDO::PARAM_INT:
				$oci_type = SQLT_INT;
				break;

			case PDO::PARAM_STR:
				$oci_type = SQLT_CHR;
				break;

			case PDO::PARAM_LOB:
				$oci_type = OCI_B_BLOB;

				// create a new descriptor for blob
				$variable = $this->_pdoOci8->getNewDescriptor();
				break;

			case PDO::PARAM_STMT:
				$oci_type = OCI_B_CURSOR;

				// Result sets require a cursor
				$variable = $this->_pdoOci8->getNewCursor();
				break;

			case SQLT_NTY:
				$oci_type = SQLT_NTY;

				$schema = isset($options['schema']) ? $options['schema'] : '';
				$type_name = isset($options['type_name']) ? $options['type_name'] : '';

				// set params required to use custom type.
				$variable = oci_new_collection($this->_pdoOci8->_dbh, $type_name, $schema);
				break;

			default:
				$oci_type = SQLT_CHR;
				break;
		}

		// Bind the parameter
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
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure.
	 * @todo Implement this functionality by creating a table map of the
	 *       variables passed in here, and, when iterating over the values
	 *       of the query or fetching rows, assign data from each column
	 *       to their respective variable in the map.
	 */
	public function bindColumn(
		$column,
		&$variable,
		$dataType = null,
		$maxLength = -1,
		$options = null)
	{
		throw new Oci8Exception("bindColumn has not been implemented");
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
	public function bindValue($parameter, $variable, $dataType = PDO::PARAM_STR)
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
	 */
	public function fetchColumn($colNumber = null)
	{
		$this->setFetchMode(PDO::FETCH_COLUMN, $colNumber);

		return $this->fetch();
	}

	/**
	 * Returns an array containing all of the result set rows
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
	public function fetchAll(
		$fetchMode = PDO::FETCH_BOTH,
		$fetchArgument = null,
		$ctorArgs = array())
	{
		$this->setFetchMode($fetchMode, $fetchArgument, $ctorArgs);

		$this->_results = array();
		while ($row = $this->fetch())
		{
			if (is_resource(reset($row))) {
				$stmt = new Statement(reset($row), $this->_pdoOci8, $this->_options);
				$stmt->execute();
				$stmt->setFetchMode($fetchMode, $fetchArgument, $ctorArgs);
				while ($rs = $stmt->fetch()) {
					$this->_results[] = $rs;
				}
			} else {
				$this->_results[] = $row;
			}
		}

		return $this->_results;
	}

	/**
	 * Fetches the next row and returns it as an object
	 *
	 * @param string $className
	 * @param array $ctorArgs
	 * @return mixed
	 */
	public function fetchObject($className = null, $ctorArgs = array())
	{
		$this->setFetchMode(PDO::FETCH_CLASS, $className, $ctorArgs);

		return $this->fetch();
	}

	/**
	 * Fetch the SQLSTATE associated with the last operation on the resource
	 * handle
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

		if (is_array($e))
		{
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
		if (isset($this->_options[$attribute]))
		{
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
	 *     pdo_type
	 *
	 * @param int $column The 0-indexed column in the result set.
	 * @return array An associative array containing the above metadata values
	 *   for a single column.
	 */
	public function getColumnMeta($column)
	{
		// Columns in oci8 are 1-based; add 1 if it's a number
		if (is_numeric($column))
		{
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
		$meta['is_null'] = oci_field_is_null($this->_sth, $column);

		return $meta;
	}

	/**
	 * Set the default fetch mode for this statement
	 *
	 * @param int|null $fetchMode The fetch mode must be one of the
	 *   PDO::FETCH_* constants.
	 * @param mixed|null $modeArg Column number, class name or object.
	 * @param array|null $ctorArgs Constructor arguments.
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function setFetchMode($fetchMode, $modeArg = null, array $ctorArgs = array())
	{
		// See which fetch mode we have
		switch ($fetchMode)
		{
			case PDO::FETCH_ASSOC:
			case PDO::FETCH_NUM:
			case PDO::FETCH_BOTH:
			case PDO::FETCH_OBJ:
				$this->_fetchMode = $fetchMode;
				$this->_fetchColno = 0;
				$this->_fetchClassName = '\stdClass';
				$this->_fetchCtorargs = array();
				$this->_fetchIntoObject = null;
				break;
			case PDO::FETCH_CLASS:
			case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
				$this->_fetchMode = $fetchMode;
				$this->_fetchColno = 0;
				$this->_fetchClassName = '\stdClass';
				if ($modeArg)
				{
					$this->_fetchClassName = $modeArg;
				}
				$this->_fetchCtorargs = $ctorArgs;
				$this->_fetchIntoObject = null;
				break;
			case PDO::FETCH_INTO:
				if ( ! is_object($modeArg))
				{
					throw new Oci8Exception(
						'$modeArg must be instance of an object');
				}
				$this->_fetchMode = $fetchMode;
				$this->_fetchColno = 0;
				$this->_fetchClassName = '\stdClass';
				$this->_fetchCtorargs = array();
				$this->_fetchIntoObject = $modeArg;
				break;
			case PDO::FETCH_COLUMN:
				$this->_fetchMode = $fetchMode;
				$this->_fetchColno = (int) $modeArg;
				$this->_fetchClassName = '\stdClass';
				$this->_fetchCtorargs = array();
				$this->_fetchIntoObject = null;
				break;
			default:
				throw new Oci8Exception("Requested fetch mode is not supported " .
					"by this implementation");
				break;
		}

		return true;
	}

	/**
	 * Advances to the next rowset in a multi-rowset statement handle
	 *
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure.
	 * @todo Implement method
	 */
	public function nextRowset()
	{
		throw new Oci8Exception("setFetchMode has not been implemented");
	}

	/**
	 * Closes the cursor, enabling the statement to be executed again.
	 *
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure.
	 * @todo Implement method
	 */
	public function closeCursor()
	{
		throw new Oci8Exception("setFetchMode has not been implemented");
	}

	/**
	 * Dump a SQL prepared command
	 *
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure.
	 * @todo Implement method
	 */
	public function debugDumpParams()
	{
		throw new Oci8Exception("setFetchMode has not been implemented");
	}

}

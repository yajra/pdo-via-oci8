<?php
/**
 * PDO userspace driver proxying calls to PHP OCI8 driver
 *
 * @category Database
 * @package yajra/PDO-via-OCI8
 * @author Arjay Angeles <aqangeles@gmail.com>
 * @copyright Copyright (c) 2013 Arjay Angeles
 * @license MIT
 */
namespace yajra\Pdo;

use PDO;
use yajra\Pdo\Oci8\Exceptions\Oci8Exception;
use yajra\Pdo\Oci8\Statement;

/**
 * Oci8 class to mimic the interface of the PDO class
 * This class extends PDO but overrides all of its methods. It does this so
 * that instanceof checks and type-hinting of existing code will work
 * seamlessly.
 */
class Oci8 extends PDO {

	/**
	 * Database handler
	 *
	 * @var resource
	 */
	public $_dbh;

	/**
	 * Driver options
	 *
	 * @var array
	 */
	protected $_options = array();

	/**
	 * Whether currently in a transaction
	 *
	 * @var bool
	 */
	protected $_inTransaction = false;

	/**
	 * insert query statement table variable
	 *
	 * @var string
	 */
	protected $_table;

	/**
	 * Creates a PDO instance representing a connection to a database
	 *
	 * @param $dsn
	 * @param $username [optional]
	 * @param $password [optional]
	 * @param array $options [optional]
	 * @throws Oci8Exception
	 */
	public function __construct($dsn, $username, $password, array $options = array())
	{
		// Set default charset to AL32UTF8
		$charset = 'AL32UTF8';
		// Get the character set
		if (array_key_exists("charset", $options))
		{
			$charset = $options["charset"];
		}
		// Convert UTF8 charset to AL32UTF8
		$charset = strtolower($charset) == 'utf8' ? 'AL32UTF8' : $charset;

		// Attempt a connection
		if (isset($options[PDO::ATTR_PERSISTENT]) && $options[PDO::ATTR_PERSISTENT])
		{
			$this->_dbh = oci_pconnect($username, $password, $dsn, $charset);
		}
		else
		{
			$this->_dbh = oci_connect($username, $password, $dsn, $charset);
		}

		// Check if connection was successful
		if ( ! $this->_dbh)
		{
			$e = oci_error();
			throw new Oci8Exception($e['message']);
		}

		// Save the options
		$this->_options = $options;
	}

	/**
	 * Prepares a statement for execution and returns a statement object
	 *
	 * @param string $statement This must be a valid SQL statement for the
	 *   target database server.
	 * @param array $options [optional] This array holds one or more key=>value
	 *   pairs to set attribute values for the PDOStatement object that this
	 *   method returns.
	 * @throws Oci8Exception
	 * @return Statement
	 */
	public function prepare($statement, $options = null)
	{
		// Get instance options
		if ($options == null)
		{
			$options = $this->_options;
		}

		// Skip replacing ? with a pseudo named parameter on alter/create table command
		if ( ! preg_match('/^alter+ +table/', strtolower(trim($statement)))
			and ! preg_match('/^create+ +table/', strtolower(trim($statement)))
		)
		{
			// Replace ? with a pseudo named parameter
			$newStatement = null;
			$parameter = 0;
			while ($newStatement !== $statement)
			{
				if ($newStatement !== null)
				{
					$statement = $newStatement;
				}
				$newStatement = preg_replace('/\?/', ':autoparam' . $parameter, $statement, 1);
				$parameter++;
			}
			$statement = $newStatement;
		}

		// check if statement is insert function
		if (strpos(strtolower($statement), 'insert into') !== false)
		{
			preg_match('/insert into\s+([^\s\(]*)?/', strtolower($statement), $matches);
			// store insert into table name
			$this->_table = $matches[1];
		}

		// Prepare the statement
		$sth = @oci_parse($this->_dbh, $statement);

		if ( ! $sth)
		{
			$e = oci_error($this->_dbh);
			throw new Oci8Exception($e['message']);
		}

		if ( ! is_array($options))
		{
			$options = array();
		}

		return new Statement($sth, $this, $options);
	}

	/**
	 * Initiates a transaction
	 *
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure
	 */
	public function beginTransaction()
	{
		if ($this->inTransaction())
		{
			throw new Oci8Exception('There is already an active transaction');
		}

		$this->_inTransaction = true;

		return true;
	}

	/**
	 * Returns true if the current process is in a transaction
	 *
	 * @deprecated Use inTransaction() instead
	 * @return bool
	 */
	public function isTransaction()
	{
		return $this->inTransaction();
	}

	/**
	 * Checks if inside a transaction
	 *
	 * @return bool TRUE if a transaction is currently active, and FALSE if not.
	 */
	public function inTransaction()
	{
		return $this->_inTransaction;
	}

	/**
	 * Commits a transaction
	 *
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function commit()
	{
		if ( ! $this->inTransaction())
		{
			throw new Oci8Exception('There is no active transaction');
		}

		if (oci_commit($this->_dbh))
		{
			$this->_inTransaction = false;

			return true;
		}

		return false;
	}

	/**
	 * Rolls back a transaction
	 *
	 * @throws Oci8Exception
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function rollBack()
	{
		if ( ! $this->inTransaction())
		{
			throw new Oci8Exception('There is no active transaction');
		}

		if (oci_rollback($this->_dbh))
		{
			$this->_inTransaction = false;

			return true;
		}

		return false;
	}

	/**
	 * Sets an attribute on the database handle
	 *
	 * @param int $attribute
	 * @param mixed $value
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function setAttribute($attribute, $value)
	{
		$this->_options[$attribute] = $value;

		return true;
	}

	/**
	 * Executes an SQL statement and returns the number of affected rows
	 *
	 * @param string $statement The SQL statement to prepare and execute.
	 * @return int The number of rows that were modified or deleted by the SQL
	 *   statement you issued.
	 */
	public function exec($statement)
	{
		$stmt = $this->prepare($statement);
		$stmt->execute();

		return $stmt->rowCount();
	}

	/**
	 * Executes an SQL statement, returning the results as a
	 * yajra\Pdo\Oci8\Statement object
	 *
	 * @param string $statement The SQL statement to prepare and execute.
	 * @param int|null $fetchMode The fetch mode must be one of the
	 *   PDO::FETCH_* constants.
	 * @param mixed|null $modeArg Column number, class name or object.
	 * @param array|null $ctorArgs Constructor arguments.
	 * @return Statement
	 */
	public function query(
		$statement,
		$fetchMode = null,
		$modeArg = null,
		array $ctorArgs = array())
	{
		$stmt = $this->prepare($statement);
		$stmt->execute();
		if ($fetchMode)
		{
			$stmt->setFetchMode($fetchMode, $modeArg, $ctorArgs);
		}

		return $stmt;
	}

	/**
	 * returns the current value of the sequence related to the table where
	 * record is inserted by default. The sequence name should follow this for it to work
	 * properly:
	 *   {$table}.'_id_seq'
	 * If the sequence name is passed, then the function will check using that value.
	 * Oracle does not support the last inserted ID functionality like MySQL.
	 * If the above sequence does not exist, the method will return 0;
	 *
	 * @param string $sequence Sequence name
	 * @return mixed Last sequence number or 0 if sequence does not exist
	 */
	public function lastInsertId($sequence = null)
	{
		if (is_null($sequence)) {
			$sequence = $this->_table . "_id_seq";
		}

		if ( ! $this->checkSequence($sequence))
		{
			return 0;
		}

		$stmt = $this->query("SELECT {$sequence}.CURRVAL FROM DUAL", PDO::FETCH_COLUMN);
		$id = $stmt->fetch();

		return $id;
	}

	/**
	 * Fetch the SQLSTATE associated with the last operation on the database
	 * handle
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
	 * handle
	 * The array consists of the following fields:
	 *   0  SQLSTATE error code (a five characters alphanumeric identifier
	 *      defined in the ANSI SQL standard).
	 *   1  Driver-specific error code.
	 *   2  Driver-specific error message.
	 *
	 * @return array Error information
	 */
	public function errorInfo()
	{
		$e = oci_error($this->_dbh);

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
	 * Retrieve a database connection attribute
	 *
	 * @param int $attribute
	 * @return mixed A successful call returns the value of the requested PDO
	 *   attribute. An unsuccessful call returns null.
	 */
	public function getAttribute($attribute)
	{
		if ($attribute == PDO::ATTR_DRIVER_NAME)
		{
			return "oci8";
		}

		if (isset($this->_options[$attribute]))
		{
			return $this->_options[$attribute];
		}

		return null;
	}

	/**
	 * Special non PDO function used to start cursors in the database
	 * Remember to call oci_free_statement() on your cursor
	 *
	 * @access public
	 * @return mixed New statement handle, or FALSE on error.
	 */
	public function getNewCursor()
	{
		return oci_new_cursor($this->_dbh);
	}

	/**
	 * Special non PDO function used to start descriptor in the database
	 * Remember to call oci_free_statement() on your cursor
	 *
	 * @access public
	 * @param int $type One of OCI_DTYPE_FILE, OCI_DTYPE_LOB or OCI_DTYPE_ROWID.
	 * @return mixed New LOB or FILE descriptor on success, FALSE on error.
	 */
	public function getNewDescriptor($type = OCI_D_LOB)
	{
		return oci_new_descriptor($this->_dbh, $type);
	}

	/**
	 * Special non PDO function used to close an open cursor in the database
	 *
	 * @access public
	 * @param mixed $cursor A valid OCI statement identifier.
	 * @return mixed Returns TRUE on success or FALSE on failure.
	 */
	public function closeCursor($cursor)
	{
		return oci_free_statement($cursor);
	}

	/**
	 * Places quotes around the input string
	 *  If you are using this function to build SQL statements, you are strongly
	 * recommended to use prepare() to prepare SQL statements with bound
	 * parameters instead of using quote() to interpolate user input into an SQL
	 * statement. Prepared statements with bound parameters are not only more
	 * portable, more convenient, immune to SQL injection, but are often much
	 * faster to execute than interpolated queries, as both the server and
	 * client side can cache a compiled form of the query.
	 *
	 * @param string $string The string to be quoted.
	 * @param int $paramType Provides a data type hint for drivers that have
	 *   alternate quoting styles
	 * @return string Returns a quoted string that is theoretically safe to pass
	 *   into an SQL statement.
	 * @todo Implement support for $paramType.
	 */
	public function quote($string, $paramType = PDO::PARAM_STR)
	{
		return "'" . str_replace("'", "''", $string) . "'";
	}

	/**
	 * Special non PDO function to check if sequence exists
	 *
	 * @param  string $name
	 * @return boolean
	 */
	public function checkSequence($name)
	{
		if ( ! $name)
		{
			return false;
		}

		$stmt = $this->query("SELECT count(*)
            FROM ALL_SEQUENCES
            WHERE
                SEQUENCE_NAME=UPPER('{$name}')
                AND SEQUENCE_OWNER=UPPER(USER)
            ", PDO::FETCH_COLUMN);

		return $stmt->fetch();
	}

}

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
 * @see Pdo_Util
 */
require_once 'Pdo/Util.php';

/**
 * @see Pdo_Oci8_Statement
 */
require_once 'Pdo/Oci8/Statement.php';

/**
 * Oci8 class to mimic the interface of the PDO class
 *
 * This class extends PDO but overrides all of its methods. It does this so
 * that instanceof checks and type-hinting of existing code will work
 * seamlessly.
 */
class Pdo_Oci8 extends PDO
{
    /**
     * Database handler
     *
     * @var resource
     */
    protected $_dbh;

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
    protected $_isTransaction = false;

    /**
     * Constructor
     *
     * @param string $dsn
     * @param string $username
     * @param string $passwd
     * @param array $options
     * @return void
     */
    public function __construct($dsn,
                                $username = null,
                                $password = null,
                                array $options = array())
    {
        $parsedDsn = Pdo_Util::parseDsn($dsn, array('dbname', 'charset'));

        if (isset($options[PDO::ATTR_PERSISTENT])
            && $options[PDO::ATTR_PERSISTENT]) {

            $this->_dbh = @oci_pconnect(
                $username,
                $password,
                $parsedDsn['dbname'],
                $parsedDsn['charset']);

        } else {

            $this->_dbh = @oci_connect(
                $username,
                $password,
                $parsedDsn['dbname'],
                $parsedDsn['charset']);

        }

        if (!$this->_dbh) {
            $e = oci_error();
            throw new PDOException($e['message']);
        }

        $this->_options = $options;
    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param string $statement
     * @param array $options
     * @return Pdo_Oci8_Statement
     */
    public function prepare($statement, $options = null)
    {
        $sth = @oci_parse($this->_dbh, $statement);

        if (!$sth) {
            $e = oci_error($this->_dbh);
            throw new PDOException($e['message']);
        }

        if (!is_array($options)) {
            $options = array();
        }

        return new Pdo_Oci8_Statement($sth, $this, $options);
    }

    /**
     * Begins a transaction (turns off autocommit mode)
     *
     * @return void
     */
    public function beginTransaction()
    {
        if ($this->isTransaction()) {
            throw new PDOException('There is already an active transaction');
        }

        $this->_isTransaction = true;
        return true;
    }

    /**
     * Returns true if the current process is in a transaction
     *
     * @return bool
     */
    public function isTransaction()
    {
        return $this->_isTransaction;
    }

    /**
     * Commits all statements issued during a transaction and ends the transaction
     *
     * @return bool
     */
    public function commit()
    {
        if (!$this->isTransaction()) {
            throw new PDOException('There is no active transaction');
        }

        if (oci_commit($this->_dbh)) {
            $this->_isTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Rolls back a transaction
     *
     * @return bool
     */
    public function rollBack()
    {
        if (!$this->isTransaction()) {
            throw new PDOException('There is no active transaction');
        }

        if (oci_rollback($this->_dbh)) {
            $this->_isTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Sets an attribute on the database handle
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
     * Executes an SQL statement and returns the number of affected rows
     *
     * @param string $query
     * @return int The number of rows affected
     */
    public function exec($query)
    {
        $stmt = $this->prepare($query);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Executes an SQL statement, returning the results as a Pdo_Oci8_Statement
     *
     * @param string $query
     * @param int|null $fetchType
     * @param mixed|null $typeArg
     * @param array|null $ctorArgs
     * @return Pdo_Oci8_Statement
     * @todo Implement support for $fetchType, $typeArg, and $ctorArgs.
     */
    public function query($query,
                          $fetchType = null,
                          $typeArg = null,
                          array $ctorArgs = array())
    {
        $stmt = $this->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Issues a PHP warning, just as with the PDO_OCI driver
     *
     * Oracle does not support the last inserted ID functionality like MySQL.
     * You must implement this yourself by returning the sequence ID from a
     * stored procedure, for example.
     *
     * @param string $name Sequence name; no use in this context
     * @return void
     */
    public function lastInsertId($name = null)
    {
        trigger_error(
            'SQLSTATE[IM001]: Driver does not support this function: driver does not support lastInsertId()',
            E_USER_WARNING);
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
        $e = oci_error($this->_dbh);

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
     * Retrieve a database connection attribute
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
     * Quotes a string for use in a query
     *
     * @param string $string
     * @param int $paramType
     * @return string
     * @todo Implement support for $paramType.
     */
    public function quote($string, $paramType = PDO::PARAM_STR)
    {
        return "'" . str_replace("'", "''", $string) . "'";
    }
}

<?php

/**
 * PDO userspace driver proxying calls to PHP OCI8 driver.
 *
 * @category  Database
 *
 * @author    Arjay Angeles <aqangeles@gmail.com>
 * @copyright Copyright (c) 2013 Arjay Angeles
 * @license   MIT
 */

namespace Yajra\Pdo;

use Exception;
use OCICollection;
use OCILob;
use PDO;
use PDOStatement;
use Yajra\Pdo\Oci8\Exceptions\Oci8Exception;
use Yajra\Pdo\Oci8\Statement;

/**
 * Oci8 class to mimic the interface of the PDO class
 * This class extends PDO but overrides all of its methods. It does this so
 * that instanceof checks and type-hinting of existing code will work
 * seamlessly.
 */
class Oci8 extends PDO
{
    /**
     * Database handler.
     *
     * @var resource
     */
    private $dbh;

    /**
     * Driver options.
     *
     * @var array
     */
    private array $options = [];

    /**
     * Whether currently in a transaction.
     *
     * @var bool
     */
    private bool $inTransaction = false;

    /**
     * Insert query statement table variable.
     *
     * @var string
     */
    private string $table;

    /**
     * Creates a PDO instance representing a connection to a database.
     *
     * Supports any connection string that is supported by oci_connect(),
     * or a valid PDO-style DSN (oci:host=host;port=port;dbname=dbname;charset=charset)
     *
     * @link https://www.php.net/manual/en/function.oci-connect.php
     * @link https://www.php.net/manual/en/pdo.construct.php
     *
     * @param  string  $dsn
     * @param  string|null  $username
     * @param  string|null  $password
     * @param  array|null  $options
     *
     * @throws Oci8Exception
     */
    public function __construct(string $dsn, ?string $username, ?string $password, ?array $options = [])
    {
        $dsn = trim($dsn);
        if (str_starts_with($dsn, 'oci:')) {
            $connectStr = preg_replace('/^oci:/', '', $dsn);
            parse_str(str_replace(';', '&', $connectStr), $connectParams);
            if (empty($connectParams['dbname'])) {
                throw new Oci8Exception('Invalid connection string');
            } else {
                $dsnStr = str_replace('//', '', $connectParams['dbname']);
                if (isset($connectParams['host']) && isset($connectParams['port'])) {
                    $host = $connectParams['host'].':'.$connectParams['port'];
                } elseif (isset($connectParams['host'])) {
                    $host = $connectParams['host'];
                }
                if (! empty($host)) {
                    $dsnStr = $host.'/'.$dsnStr;
                }
                // A charset specified in the connection string takes
                // precedence over one specified in $options
                ! empty($connectParams['charset']) ? $charset = $this->configureCharset(
                    $connectParams
                ) : $charset = $this->configureCharset($options);
                $dsn = $dsnStr;
            }
        } else {
            $charset = $this->configureCharset($options);
        }
        $this->connectOracle($dsn, $username, $password, $options, $charset);
        // Save the options
        $this->options = $options;
    }

    /**
     * Configure proper charset.
     *
     * @param  array  $options
     * @return string
     */
    private function configureCharset(array $options): string
    {
        $defaultCharset = 'AL32UTF8';
        if (! empty($options['charset'])) {
            // Convert UTF8 charset to AL32UTF8
            return strtolower($options['charset']) == 'utf8' ? $defaultCharset : $options['charset'];
        }

        return $defaultCharset;
    }

    /**
     * Connect to database.
     *
     * @param  string  $dsn
     * @param  string  $username
     * @param  string  $password
     * @param  array  $options
     * @param  string  $charset
     *
     * @throws Oci8Exception
     */
    private function connectOracle(string $dsn, string $username, string $password, array $options, string $charset)
    {
        $sessionMode = array_key_exists('session_mode', $options) ? $options['session_mode'] : OCI_DEFAULT;
        $cached = array_key_exists('cached', $options) ? $options['cached'] : true;

        if (array_key_exists(PDO::ATTR_PERSISTENT, $options) && $options[PDO::ATTR_PERSISTENT]) {
            $this->dbh = oci_pconnect($username, $password, $dsn, $charset, $sessionMode);
        } else {
            if ($cached) {
                $this->dbh = oci_connect($username, $password, $dsn, $charset, $sessionMode);
            } else {
                $this->dbh = oci_new_connect($username, $password, $dsn, $charset, $sessionMode);
            }
        }

        if (! $this->dbh) {
            $e = oci_error();

            $ignoreMessageList = array_key_exists('ignore_error_messages', $options) ? $options['ignore_error_messages'] : ['the password will expire within'];

            $throwError = true;

            foreach ($ignoreMessageList as $str) {
                if (str_contains($e['message'], $str)) {
                    $throwError = false;
                }
            }

            if ($throwError) {
                throw new Oci8Exception($e['message'], $e['code']);
            }
        }
    }

    /**
     * Return available drivers
     * Will insert the OCI driver on the list, if not exist.
     *
     * @return array with drivers
     */
    public static function getAvailableDrivers(): array
    {
        $drivers = PDO::getAvailableDrivers();
        if (! in_array('oci', $drivers)) {
            $drivers[] = 'oci';
        }

        return $drivers;
    }

    /**
     * Initiates a transaction.
     *
     * @return bool TRUE on success or FALSE on failure
     *
     * @throws Oci8Exception
     */
    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            throw new Oci8Exception('There is already an active transaction');
        }

        $this->inTransaction = true;

        return true;
    }

    /**
     * Checks if inside a transaction.
     *
     * @return bool TRUE if a transaction is currently active, and FALSE if not.
     */
    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    /**
     * Returns true if the current process is in a transaction.
     *
     * @return bool
     *
     * @deprecated Use inTransaction() instead
     */
    public function isTransaction(): bool
    {
        return $this->inTransaction();
    }

    /**
     * Commits a transaction.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function commit(): bool
    {
        if (oci_commit($this->dbh)) {
            $this->inTransaction = false;

            return true;
        }

        return false;
    }

    /**
     * Rolls back a transaction.
     *
     * @return bool TRUE on success or FALSE on failure.
     *
     * @throws Oci8Exception
     */
    public function rollBack(): bool
    {
        if (! $this->inTransaction()) {
            throw new Oci8Exception('There is no active transaction');
        }

        if (oci_rollback($this->dbh)) {
            $this->inTransaction = false;

            return true;
        }

        return false;
    }

    /**
     * Sets an attribute on the database handle.
     *
     * @param  int  $attribute
     * @param  mixed  $value
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        $this->options[$attribute] = $value;

        return true;
    }

    /**
     * Executes an SQL statement and returns the number of affected rows.
     *
     * @param  string  $statement  The SQL statement to prepare and execute.
     * @return int The number of rows that were modified or deleted by the SQL
     *             statement you issued.
     */
    public function exec(string $statement): int
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param  string  $query  This must be a valid SQL statement for the
     *                         target database server.
     * @param  array|null  $options  [optional] This array holds one or more key=>value
     *                               pairs to set attribute values for the PDOStatement object that this
     *                               method returns.
     * @return Statement
     *
     * @throws Oci8Exception
     */
    public function prepare(string $query, ?array $options = null): PDOStatement
    {
        // Get instance options
        if ($options == null) {
            $options = $this->options;
        }

        // Skip replacing ? with a pseudo named parameter on alter/create table command
        if ($this->isNamedParameterable($query)) {
            // Replace ? with a pseudo named parameter
            $parameter = 0;
            $query = preg_replace_callback('/(?:\'[^\']*\')(*SKIP)(*F)|\?/', function () use (&$parameter) {
                return ':p'.$parameter++;
            }, $query);
        }

        // check if statement is insert function
        if (str_contains(strtolower($query), 'insert into')) {
            preg_match('/insert into\s+([^\s\(]*)?/', strtolower($query), $matches);
            // store insert into table name
            $this->table = $matches[1];
        }

        // Prepare the statement
        $sth = @oci_parse($this->dbh, $query);

        if (! $sth) {
            $e = oci_error($this->dbh);
            throw new Oci8Exception($e['message']);
        }

        if (! is_array($options)) {
            $options = [];
        }

        return new Statement($sth, $this, $options);
    }

    /**
     * Check if statement can use pseudo named parameter.
     *
     * @param  string  $statement
     * @return bool
     */
    private function isNamedParameterable(string $statement): bool
    {
        return ! preg_match('/^alter+ +table/', strtolower(trim($statement))) and ! preg_match(
            '/^create+ +table/',
            strtolower(trim($statement))
        );
    }

    /**
     * returns the current value of the sequence related to the table where
     * record is inserted by default. The sequence name should follow this for it to work
     * properly:
     *   {$table}.'_id_seq'
     * If the sequence name is passed, then the function will check using that value.
     * Oracle does not support the last inserted ID functionality like MySQL.
     * If the above sequence does not exist, the method will return 0;.
     *
     * @param  string|null  $name  Sequence name
     * @return false|string Last sequence number or 0 if sequence does not exist
     *
     * @throws \ReflectionException
     */
    public function lastInsertId(?string $name = null): false|string
    {
        if (! isset($this->table)) {
            return 0;
        }

        if (is_null($name)) {
            $name = $this->table.'_id_seq';
        }

        if (! $this->checkSequence($name)) {
            return 0;
        }

        $stmt = $this->query("SELECT $name.CURRVAL FROM DUAL", PDO::FETCH_COLUMN);

        return $stmt->fetch();
    }

    /**
     * Special non PDO function to check if sequence exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function checkSequence(string $name): bool
    {
        try {
            $stmt = $this->query(
                "SELECT count(*) FROM ALL_SEQUENCES WHERE SEQUENCE_NAME=UPPER('$name') AND SEQUENCE_OWNER=UPPER(USER)",
                PDO::FETCH_COLUMN
            );

            return $stmt->fetch();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Executes an SQL statement, returning the results as a
     * Yajra\Pdo\Oci8\Statement object.
     *
     * @link https://php.net/manual/en/pdo.query.php
     *
     * @param  string  $statement  <p>
     *                             The SQL statement to prepare and execute.
     *                             </p>
     *                             <p>
     *                             Data inside the query should be properly escaped.
     *                             </p>
     * @param  int  $mode  The fetch mode must be one of the PDO::FETCH_* constants.
     * @param  mixed  $fetch_mode_args  <p>
     *                                  Arguments of custom class constructor when the <i>mode</i>
     *                                  parameter is set to <b>PDO::FETCH_CLASS</b>.
     *                                  </p>
     * @return Statement <b>PDO::query</b> returns a PDOStatement object, or <b>FALSE</b>
     *                   on failure.
     *
     * @see  PDOStatement::setFetchMode For a full description of the second and following parameters.
     */
    public function query($statement, $mode = null, ...$fetch_mode_args): PDOStatement
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        if ($mode) {
            $stmt->setFetchMode($mode, $fetch_mode_args);
        }

        return $stmt;
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the database
     * handle.
     * While this returns an error code, it merely emulates the action. If
     * there are no errors, it returns the success SQLSTATE code (00000).
     * If there are errors, it returns HY000. See errorInfo() to retrieve
     * the actual Oracle error code and message.
     *
     * @return string
     */
    public function errorCode(): string
    {
        $error = $this->errorInfo();

        return $error[0];
    }

    /**
     * Returns extended error information for the last operation on the database handle.
     * The array consists of the following fields:
     *   0  SQLSTATE error code (a five characters alphanumeric identifier
     *      defined in the ANSI SQL standard).
     *   1  Driver-specific error code.
     *   2  Driver-specific error message.
     *
     * @return array Error information
     */
    public function errorInfo(): array
    {
        $e = $this->dbh ? oci_error($this->dbh) : null;

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
     * Retrieve a database connection attribute.
     *
     * @param  int  $attribute
     * @return mixed A successful call returns the value of the requested PDO attribute.
     *               An unsuccessful call returns null.
     */
    public function getAttribute(int $attribute): mixed
    {
        if ($attribute == PDO::ATTR_DRIVER_NAME) {
            return 'oci8';
        }

        if (isset($this->options[$attribute])) {
            return $this->options[$attribute];
        }

        return null;
    }

    /**
     * Special non PDO function used to start cursors in the database
     * Remember to call oci_free_statement() on your cursor.
     *
     * @return resource|false New statement handle, or FALSE on error.
     */
    public function getNewCursor(): mixed
    {
        return oci_new_cursor($this->dbh);
    }

    /**
     * Special non PDO function used to start descriptor in the database
     * Remember to call oci_free_statement() on your cursor.
     *
     * @param  int  $type  One of OCI_DTYPE_FILE, OCI_DTYPE_LOB or OCI_DTYPE_ROWID.
     * @return bool|\OCILob New LOB or FILE descriptor on success, FALSE on error.
     */
    public function getNewDescriptor(int $type = OCI_D_LOB): bool|OCILob
    {
        return oci_new_descriptor($this->dbh, $type);
    }

    /**
     * Special non PDO function used to close an open cursor in the database.
     *
     * @param  resource  $cursor  A valid OCI statement identifier.
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function closeCursor($cursor): bool
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
     * @param  string  $string  The string to be quoted.
     * @param  int  $type  Provides a data type hint for drivers that have
     *                     alternate quoting styles
     * @return string|false Returns a quoted string that is theoretically safe to pass
     *                      into an SQL statement.
     *
     * @todo Implement support for $paramType.
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        if (is_numeric($string)) {
            return $string;
        }

        return "'".str_replace("'", "''", $string)."'";
    }

    /**
     * Sets a timeout limiting the maximum time a database round-trip.
     *
     * @param  int  $time_out
     * @return bool
     */
    public function setCallTimeout(int $time_out): bool
    {
        if (! $this->dbh) {
            return false;
        }

        return oci_set_call_timeout($this->dbh, $time_out);
    }

    /**
     * Set the client identifier.
     *
     * @param  $identifier
     * @return bool
     */
    public function setClientIdentifier($identifier): bool
    {
        if (! $this->dbh) {
            return false;
        }

        return oci_set_client_identifier($this->dbh, $identifier);
    }

    /**
     * Special non PDO function
     * Allocates new collection object.
     *
     * @param  string  $typeName  Should be a valid named type (uppercase).
     * @param  string|null  $schema  Should point to the scheme, where the named type was created.
     *                               The name of the current user is the default value.
     * @return false|OCICollection
     */
    public function getNewCollection(string $typeName, ?string $schema = null): false|OCICollection
    {
        return oci_new_collection($this->dbh, $typeName, $schema);
    }

    /**
     * Special not PDO function.
     * Get options used in creating the connection.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Special non PDO function to get DB resource.
     *
     * @return resource
     */
    public function getResource()
    {
        return $this->dbh;
    }

    /**
     * Close the connection when object is removed.
     *
     * @link https://www.php.net/manual/en/pdo.connections.php PDO should remove the connection
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Close the connection.
     *
     * @link https://www.oracle.com/technetwork/topics/php/php-scalability-ha-twp-128842.pdf oci_close should be called
     *       if the connection is pooled
     */
    public function close()
    {
        if ($this->dbh) {
            oci_close($this->dbh);
            $this->dbh = null;
        }
    }
}

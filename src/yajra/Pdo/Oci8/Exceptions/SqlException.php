<?php
namespace yajra\Pdo\Oci8\Exceptions;

class SqlException extends \PDOException
{
    /**
	 * The variable for error information.
	 *
	 * @var errorInfo
	 */
	public $errorInfo;
}

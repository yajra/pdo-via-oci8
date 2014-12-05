<?php namespace yajra\Pdo\Oci8\Exceptions;

use PDOException;

class Oci8Exception extends PDOException {

    /**
	 * The variable for error information.
	 *
	 * @var errorInfo
	 */
	public $errorInfo;

}

<?php
/**
 * PDO Userspace Driver for Oracle (oci8)
 *
 * @category Database
 * @package Pdo
 * @subpackage Oci8
 * @author Ben Ramsey <ramsey@php.net>
 * @copyright Copyright (c) 2009 Ben Ramsey
 * @license http://opensource.org/licenses/mit-license.php  MIT License
 */

/**
 * A static utility class for PDO userspace classes
 */
class Pdo_Util
{
    /**
     * Parses a DSN string according to the rules in the PHP manual
     *
     * See also the PDO_User::parseDSN method in pecl/pdo_user. This method
     * mimics the functionality provided by that method.
     *
     * @param string $dsn
     * @param array $params
     * @return array
     * @link http://www.php.net/manual/en/pdo.construct.php
     */
    public static function parseDsn($dsn, array $params)
    {
        if (strpos($dsn, ':') !== false) {
            $driver = substr($dsn, 0, strpos($dsn, ':'));
            $vars = substr($dsn, strpos($dsn, ':') + 1);

            if ($driver == 'uri') {
                return self::parseDsn(file_get_contents($vars), $params);
            } else {
                $returnParams = array();
                foreach (explode(';', $vars) as $var) {
                    $param = explode('=', $var);
                    if (in_array($param[0], $params)) {
                        $returnParams[$param[0]] = $param[1];
                    }
                }
                return $returnParams;
            }
        } else if (strlen(trim($dsn)) > 0) {
            // The DSN passed in must be an alias set in php.ini
            return self::parseDsn(ini_get("pdo.dsn.{$dsn}"), $params);
        }

        return array();
    }
}

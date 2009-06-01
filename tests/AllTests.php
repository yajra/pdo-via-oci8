<?php
/**
 * PDO Userspace Driver for Oracle (oci8)
 *
 * @category Tests
 * @package Pdo
 * @subpackage Oci8
 * @author Ben Ramsey <ramsey@php.net>
 * @copyright Copyright (c) 2009 Ben Ramsey
 * @license http://opensource.org/licenses/mit-license.php  MIT License
 */

set_include_path(
    realpath(dirname(__FILE__) . '/../library')
    . PATH_SEPARATOR
    . get_include_path()
);

/**
 * Require the PHPUnit testing framework
 */
require_once 'PHPUnit/Framework.php';

/**#@+
 * Require the test classes
 */
require_once 'Pdo/UtilTest.php';
require_once 'Pdo/Oci8Test.php';
/**#@-*/

class AllTests
{
    /**
     * Test suite handler
     *
     * @return PHPUnit_Framework_TestSuite
     */
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite(
            'PDO Userspace Driver for Oracle (oci8)');

        $suite->addTestSuite('Pdo_UtilTest');
        $suite->addTestSuite('Pdo_Oci8Test');

        return $suite;
    }
}

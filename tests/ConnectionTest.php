<?php

use Yajra\Pdo\Oci8;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    protected $con = null;

    /**
     * Set up a new object
     *
     * @return null
     */
    public function setUp()
    {
        $user = getenv("OCI_USER") ?: 'system';
        $pwd = getenv("OCI_PWD") ?: 'oracle';
        $dsn = getenv("OCI_DSN") ?: 'oci:dbname=127.0.0.1:49161/xe';
        $this->con = new Oci8($dsn, $user, $pwd);
    }

    /**
     * Test if it is a valid object
     *
     * @return null
     */
    public function testObject()
    {
        $this->assertNotNull($this->con);
    }

    public function testBindParamSingle()
    {
        $stmt = $this->con->prepare('INSERT INTO person (name) VALUES (?)');
        $var = 'Joop';
        $this->assertTrue($stmt->bindParam(1, $var, PDO::PARAM_STR));
    }

    public function testBindParamMultiple()
    {
        $stmt = $this->con->prepare('INSERT INTO person, email (name) VALUES (:person, :email)');
        $var = 'Joop';
        $email = 'joop@world.com';
        $this->assertTrue($stmt->bindParam(':person', $var, PDO::PARAM_STR));
        $this->assertTrue($stmt->bindParam(':email', $email, PDO::PARAM_STR));
    }
}

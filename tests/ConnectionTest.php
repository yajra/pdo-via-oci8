<?php

use Yajra\Pdo\Oci8;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    const DEFAULT_USER = 'system';
    const DEFAULT_PWD  = 'oracle';
    const DEFAULT_DSN  = 'oci:dbname=127.0.0.1:49161/xe';

    /**
     * @var Oci8
     */
    protected $con = null;

    /**
     * Set up a new object.
     *
     * @return null
     */
    public function setUp()
    {
        $user      = getenv('OCI_USER') ?: self::DEFAULT_USER;
        $pwd       = getenv('OCI_PWD') ?: self::DEFAULT_PWD;
        $dsn       = getenv('OCI_DSN') ?: self::DEFAULT_DSN;
        $this->con = new Oci8($dsn, $user, $pwd);
    }

    /**
     * Test if it is a valid object.
     *
     * @return null
     */
    public function testObject()
    {
        $this->assertNotNull($this->con);
    }

    /**
     * Test if can connect using persistent connections.
     *
     * @return null
     */
    public function testPersistentConnection()
    {
        $user = getenv('OCI_USER') ?: self::DEFAULT_USER;
        $pwd  = getenv('OCI_PWD') ?: self::DEFAULT_PWD;
        $dsn  = getenv('OCI_DSN') ?: self::DEFAULT_DSN;
        $con  = new Oci8($dsn, $user, $pwd, [\PDO::ATTR_PERSISTENT => true]);
        $this->assertNotNull($con);
    }

    /**
     * Test if can connect, using parameters.
     *
     * @return null
     */
    public function testConnectionWithParameters()
    {
        $user = getenv('OCI_USER') ?: self::DEFAULT_USER;
        $pwd  = getenv('OCI_PWD') ?: self::DEFAULT_PWD;
        $dsn  = getenv('OCI_DSN') ?: self::DEFAULT_DSN;
        $con  = new Oci8("$dsn;charset=utf8", $user, $pwd);
        $this->assertNotNull($con);
    }

    /**
     * Test if throws an exception when failing to open connection.
     */
    public function testInvalidConnection()
    {
        $user = 'pdooci';
        $pwd  = 'pdooci';
        $str  = 'oci:dbname=127.0.0.1:49161/hoi';
        $this->expectException(Oci8\Exceptions\Oci8Exception::class);
        new Oci8($str, $user, $pwd, [\PDO::ATTR_PERSISTENT => true]);
    }

    /**
     * Set and get an attribute.
     *
     * @return null
     */
    public function testAttributes()
    {
        $this->con->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
        $this->assertTrue($this->con->getAttribute(\PDO::ATTR_AUTOCOMMIT));
    }

    /**
     * Test the error code.
     *
     * @return null
     */
    public function testErrorCode()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionCode(942);
        $this->con->exec("insert into bones (skull) values ('lucy')");
    }

    /**
     * Test if OCI is present on the available drivers.
     *
     * @return null
     */
    public function testDrivers()
    {
        $this->assertTrue(in_array('oci', $this->con->getAvailableDrivers()));
    }

    /**
     * Test if is on a transaction.
     *
     * @return null
     */
    public function testInTransaction()
    {
        $this->con->beginTransaction();
        $this->assertTrue($this->con->inTransaction());
        $this->con->commit();
        $this->assertFalse($this->con->inTransaction());
    }

    /**
     * Test quotes.
     *
     * @return null
     */
    public function testQuote()
    {
        $this->assertEquals("'Nice'", $this->con->quote('Nice'));
        $this->assertEquals("'Naughty '' string'", $this->con->quote('Naughty \' string'));
    }

    /**
     * Test if fails if requiring the last inserted id without a sequence.
     *
     * @return null
     */
    public function testLastIdWithoutSequence()
    {
        $this->assertEquals(0, $this->con->lastInsertId());
    }

    /**
     * Test if returns the last inserted id with a sequence.
     *
     * @return null
     */
    public function testLastIdWithSequence()
    {
        $id = $this->con->lastInsertId('person_sequence');
        $this->assertTrue(is_numeric($id));
    }

    public function testCaseDefaultValue()
    {
        $case = $this->con->getAttribute(\PDO::ATTR_CASE);
        $this->assertEquals(\PDO::CASE_NATURAL, $case);
    }

    /**
     * Test setting case.
     * @param int $case
     * @dataProvider caseProvider
     */
    public function testSettingCase($case)
    {
        $this->con->setAttribute(\PDO::ATTR_CASE, $case);
        $this->assertEquals($case, $this->con->getAttribute(\PDO::ATTR_CASE));
    }

    public function caseProvider()
    {
        return [
            [\PDO::CASE_LOWER],
            [\PDO::CASE_UPPER],
        ];
    }

    public function testQuery()
    {
        $statement = $this->con->query('SELECT table_name FROM user_tables', null, null, null);
        $this->assertInstanceOf(PDOStatement::class, $statement);
    }

    public function testClose()
    {
        $this->con->close();
        $this->assertEquals(['00000', null, null], $this->con->errorInfo());
    }

    public function testBindParamSingle()
    {
        $stmt = $this->con->prepare('INSERT INTO person (name) VALUES (?)');
        $var  = 'Joop';
        $this->assertTrue($stmt->bindParam(1, $var, PDO::PARAM_STR));
    }

    public function testBindParamMultiple()
    {
        $stmt  = $this->con->prepare('INSERT INTO person, email (name) VALUES (:person, :email)');
        $var   = 'Joop';
        $email = 'joop@world.com';
        $this->assertTrue($stmt->bindParam(':person', $var, PDO::PARAM_STR));
        $this->assertTrue($stmt->bindParam(':email', $email, PDO::PARAM_STR));
    }

    public function testSetConnectionIdentifier()
    {
        $expectedIdentifier = "PDO_OCI8_CON";

        $user = getenv('OCI_USER') ?: self::DEFAULT_USER;
        $pwd = getenv('OCI_PWD') ?: self::DEFAULT_PWD;
        $dsn = getenv('OCI_DSN') ?: self::DEFAULT_DSN;
        $con = new Oci8($dsn, $user, $pwd);
        $this->assertNotNull($con);

        $con->setClientIdentifier($expectedIdentifier);
        $stmt = $con->query("SELECT SYS_CONTEXT('USERENV','CLIENT_IDENTIFIER') as IDENTIFIER FROM DUAL");
        $foundClientIdentifier = $stmt->fetchColumn(0);
        $con->close();

        $this->assertEquals($expectedIdentifier, $foundClientIdentifier);
    }
}

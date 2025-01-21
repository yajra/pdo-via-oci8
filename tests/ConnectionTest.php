<?php

use PHPUnit\Framework\TestCase;
use Yajra\Pdo\Oci8;

class ConnectionTest extends TestCase
{
    const DEFAULT_USER = 'system';
    const DEFAULT_PWD = 'oracle';
    const DEFAULT_DSN = 'oci:dbname=127.0.0.1:1521/free';

    protected ?Oci8 $con = null;

    /**
     * Set up a new object.
     */
    public function setUp(): void
    {
        $user = getenv('OCI_USER') ?: self::DEFAULT_USER;
        $pwd = getenv('OCI_PWD') ?: self::DEFAULT_PWD;
        $dsn = getenv('OCI_DSN') ?: self::DEFAULT_DSN;
        $this->con = new Oci8($dsn, $user, $pwd, [PDO::ATTR_CASE => PDO::CASE_NATURAL]);
    }

    /**
     * Test if it is a valid object.
     */
    public function testObject(): void
    {
        $this->assertNotNull($this->con);
    }

    /**
     * Test if can connect using persistent connections.
     */
    public function testPersistentConnection(): void
    {
        $user = getenv('OCI_USER') ?: self::DEFAULT_USER;
        $pwd = getenv('OCI_PWD') ?: self::DEFAULT_PWD;
        $dsn = getenv('OCI_DSN') ?: self::DEFAULT_DSN;
        $con = new Oci8($dsn, $user, $pwd, [PDO::ATTR_PERSISTENT => true]);
        $this->assertNotNull($con);
    }

    /**
     * Test if can connect, using parameters.
     */
    public function testConnectionWithParameters(): void
    {
        $user = getenv('OCI_USER') ?: self::DEFAULT_USER;
        $pwd = getenv('OCI_PWD') ?: self::DEFAULT_PWD;
        $dsn = getenv('OCI_DSN') ?: self::DEFAULT_DSN;
        $con = new Oci8("$dsn;charset=utf8", $user, $pwd);
        $this->assertNotNull($con);
    }

    /**
     * Test if throws an exception when failing to open connection.
     */
    public function testInvalidConnection(): void
    {
        $user = 'pdooci';
        $pwd = 'pdooci';
        $str = 'oci:dbname=127.0.0.1:1521/hoi';
        try {
            new Oci8($str, $user, $pwd, [PDO::ATTR_PERSISTENT => true]);
        } catch (Exception $e) {
            $this->assertStringContainsString('ORA-12514', $e->getMessage());
        }
    }

    /**
     * Set and get an attribute.
     */
    public function testAttributes(): void
    {
        $this->con->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
        $this->assertTrue($this->con->getAttribute(PDO::ATTR_AUTOCOMMIT));
    }

    /**
     * Test the error code.
     */
    public function testErrorCode(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionCode(942);
        $this->con->exec("insert into bones (skull) values ('lucy')");
    }

    /**
     * Test if OCI is present on the available drivers.
     */
    public function testDrivers(): void
    {
        $this->assertTrue(in_array('oci', $this->con->getAvailableDrivers()));
    }

    /**
     * Test if is on a transaction.
     */
    public function testInTransaction(): void
    {
        $this->con->beginTransaction();
        $this->assertTrue($this->con->inTransaction());
        $this->con->commit();
        $this->assertFalse($this->con->inTransaction());
    }

    /**
     * Test quotes.
     */
    public function testQuote(): void
    {
        $this->assertEquals("'Nice'", $this->con->quote('Nice'));
        $this->assertEquals("'Naughty '' string'", $this->con->quote('Naughty \' string'));
    }

    /**
     * Test if fails if requiring the last inserted id without a sequence.
     *
     * @throws \ReflectionException
     */
    public function testLastIdWithoutSequence(): void
    {
        $this->assertEquals(0, $this->con->lastInsertId());
    }

    /**
     * Test if returns the last inserted id with a sequence.
     *
     * @throws \ReflectionException
     */
    public function testLastIdWithSequence(): void
    {
        $id = $this->con->lastInsertId('person_sequence');
        $this->assertTrue(is_numeric($id));
    }

    public function testCaseDefaultValue(): void
    {
        $case = $this->con->getAttribute(PDO::ATTR_CASE);
        $this->assertEquals(PDO::CASE_NATURAL, $case);
    }

    /**
     * Test setting case.
     *
     * @dataProvider caseProvider
     */
    public function testSettingCase(int $case): void
    {
        $this->con->setAttribute(PDO::ATTR_CASE, $case);
        $this->assertEquals($case, $this->con->getAttribute(PDO::ATTR_CASE));
    }

    public function caseProvider(): array
    {
        return [
            [PDO::CASE_LOWER],
            [PDO::CASE_UPPER],
        ];
    }

    public function testQuery(): void
    {
        $statement = $this->con->query('SELECT table_name FROM user_tables', null, null, null);
        $this->assertInstanceOf(PDOStatement::class, $statement);
    }

    public function testClose(): void
    {
        $this->con->close();
        $this->assertEquals(['00000', null, null], $this->con->errorInfo());
    }

    public function testBindParamSingle(): void
    {
        $stmt = $this->con->prepare('INSERT INTO person (name) VALUES (?)');
        $var = 'Joop';
        $this->assertTrue($stmt->bindParam(1, $var, PDO::PARAM_STR));
    }

    public function testBindParamMultiple(): void
    {
        $stmt = $this->con->prepare('INSERT INTO person, email (name) VALUES (:person, :email)');
        $var = 'Joop';
        $email = 'joop@world.com';
        $this->assertTrue($stmt->bindParam(':person', $var, PDO::PARAM_STR));
        $this->assertTrue($stmt->bindParam(':email', $email, PDO::PARAM_STR));
    }

    public function testSetConnectionIdentifier(): void
    {
        $expectedIdentifier = 'PDO_OCI8_CON';

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

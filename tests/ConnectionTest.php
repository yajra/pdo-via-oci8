<?php

use PHPUnit\Framework\TestCase;
use Yajra\Pdo\Oci8;

class ConnectionTest extends TestCase
{
    private const DEFAULT_USER = 'system';
    private const DEFAULT_PWD = 'oracle';
    private const DEFAULT_DSN = 'oci:dbname=127.0.0.1:1521/xe';

    /**
     * @var Oci8
     */
    protected Oci8 $con;

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
            $this->assertMatchesRegularExpression('/ORA-125(14|41)/', $e->getMessage());
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
        $this->assertContains('oci', $this->con::getAvailableDrivers());
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
     * @throws ReflectionException
     */
    public function testLastIdWithoutSequence(): void
    {
        $this->assertEquals(0, $this->con->lastInsertId());
    }

    /**
     * Test if returns the last inserted id with a sequence.
     *
     * @throws ReflectionException
     */
    public function testLastIdWithSequence(): void
    {
        $id = $this->con->lastInsertId('person_sequence');
        $this->assertIsNumeric($id);
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

    public static function caseProvider(): array
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

    /**
     * Test multiple cases with ? replacement within Oracle strings, q-quoted strings and comments
     *
     * @dataProvider provideSqlCases
     */
    public function testRewriteSkipsLiteralsAndComments(array $case): void
    {
        $actualSql = $this->con->rewritePositionalPlaceholders($case['input_sql']);

        $this->assertSame(
            $case['expected_sql'],
            $actualSql,
            $case['message'] ?? 'SQL rewrite assertion failed.'
        );
    }

    public static function provideSqlCases(): iterable
    {
        // 1) SELECT 1 FROM dual WHERE x = ?; → :p0
        yield 'simple_placeholder' => [[
            'input_sql' => 'SELECT 1 FROM dual WHERE x = ?;',
            'expected_sql' => 'SELECT 1 FROM dual WHERE x = :p0;',
            'message' => 'A plain positional placeholder must be rewritten.',
        ]];

        // 2) q'[foo?bar]' → no replacement
        yield 'q_brackets_literal' => [[
            'input_sql' => "SELECT q'[foo?bar]' AS test FROM dual",
            'expected_sql' => "SELECT q'[foo?bar]' AS test FROM dual",
            'message' => 'Question mark inside q\'[...]\' must be ignored.',
        ]];

        // 3) q'~Nom d'utilisateur & login?lang=fr~' → no replacement
        yield 'q_tilde_literal_with_apostrophe_and_qm' => [[
            'input_sql' => "SELECT q'~Nom d'utilisateur & login?lang=fr~' AS test FROM dual",
            'expected_sql' => "SELECT q'~Nom d'utilisateur & login?lang=fr~' AS test FROM dual",
            'message' => 'Question mark inside q\'~...~\' with inner apostrophes must be ignored.',
        ]];

        // 4) 'abc''def?ghi' → no replacement (doubled quote)
        yield 'single_quoted_with_escaped_quote' => [[
            'input_sql' => "SELECT 'abc''def?ghi' AS test FROM dual",
            'expected_sql' => "SELECT 'abc''def?ghi' AS test FROM dual",
            'message' => 'Question mark inside single-quoted literal with doubled quotes must be ignored.',
        ]];

        // 5) -- comment ?\n SELECT ? FROM dual; → only one ? replaced
        yield 'line_comment_then_placeholder' => [[
            'input_sql' => "-- comment ?\nSELECT ? AS test FROM dual;",
            'expected_sql' => "-- comment ?\nSELECT :p0 AS test FROM dual;",
            'message' => 'Question mark in -- comment must be ignored; placeholder after must be rewritten.',
        ]];

        // 6) /* block ? */ SELECT ? FROM dual; → only one ? replaced
        yield 'block_comment_then_placeholder' => [[
            'input_sql' => "/* block ? */ SELECT ? AS test FROM dual;",
            'expected_sql' => "/* block ? */ SELECT :p0 AS test FROM dual;",
            'message' => 'Question mark in /* ... */ must be ignored; placeholder after must be rewritten.',
        ]];

        // 7) DECLARE v VARCHAR2(1000) := q'{<a href=\"?x=1&y=2\">}'; BEGIN NULL; END; → no replacement
        yield 'declare_with_q_braces_html_url' => [[
            'input_sql' => "DECLARE v VARCHAR2(1000) := q'{<a href=\"?x=1&y=2\">}'; BEGIN NULL; END;",
            'expected_sql' => "DECLARE v VARCHAR2(1000) := q'{<a href=\"?x=1&y=2\">}'; BEGIN NULL; END;",
            'message' => 'Question marks inside q\'{...}\' must be ignored.',
        ]];
    }

    /**
     * Test ? replacement within q-quoted strings, comments and plain positional in same SQL
     */
    public function testNumberingIsSequentialOutsideSkips(): void
    {
        $inputSql = <<<SQL
            /* ? in comment */ SELECT a FROM t WHERE x = ? AND y = ? AND z = q'[keep?]';
            SQL;

        $expectedSql = <<<SQL
            /* ? in comment */ SELECT a FROM t WHERE x = :p0 AND y = :p1 AND z = q'[keep?]';
            SQL;

        $this->assertSame($expectedSql, $this->con->rewritePositionalPlaceholders($inputSql));
    }
}

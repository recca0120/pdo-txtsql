<?php

namespace Recca0120\Tests\TxtSQL\Old;

use txtSQL;
use Mockery as m;
use PDOException;
use Recca0120\TxtSQL\PDO;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    protected function setUp()
    {
        $this->path = realpath(__DIR__.'/../../txtSQL/v2.2/data');
        $this->username = 'root';
        $this->passwd = '';
        $this->dbname = 'test';
    }

    protected function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public function test_user_validate_fail()
    {
        try {
            $pdo = new PDO(sprintf('txtsql:dbname=txtsql;path=%s', $this->path), 'root', 'root');
        } catch (PDOException $e) {
            $this->assertInstanceOf('PDOException', $e);
            $this->assertSame(sprintf("SQLSTATE[HY000] [1045] Access denied for user '%s'@'%s' (using password: YES)", 'root', $this->path), $e->getMessage());
            $this->assertSame(1045, $e->getCode());
        }
    }

    public function test_database_not_exists()
    {
        try {
            $pdo = new PDO(sprintf('txtsql:dbname=fake-test;path=%s', $this->path), 'root', '');
        } catch (PDOException $e) {
            $this->assertInstanceOf('PDOException', $e);
            $this->assertSame(sprintf("SQLSTATE[HY000] [1049] Unknown database '%s'", 'fake-test'), $e->getMessage());
            $this->assertSame(1049, $e->getCode());
        }
    }

    public function test_connection()
    {
        $connection = new txtSQL($this->path);
        $this->assertTrue($connection->connect($this->username, $this->passwd));

        $pdo = new PDO(sprintf('txtsql:dbname=%s;file=%s', $this->dbname, $this->path), $this->username, $this->passwd);
    }
}

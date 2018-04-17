<?php

namespace Recca0120\Tests\TxtSQL\Old;

use txtSQL;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    protected function setUp()
    {
        $this->folder = __DIR__.'/../../data';
    }

    protected function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public function test_connection()
    {
        $connection = new txtSQL($this->folder);
        $this->assertTrue($connection->connect('root', ''));
    }
}

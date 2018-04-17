<?php

namespace Recca0120\TxtSQL;

class PDO
{
    /**
     *
     * Constructor.
     *
     * This overrides the parent so that it can take connection attributes as a
     * constructor parameter, and set them after connection.
     *
     * @param string $dsn The data source name for the connection.
     * @param string $username The username for the connection.
     * @param string $password The password for the connection.
     * @param array $options Driver-specific options for the connection.
     *
     * @see http://php.net/manual/en/pdo.construct.php
     *
     */

    public function __construct($dsn, $username = null, $passwd = null, $options = [])
    {
    }
}

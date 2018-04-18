<?php

namespace Recca0120\TxtSQL;

use PDOException;

class PDO extends \PDO
{
    /**
     * The attributes for a lazy connection.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The DSN for a lazy connection.
     *
     * @var string
     */
    protected $dsn;

    /**
     * PDO options for a lazy connection.
     *
     * @var array
     */
    protected $options = [];

    /**
     * The username for a lazy connection.
     *
     * @var string
     */
    protected $username;

    /**
     * The password for a lazy connection.
     *
     * @var string
     */
    protected $password;

    /**
     * Undocumented variable.
     *
     * @var string
     */
    protected $path;

    /**
     * Undocumented variable.
     *
     * @var string
     */
    protected $dbname;

    /**
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
     */
    public function __construct($dsn, $username = 'root', $passwd = null, $options = [])
    {
        // if no error mode is specified, use exceptions
        if (! isset($options[self::ATTR_ERRMODE])) {
            $options[self::ATTR_ERRMODE] = self::ERRMODE_EXCEPTION;
        }

        $parsedDsn = $this->parseDsn($dsn, ['dbname', 'path']);
        $this->txtSQL = new TxtSQL($parsedDsn['path'], $username, $passwd);

        if ($this->txtSQL->connect() === false) {
            throw new PDOException(
                sprintf("SQLSTATE[HY000] [1045] Access denied for user '%s'@'%s' (using password: YES)", $username, $parsedDsn['path']),
                1045
            );
        }

        if ($this->txtSQL->selectDatabase($parsedDsn['dbname']) === false) {
            throw new PDOException(
                sprintf("SQLSTATE[HY000] [1049] Unknown database '%s'", $parsedDsn['dbname']),
                1049
            );
        }
    }

    private function parseDsn($dsn, $params)
    {
        if (strpos($dsn, ':') !== false) {
            $driver = substr($dsn, 0, strpos($dsn, ':'));
            $vars = substr($dsn, strpos($dsn, ':') + 1);

            $returnParams = [
                'driver' => $driver,
            ];
            foreach (explode(';', $vars) as $var) {
                $param = explode('=', $var);
                if (in_array($param[0], $params)) {
                    $returnParams[$param[0]] = $param[1];
                }
            }

            return $returnParams;
        }

        return [];
    }
}

<?php

namespace Recca0120\TxtSQL;

use PDOException;
use Recca0120\TxtSQL\Utils\FileFactory;

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

        $this->fileFactory = new FileFactory;


        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $passwd;
        $this->options = $options;

        $parsedDsn = $this->parseDsn($this->dsn, ['dbname', 'path', 'file']);
        $this->dbname = $parsedDsn['dbname'];
        $this->path = isset($parsedDsn['path']) ? $parsedDsn['path'] : $parsedDsn['file'];
        $this->fileFactory->setPath($this->path);

        $this->connect()->selectDatabase();
    }

    private function connect()
    {
        $userFile = $this->fileFactory->get('txtsql/txtsql.MYI');
        $users = $userFile->getContent();
        $username = strtolower($this->username);
        if (empty($users[$username]) === true || $users[$username] !== md5($this->password)) {
            throw new PDOException(
                sprintf("SQLSTATE[HY000] [1045] Access denied for user '%s'@'%s' (using password: YES)", $this->username, $this->path),
                1045
            );
        }

        return $this;
    }

    private function selectDatabase()
    {
        $dbFolder = $this->fileFactory->get($this->dbname);

        if ($dbFolder->exists() === false) {
            throw new PDOException(
                sprintf("SQLSTATE[HY000] [1049] Unknown database '%s'", $this->dbname),
                1049
            );
        }

        return $this;
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

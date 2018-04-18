<?php

namespace Recca0120\TxtSQL;

use Recca0120\TxtSQL\Database\Finder;

class TxtSQL
{
    private $finder;

    private $username;

    private $password;

    private $dbname;

    private $path;

    private $userFile;

    private $dbFile;

    public function __construct($path, $username = 'root', $passwd = null, Finder $finder = null)
    {
        $this->username = $username;
        $this->password = $passwd;
        $this->path = $path;
        $this->finder = $finder ?: new Finder();
        $this->finder->setPath($this->path);
    }

    public function connect()
    {
        $this->userFile = $this->finder->find('txtsql/txtsql.MYI');
        $users = $this->userFile->getContent();
        $username = strtolower($this->username);

        return empty($users[$username]) === false && $users[$username] === md5($this->password) ? true : false;
    }

    public function selectDatabase($database)
    {
        $this->dbname = $database;
        $this->dbFile = $this->finder->find($this->dbname);

        return $this->dbFile->exists() === true;
    }
}

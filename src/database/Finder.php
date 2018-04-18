<?php

namespace Recca0120\TxtSQL\Database;

class Finder
{
    private $path = '';

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    public function find($file)
    {
        return new Database($this->path, $file);
    }
}

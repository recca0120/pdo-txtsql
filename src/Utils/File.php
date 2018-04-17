<?php

namespace Recca0120\TxtSQL\Utils;

class File
{
    private $file;

    public function __construct($path, $file)
    {
        $this->file = realpath(sprintf('%s/%s', $path, $file));
    }

    public function exists()
    {
        return file_exists($this->file) && is_writable($this->file);
    }

    public function getContent()
    {
        return unserialize(file_get_contents($this->file));
    }
}

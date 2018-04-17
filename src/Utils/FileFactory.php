<?php

namespace Recca0120\TxtSQL\Utils;

class FileFactory
{
    private $path = '';

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    public function get($file)
    {
        return new File($this->path, $file);
    }
}

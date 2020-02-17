<?php

namespace ricwein\FileSystem\Exceptions;

use Exception;

class Hint extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message, 0, null);
    }
}
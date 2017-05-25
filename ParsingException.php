<?php

namespace matveu\parser;

use Exception;

class WarningException extends Exception
{
    public function warning($errno, $errstr)
    {
        if ($errno == E_WARNING) {
            throw new self($errstr);
        }
    }
}

class InvalidUrlException extends Exception
{
    public function __construct()
    {
        $this->message = "Please, set valid URL";
    }
}

class CreateFolderException extends Exception
{
    public function __construct($folderPath)
    {
        $this->message = "Can't create new folder in: " . $folderPath;
    }
}
<?php

namespace matveu\parser;

class WarningException extends \Exception
{
    public function __toString()
    {
        return "Warning: {$this->message} {$this->file} on line {$this->line}n";
    }
}
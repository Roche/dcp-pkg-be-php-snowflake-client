<?php

namespace Dcp\SnowflakeClient\Exceptions;

use Exception;

class InvalidQueryParameter extends Exception
{
    protected $parameter;
    protected $value;

    public function __construct($parameter, $value)
    {
        parent::__construct("Invalid query value for '{$parameter}'.");
        $this->parameter = $parameter;
        $this->value = $value;
    }

    public function getParameter()
    {
        return $this->parameter;
    }

    public function getValue()
    {
        return $this->value;
    }
}

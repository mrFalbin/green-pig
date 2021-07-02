<?php
namespace GreenPig\Exception;



class GreenPigQueryException extends GreenPigException
{
    public function __construct($message, $errorObject, $parametersForDebug = [])
    {
        $this->parametersForDebug = $parametersForDebug;
        parent::__construct($message, $errorObject);
    }
}
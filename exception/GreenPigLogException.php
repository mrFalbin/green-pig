<?php
namespace GreenPig\Exception;



class GreenPigLogException extends GreenPigException
{
    public function __construct($message, $errorObject, $parametersForDebug = [])
    {
        $this->parametersForDebug = $parametersForDebug;
        parent::__construct($message, $errorObject);
    }
}
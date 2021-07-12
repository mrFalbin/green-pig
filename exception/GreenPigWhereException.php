<?php
namespace GreenPig\Exception;



class GreenPigWhereException extends GreenPigException
{
    public function __construct($message, $errorObject, $where)
    {
        $this->parametersForDebug = [
          ['Where:', $where]
        ];
        parent::__construct($message, $errorObject);
    }
}
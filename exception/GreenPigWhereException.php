<?php
namespace GreenPig\Exception;



class GreenPigWhereException extends GreenPigException
{
    public function __construct($message, $errorObject, $where)
    {
        $this->parametersForDebug = [
          ['Логическое выражение where:', $where]
        ];
        parent::__construct($message, $errorObject);
    }
}
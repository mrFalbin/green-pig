<?php
namespace GreenPig\Exception;



class GreenPigDatabaseException extends GreenPigException
{
    public function __construct($message, $errorObject, $sql = null, $bind = null, $sqlWithVal = null, $options = null)
    {
        $this->parametersForDebug = [];
        if ($sql)  $this->parametersForDebug[] = ['sql:', $sql];
        if ($bind)  $this->parametersForDebug[] = ['binds:', $bind];
        if ($sqlWithVal)  $this->parametersForDebug[] = ['sql with val:', $sqlWithVal];
        if ($options)  $this->parametersForDebug[] = ['Настройки подключения:', $options];
        parent::__construct($message, $errorObject);
    }
}
<?php
namespace GreenPig;


use GreenPig\Database\DB;
use GreenPig\Database\MySql;
use GreenPig\Database\Oracle;
use GreenPig\Helpers\Debug;
use GreenPig\Exception\GreenPigException;


class GP
{
    private static $config = [];
    // --- log ---
    const LOG_INFO = 'info';
    const LOG_DEBUG = 'debug';
    const LOG_WARNING = 'warning';
    const LOG_ERROR = 'error';


    public static function config($options)
    {
        // записываем $options в static::$config с минимальной проверкой на правильную структуру
        if (BaseFun::getSettings($options, 'db', false, false) !== null) {
            $options = self::setDefaultParameters($options);
            static::$config['default'] = $options;
        } else {
            // Настройки default должны быть обязательно, либо уже в массиве static::$config, либо в $options.
            // Если нет - выбрасывается исключение.
            if (empty(static::$config['default'])) BaseFun::getSettings($options, 'default', false);
            foreach ($options as $nameConect => $arrSettings) {
                $nameConect = BaseFun::trimLower($nameConect);
                // Если настройки db отсутствуют - будет брошено исключение
                BaseFun::getSettings($arrSettings, 'db', false);
                $arrSettings = self::setDefaultParameters($arrSettings);
                static::$config[$nameConect] = $arrSettings;
            }
        }
    }


    private static function setDefaultParameters($options)
    {
        // ---------- Дефолтные значения для даты ----------
        if (BaseFun::getSettings($options, 'date', false, false) == null) {
            $rdbms = BaseFun::getSettings($options, 'rdbms');
            $phpFormat = 'd.m.Y H:i:s';
            $sqlFormat = '';
            if ($rdbms == 'oracle') $sqlFormat = 'dd.mm.yyyy hh24:mi:ss';
            elseif ($rdbms == 'mysql') $sqlFormat = '%d.%m.%Y %H:%i:%s';
            $options['date'] = [
                'php' => $phpFormat,
                'sql' => $sqlFormat
            ];
        }
        // ---------- Дефолтные значения для дебага ----------
        $debugQuery = BaseFun::getSettings($options, 'debugquery', false, false);
        if ($debugQuery === null) $options['debugquery'] = 100;
        else {
            if (is_int($debugQuery) && $debugQuery >= 0) $options['debugquery'] = $debugQuery;
            else throw new GreenPigException("Invalid parameter numberQuery, it must be an integer (numberQuery >= 0).", $debugQuery);
        }
        return $options;
    }


    public static function configDebugQuery($numberQuery, $nameConnection = 'default')
    {
        $nameConnection = BaseFun::trimLower($nameConnection);
        if (empty(static::$config[$nameConnection])) throw new GreenPigException("Invalid connection name: $nameConnection", static::$config);
        if (is_int($numberQuery) && $numberQuery >= 0) static::$config[$nameConnection]['debugquery'] = $numberQuery;
        else throw new GreenPigException("Invalid parameter numberQuery, it must be an integer (numberQuery >= 0).", $numberQuery);
    }


    public static function configDate($formatDatePhp, $formatDateSql, $nameConnection = 'default')
    {
        $nameConnection = BaseFun::trimLower($nameConnection);
        if (empty(static::$config[$nameConnection])) throw new GreenPigException("Invalid connection name: $nameConnection", static::$config);
        static::$config[$nameConnection]['date'] = [
            'php' => $formatDatePhp,
            'sql' => $formatDateSql
        ];
    }


    public static function configLog($isWrite, $nameConnection = 'default')
    {
        $nameConnection = BaseFun::trimLower($nameConnection);
        if (empty(static::$config[$nameConnection])) throw new GreenPigException("Invalid connection name: $nameConnection", static::$config);
        static::$config[$nameConnection]['log']['iswrite'] = $isWrite;
    }


    public static function instance($nameConnection = 'default')
    {
        if (empty(static::$config[$nameConnection])) throw new GreenPigException("Invalid connection name: $nameConnection", static::$config);
        $rdbms = BaseFun::getSettings(static::$config[$nameConnection], 'rdbms');
        $dbConfig = BaseFun::getSettings(static::$config[$nameConnection], 'db', false);
        if ($rdbms == 'oracle') $db = Oracle::instance($nameConnection, $dbConfig);
        elseif ($rdbms == 'mysql') $db = MySql::instance($nameConnection, $dbConfig);
        else throw new GreenPigException("Incorrect value rdbms: $rdbms (Must be either 'Oracle' or 'MySQL').", static::$config[$nameConnection]);
        return new Query($db, static::$config[$nameConnection], $nameConnection);
    }


    public static function getNamesActiveInstances()
    {
        return DB::getAllNamesInstances();
    }


    public static function disconnect($nameConnection)
    {
        return DB::deleteInstance($nameConnection);
    }


    public static function disconnectAll()
    {
        return DB::deleteAllInstances();
    }


    public static function clearConfig()
    {
        static::$config = [];
        return DB::deleteAllInstances();
    }


    // =================================================================================================================
    //                                                 HELPERS
    // =================================================================================================================

    // ----------------------------------------------------- Debug -----------------------------------------------------

    public static function varDump($var, $title = '', $depth = 10) {
        Debug::$_isGP = true;
        Debug::varDump($var, $title, $depth);
        Debug::$_isGP = false;
    }

    public static function varDumpExport($var, $isHighlight = false, $depth = 10) {
        return Debug::varDumpExport($var, $isHighlight, $depth);
    }

}
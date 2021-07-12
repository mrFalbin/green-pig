<?php
namespace GreenPig;


use GreenPig\Database\DB;
use GreenPig\Database\MySql;
use GreenPig\Database\Oracle;
use GreenPig\Exception\GreenPigException;


class GP
{
    private static $config = [];


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
        if (BaseFun::getSettings($options, 'debug', false, false) == null) {
            $options['debug'] = [
                'isdebug' => true,
                'maxnumberquery' => 100
            ];
        }
        return $options;
    }


    public static function configDate($formatDatePhp, $formatDateSql, $nameConnection = 'default')
    {
        $nameConnection = BaseFun::trimLower($nameConnection);
        if (empty(static::$config[$nameConnection])) throw new GreenPigException("Неверное название подключения: $nameConnection", static::$config);
        static::$config[$nameConnection]['date'] = [
            'php' => $formatDatePhp,
            'sql' => $formatDateSql
        ];
    }


    public static function instance($nameConnection = 'default')
    {
        if (empty(static::$config[$nameConnection])) throw new GreenPigException("Неверное название подключения: $nameConnection", static::$config);
        $rdbms = BaseFun::getSettings(static::$config[$nameConnection], 'rdbms');
        $dbConfig = BaseFun::getSettings(static::$config[$nameConnection], 'db', false);
        if ($rdbms == 'oracle') $db = Oracle::instance($nameConnection, $dbConfig);
        elseif ($rdbms == 'mysql') $db = MySql::instance($nameConnection, $dbConfig);
        else throw new GreenPigException("Неверное название rdbms: $rdbms (Должно быть либо 'Oracle', либо 'MySQL').", static::$config[$nameConnection]);
        return new Query($db, static::$config[$nameConnection]);
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

}
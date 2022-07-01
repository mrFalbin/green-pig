<?php
namespace GreenPig;


use GreenPig\Database\DB;
use GreenPig\Database\MySql;
use GreenPig\Database\Oracle;
use GreenPig\Helpers\Debug;
use GreenPig\Helpers\Arr;
use GreenPig\Helpers\Encoding;
use GreenPig\Exception\GreenPigException;


/**
 *  version:       2.1.2
 *  author:        Falbin
 *  email:         ifalbin@yandex.ru
 *  homepage:      https://falbin.ru
 *  documentation: https://falbin.ru/documentation/gp2/index.html
 *  github:        https://github.com/mrFalbin/green-pig
 *
 *                             ╔═══╗╔═══╗╔═══╗╔═══╗╔╗─╔╗────╔═══╗╔══╗╔═══╗
 *                             ║╔══╝║╔═╗║║╔══╝║╔══╝║╚═╝║────║╔═╗║╚╗╔╝║╔══╝
 *                             ║║╔═╗║╚═╝║║╚══╗║╚══╗║╔╗─║────║╚═╝║─║║─║║╔═╗
 *                             ║║╚╗║║╔╗╔╝║╔══╝║╔══╝║║╚╗║────║╔══╝─║║─║║╚╗║
 *                             ║╚═╝║║║║║─║╚══╗║╚══╗║║─║║────║║───╔╝╚╗║╚═╝║
 *                             ╚═══╝╚╝╚╝─╚═══╝╚═══╝╚╝─╚╝────╚╝───╚══╝╚═══╝
 *
 *
 *                                                                  MMMM:
 *                                                                 MMMMMMMMMA9
 *                                                                 GMMMMMMMMMMMMMM
 *                                                                  SMMMMMMMMMMMMMMM
 *                                                                        ,5HMMMMMMMM
 *                                                                            MMMMMMMM
 *                                                                            GMMMMMMMM
 *                                                                            &MMMMMMMM
 *                                                 23S,.                      MMMMMMMMM
 *                                              MMMMMMMMMMMMMMMMM3i          MMMMMMMMMM
 *                                             MMMMMMMMMMMMMMMMMMMMMMMMMM3AMMMMMMMMMMMH
 *                                         MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *                                        MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *                                        MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *                                       MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *                                      MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *                                HMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMi
 *                            MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *                       rMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM 9MMM2
 *                   :MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM,
 *                 MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *              rMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMB
 *            iMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM5
 *           MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM.
 *           MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *        MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *        MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMr
 *       MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *       MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *      MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *     MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *    MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMi
 *   MM  MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *   M   MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *  3M   MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 *  9M   MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 * .MM   sMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM
 * MMM    MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM5                                    5HHHG
 * MM     MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM                         HH       HHHHHHH
 *        MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM                       9HHHA    HHHHHHHH5
 *        MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM   MMMMMMMMMMMMMMMMM                     HHHHHHHHHHHHHHHHHH  9HHHHH5
 *         MMMMMMMMMMMMM3MMMMMMMMMMMMMA     3MMMMMMM MMMMMMMM                   5HHHHHHHHHHHHHHHHHHHHHHHHHHH
 *          MMMMMMMM     ,MMMMMMMMMMM        MMMMMMM MMMMMMMM                  HHHHHHHHHHHHHHHHHHHHHHHHHHHH
 *          MMMMMMMh      AMMMMMMMMM         ;MMMMMM SMMMMMMM                ;HHHHHHHHHHHHHHHHHHHHHHHHHHA
 *          MMMMMMM       hMMMMMMM            MMMMMM. MMMMMMM                 H2   HHHHHHHHHHHHHHHHHHHHHH
 *          AMMMMMM       MMMMMMMM            MMMMMM  MMMMMMM                      HHHHHHHHHHHHHHHHHHHHHHH9
 *          3MMMMMM      2MMMMMMM            HMMMMMM  MMMMMMM                       HHHHHHHHHHHHHHHHHHHHHHH
 *          9MMMMMM      MMMMMMM             MMMMMMM  MMMMMMM                       AHHHHHHHHHHHHHHHHHHHHHH
 *          MMMMMMM     MMMMMMMM             MMMMMMM  MMMMMMM                        HHHHHHHHHHHHHHHHHHHHH9  iHS
 *          MMMMMMM     MMMMMMMM             MMMMMMi  MMMMMMM                         HHHHHHHHHHHHHHHHHHHHHHhh
 *          MMMMMMM    BMMMMMMMA            MMMMMMM   MMMMMMM                          HHHHHHHHHHHHHHHHHH
 *          MMMMMMM    MMMMMMMMM           MMMMMMMX   MMMMMMM                         AA HHHHHHHHHHHHHH3
 *         9MMMMMMM&   MMMMMMMMM           MMMMMMMi   MMMMMMM                        &H  Hi         HS Hr
 *         MMMMMMMMM                       MMMMMMMMM ;MMMMMMM                        &  H&          H&  Hi
 *
 */
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
        $nameConnection = BaseFun::trimLower($nameConnection);
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

    // ----------------------------------------------------- Array -----------------------------------------------------

    /**
     * @param $arrOrObj array | Object
     * @param $key string | array
     * @param null $default
     * @return mixed|null
     */
    public static function getVal($arrOrObj, $key, $default = null)
    {
        return Arr::getVal($arrOrObj, $key, $default);
    }

    // --------------------------------------------------- Encoding ----------------------------------------------------

    public static function utf8($var, $from = 'windows-1251')
    {
        return Encoding::utf8($var, $from);
    }

    public static function cp1251($var, $from = 'utf-8')
    {
        return Encoding::cp1251($var, $from);
    }

    // -----------------------------------------------------------------------------------------------------------------

    public static function trim($val)
    {
        return is_string($val) ? trim(preg_replace('/\s+/', ' ', $val)) : $val;
    }


    public static function scriptRunningTime($timeStart, $isDraw = false, $precision = 3) {
        $timeJob = microtime(true) - $timeStart;
        $precision = pow(10, $precision);
        $remainderSecond = fmod($timeJob, 1);
        $remainderSecond = floor($remainderSecond * $precision ) / $precision;
        $result = [
            'minute' => floor($timeJob / 60),
            'second' => (floor($timeJob) % 60) + $remainderSecond,
            'allSeconds' => floor($timeJob * $precision ) / $precision
        ];
        if ($isDraw) {
            echo "<div style='background-color: #ddd; color: #000; padding: 4px; border-top: solid 1px #000; font-size: 14px; position: fixed; bottom: 0; left: 0; width: 100%;'>
                Время работы скрипта: <b>{$result['minute']}</b> мин. <b>{$result['second']}</b> сек.
              </div>";
        }
        return $result;
    }


    public static function isAjax()
    {
        return BaseFun::getSettings($_SERVER, 'http_x_requested_with', true, false) === 'xmlhttprequest';
    }

}
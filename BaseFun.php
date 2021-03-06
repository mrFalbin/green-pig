<?php
namespace GreenPig;

use GreenPig\Exception\GreenPigException;



class BaseFun
{
    // Проверяем, является ли объект $object ('\GreenPig\Database\Oracle') экземпляром класса $nameClass (Oracle),
    // причем $nameClass это само имя файла, без полной записи.
    // Функция большая, т.к. простой strrpos() в ряде случаев дает неверный результат.
    public static function isClass($object, $nameClass)
    {
        if (!is_object($object)) return false;
        $nameClass = trim($nameClass);
        $classObject = get_class($object);
        $pos = strrpos($classObject, '\\');
        if ($pos === false) return $nameClass === $classObject;
        $classObject = substr($classObject, ++$pos);
        return $classObject === $nameClass;
    }


    // Заменяются * на %, причем можно экранировать \*, тогда * не заменяются.
    public static function repStar($str)
    {
        $str = ''. $str;
        $str = str_replace('\*', '|-=star=-|', $str);
        $str = str_replace('*', '%', $str);
        $str = str_replace('|-=star=-|', '*', $str);
        return $str;
    }


    /**
    * Получение значения настройки из массива настроек, при котором не заботимся о корректности ключей (пробелы/регистр)
    * и опционально значения. В случае отсутсвия настройки в $settings выкидываем исключение если $isException == true.
    * $settings - массив настроек. ПР:
    * [
    *   'DB ' => [
    *      '   RDmS ' => ' oRAcle  ',
    *      ......
    *   ],
    *   ......
    * ]
    * $pathSettings - путь к нужной настройке, где ключи массива настроек записаны через '/' ПР: 'db/rdms'
    * $isFormattingVal - (true/false) если true, то форматируем результат (удаляем лишние пробелы, приводим к нижнему регистру)
    * $isException - (true/false) если true, то в случае отсутсвия искомой настройки выбрасывается исключение, если false - возвращается null
    * ПР: getSettings($settings, 'db/rdms') === 'oracle'
    */
    public static function getSettings($settings, $pathSettings,  $isFormattingVal = true, $isException = true)
    {
        if (!is_array($settings)) throw new GreenPigException("Settings must be described in an array.", $settings);
        $arrPathSettings = explode('/', $pathSettings);
        $val = self::arrKeyTrimLower($settings);
        foreach ($arrPathSettings as $keySettings) {
            $keySettings = self::trimLower($keySettings);
            if (!isset($val[$keySettings])) {
                if ($isException) throw new GreenPigException("There is no setting in the configuration $pathSettings", $settings);
                else return null;
            }
            $val = $val[$keySettings];
        }
        if ($isFormattingVal) {
            if (!is_string($val)) {
                if ($isException) throw new GreenPigException("Incorrect settings $pathSettings, the final value must be a string.", $settings);
                else return null;
            } else return self::trimLower($val);
        }
        return $val;
    }


    public static function arrKeyTrimLower($arr)
    {
        return self::arrKeyTrim($arr, 'lower');
    }


    public static function arrKeyTrimUpper($arr)
    {
        return self::arrKeyTrim($arr, 'upper');
    }


    // Функция удаляет все лишние пробелы в ключах массива $arr и приводит их к верхнему/нижнему регистру.
    private static function arrKeyTrim($arr, $registerType)
    {
        $newArr = [];
        foreach ($arr as $key => $val) {
            if ($registerType == 'upper') $key = self::trimUpper($key);
            else $key = self::trimLower($key);
            if (is_array($val)) $newArr[$key] = self::arrKeyTrim($val, $registerType);
            else $newArr[$key] = $val;
        }
        return $newArr;
    }


    public static function arrValTrimLower($arr)
    {
        return self::arrValTrim($arr, 'lower');
    }


    public static function arrValTrimUpper($arr)
    {
        return self::arrValTrim($arr, 'upper');
    }


    // Функция удаляет все лишние пробелы в значениях массива $arr и приводит их к верхнему/нижнему регистру.
    private static function arrValTrim($arr, $registerType)
    {
        $newArr = [];
        foreach ($arr as $key => $val) {
            if (is_array($val)) $newArr[$key] = self::arrValTrim($val, $registerType);
            else {
                if ($registerType == 'upper') $val = is_string($val) ? self::trimUpper($val) : $val;
                else $val = is_string($val) ? self::trimLower($val) : $val;
                $newArr[$key] = $val;
            }
        }
        return $newArr;
    }


    public static function trimLower($val)
    {
        return is_string($val) ? mb_strtolower(trim(preg_replace('/\s+/', ' ', $val))) : $val;
    }


    public static function trimUpper($val)
    {
        return is_string($val) ? mb_strtoupper(trim(preg_replace('/\s+/', ' ', $val))) : $val;
    }


    public static function isInt($val)
    {
        if (is_int($val)) return true;
        if (is_string($val)) {
            preg_match('/^\d+$/', trim($val), $int);
            return isset($int[0]);
        }
        return false;
    }

}
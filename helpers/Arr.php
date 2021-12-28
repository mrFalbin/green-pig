<?php


namespace GreenPig\Helpers;


class Arr
{


    /**
     * @param $arrOrObj array | Object
     * @param $key string | array
     * @param null $default
     * @return mixed|null
     */
    public static function getVal($arrOrObj, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($arrOrObj, $default);
        }
        if (is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $arrOrObj = static::getVal($arrOrObj, $keyPart);
            }
            $key = $lastKey;
        }
        if (is_array($arrOrObj) && (isset($arrOrObj[$key]) || array_key_exists($key, $arrOrObj))) {
            return $arrOrObj[$key];
        }
        if (($pos = strrpos($key, '/')) !== false) {
            $arrOrObj = static::getVal($arrOrObj, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }
        if (is_object($arrOrObj)) {
            if(property_exists($arrOrObj, $key)) return $arrOrObj->$key;
            else return $default;
        } elseif (is_array($arrOrObj)) {
            return (isset($arrOrObj[$key]) || array_key_exists($key, $arrOrObj)) ? $arrOrObj[$key] : $default;
        }
        return $default;
    }


}
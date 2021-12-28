<?php


namespace GreenPig\Helpers;


class Encoding
{
    public static function convertEncoding($var, $from, $to)
    {
        // Преобразование в другую кодировку нужно делать только для строки
        // или объекта с магическим методом __toString()
        if (is_string($var) || is_object($var) && method_exists($var, '__toString')) {
            return mb_convert_encoding($var, $to, $from);
        } elseif (is_array($var)) {
            $result = array();
            foreach ($var as $k => $v) {
                // В массиве в исходной кодировке могут быть
                // как ключи, так и значения.
                $result[self::convertEncoding($k, $from, $to)] = self::convertEncoding($v, $from, $to);
            }
            return $result;
        } elseif (is_object($var)) {
            // Объект передается по ссылке, поэтому его нужно клонировать,
            // иначе мы изменим исходный объект.
            $var = clone $var;
            // В объекте в исходной кодировке могут быть
            // как поля, так и их значения.
            // Нужно иметь ввиду, что преобразованию подлежат только открытые поля.
            // Так как конструкция $var->$k добавляет поле в конец,
            // то после преобразования все открытые поля будут идти в самом конце,
            // после всех закрытых членов.
            foreach (get_object_vars($var) as $k => $v) {
                // Если название поля имеет не_латинские символы,
                // то после преобразования конструкция $var->$k
                // не заменит существующее поле, а добавит новое,
                // поэтому его надо сначала удалить из объекта.
                unset ($var->$k);
                $k = self::convertEncoding($k, $from, $to);
                $var->$k = self::convertEncoding($v, $from, $to);
            }
            return $var;
        }
        // Все остальные типы: число, bool, null, ресурс... оставить как есть
        return $var;
    }


    public static function utf8($var, $from = 'windows-1251')
    {
        return self::convertEncoding($var, $from, 'utf-8');
    }


    public static function cp1251($var, $from = 'utf-8')
    {
        return self::convertEncoding($var, $from, 'windows-1251');
    }

}
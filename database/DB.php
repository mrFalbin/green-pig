<?php
namespace GreenPig\Database;



abstract class DB
{
    protected static $instances = [];
    protected $options;
    protected $debugInfo = [];
    protected $nameConnection;
    protected $db;

    private function __clone() { }
    final public function __wakeup() { }


    /**
     * DB constructor.
     * @param $nameConnection
     * @param array $options
     */
    protected function __construct($nameConnection, $options)
    {
        $this->nameConnection = $nameConnection;
        $this->options = $options;
    }


    /**
     * @param $nameConnection
     * @param $options
     * @return object DB
     */
    final public static function instance($nameConnection, $options)
    {
        if (!isset(static::$instances[$nameConnection]) or (static::$instances[$nameConnection] === null)) {
            static::$instances[$nameConnection] = new static($nameConnection, $options);
        }
        return static::$instances[$nameConnection];
    }


    final public static function getAllNamesInstances()
    {
        return array_keys(static::$instances);
    }


    // Функция возвращает сколько активных соединений с базами данных были закрыты.
    final public static function deleteAllInstances()
    {
        $numberCloseInstances = 0;
        foreach (static::$instances as $nameConnection => $instance) {
            if (static::deleteInstance($nameConnection)) $numberCloseInstances++;
        }
        static::$instances = [];
        return $numberCloseInstances;
    }


    final public static function deleteInstance($nameConnection)
    {
        $isDisconnect = false;
        if (isset(static::$instances[$nameConnection])) {
            $isDisconnect = static::$instances[$nameConnection]->disconnect();
            unset(static::$instances[$nameConnection]);
        }
        return $isDisconnect;
    }


    abstract public function disconnect();

    abstract public function all($sql, &$bind);

    abstract public function first($sql, &$bind);

    abstract public function execute($sql, &$bind);

    abstract public function insert($sql, &$bind);

    abstract public function beginTransaction();

    abstract public function commit();

    abstract public function rollBack();


    protected function getBindOptions($nameBind)
    {
        $nameBind = trim(str_replace(":", "", $nameBind));
        // --- в случае если нет [] с дополнительными настройками  ---
        $name = $nameBind;
        $typeStr = 'str';
        $maxlength = -1;
        $typeArr = null;
        // --- если есть [], работаем с данными внутри скобок ---
        preg_match_all("/\[(.+)\]/", $nameBind, $bindOptions); // Получаем все, что записано между []
        if (isset($bindOptions[0][0])) {
            $name = trim(str_replace($bindOptions[0][0], "", $name));
            $bindOptions = $bindOptions[1][0];
            $typeStr = $bindOptions; // присваеваем все что между []
            // --- если есть число, то это maxlength ---
            preg_match_all("/\d+/", $bindOptions, $bufMaxlength);
            if (isset($bufMaxlength[0][0])) {
                $typeStr = str_replace($bufMaxlength[0][0], "", $typeStr); // исключаем число (maxlength)
                $maxlength = (int)$bufMaxlength[0][0];
            }
            // --- Если есть (), то в них тип коллекции, при этом подразумевается что typeStr должен быть array ---
            preg_match_all("/\((.+)\)/", $bindOptions, $typeArrName);
            if (isset($typeArrName[0][0])) {
                $typeStr = str_replace($typeArrName[0][0], "", $typeStr); // исключаем ()
                switch (trim($typeArrName[1][0])) {
                    case 'SQLT_NUM': $typeArr = SQLT_NUM; break;
                    case 'SQLT_INT': $typeArr = SQLT_INT; break;
                    case 'SQLT_FLT': $typeArr = SQLT_FLT; break;
                    case 'SQLT_AFC': $typeArr = SQLT_AFC; break;
                    case 'SQLT_CHR': $typeArr = SQLT_CHR; break;
                    case 'SQLT_VCS': $typeArr = SQLT_VCS; break;
                    case 'SQLT_AVC': $typeArr = SQLT_AVC; break;
                    case 'SQLT_STR': $typeArr = SQLT_STR; break;
                    case 'SQLT_LVC': $typeArr = SQLT_LVC; break;
                    case 'SQLT_ODT': $typeArr = SQLT_ODT; break;
                    default: $typeArr = SQLT_CHR;
                }
            }
            // ---
            $typeStr = mb_strtolower(trim($typeStr)) ?: 'str';
        }
        return [
            'alias'   => $name,
            'type'      => $typeStr,
            'maxlength' => $maxlength,
            'typeArr' => $typeArr
        ];
    }


    protected function generateSqlWithVal($sql, $bind) {
        $sqlWithVal = null;
        if (!empty($sql) && !empty($bind)) {
            foreach ($bind as $alias => $val) {
                $aliasOptions = $this->getBindOptions($alias);
                if ($aliasOptions['type'] != 'clob') {
                    if (is_array($val)) $val = '[array]';
                    else $val = is_int($val) ? $val : " '$val' ";
                } else $val = '[clob]';
                $sql = str_replace(':' . $aliasOptions['alias'], $val, $sql);
            }
            $sqlWithVal = $sql;
        }
        return $sqlWithVal;
    }


    public function getDebugInfo()
    {
        return $this->debugInfo;
    }

}
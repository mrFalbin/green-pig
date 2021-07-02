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
        // --- Получаем все, что записано между квадратными скобками ---
        preg_match_all("/\[(\w|\s)+\]/", $nameBind, $bindOptions);
        $bindOptions = isset($bindOptions[0][0]) ? $bindOptions[0][0] : null;
        $name = trim(str_replace($bindOptions, "", $nameBind));
        $bindOptions = str_replace(['[', ']'], "", $bindOptions);
        // ---
        preg_match_all("/\d+/", $bindOptions, $maxlength);
        $maxlength = isset($maxlength[0][0]) ? (int)$maxlength[0][0] : -1;
        // ---
        preg_match_all("/[a-zA-Z]+/", $bindOptions, $typeStr);
        $typeStr = isset($typeStr[0][0]) ? mb_strtolower($typeStr[0][0]) : 'str';
        // ---
        return [
            'alias'   => $name,
            'type'      => $typeStr,
            'maxlength' => $maxlength
        ];
    }


    protected function generateSqlWithVal($sql, $bind) {
        $sqlWithVal = null;
        if (!empty($sql) && !empty($bind)) {
            foreach ($bind as $alias => $val) {
                $aliasOptions = $this->getBindOptions($alias);
                if ($aliasOptions['type'] != 'clob') {
                    $val = is_int($val) ? $val : " '$val' ";
                    $sql = str_replace(':' . $aliasOptions['alias'], $val, $sql);
                }
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
<?php
namespace GreenPig\Database;

use GreenPig\Exception\GreenPigDatabaseException;
use PDO;
use PDOException;



class MySql extends DB
{
    protected function __construct($nameConnection, $options)
    {
        $usr = empty($options['username']) ? null : $options['username'];
        $pas = empty($options['password']) ? null : $options['password'];
        $opt = empty($options['options']) ? null : $options['options'];
        $dsn = empty($options['dsn']) ? null : $options['dsn'];
        if ($dsn) {
            try {
                $this->db = new PDO($dsn, $usr, $pas, $opt);
            } catch (PDOException $e) {
                throw new GreenPigDatabaseException($e->getMessage(), $e, null, null, null, $options);
            }
        } else throw new GreenPigDatabaseException('The parameters required to connect to the MySql database were not passed.', $options);
        parent::__construct($nameConnection, $options);
    }


    public function disconnect()
    {
        $isDisconnect = false;
        if ($this->db) $isDisconnect = true;
        $this->db = null;
        return $isDisconnect;
    }


    public function all($sql, &$bind)
    {
        return $this->_execute($sql, $bind, 'all');
    }


    public function first($sql, &$bind)
    {
        return $this->_execute($sql, $bind, 'first');
    }


    public function execute($sql, &$bind)
    {
        return $this->_execute($sql, $bind, 'all');
    }


    public function insert($sql, &$bind)
    {
        return $this->_execute($sql, $bind, 'insert');
    }


    public function beginTransaction()
    {
        try {
            $this->db->beginTransaction();
        } catch (PDOException $e) {
            throw new GreenPigDatabaseException($e->getMessage(), $e, null, null, null, null);
        }
    }


    public function commit()
    {
        try {
            $this->db->commit();
        } catch (PDOException $e) {
            throw new GreenPigDatabaseException($e->getMessage(), $e, null, null, null, null);
        }
    }


    public function rollBack()
    {
        try {
            $this->db->rollBack();
        } catch (PDOException $e) {
            throw new GreenPigDatabaseException($e->getMessage(), $e, null, null, null, null);
        }
    }


    // ----------------------------------------------------------------------------------


    /* @throws GreenPigDatabaseException */
    private function _execute($sql, &$bind, $type)
    {
        $sqlWithBinds = $this->generateSqlWithVal($sql, $bind);
        $timeStartQuery = microtime(true);
        try { $sth = $this->db->prepare($sql); }
        catch (\PDOException $e) { throw new GreenPigDatabaseException($e->getMessage(), $e, $sql, $bind, $sqlWithBinds); }
        $this->arrBindByName($sth, $bind);
        try { $sth->execute(); }
        catch (\PDOException $e) { throw new GreenPigDatabaseException($e->getMessage(), $e, $sql, $bind, $sqlWithBinds); }
        $result = null;
        if ($type == 'first') $result = $sth->fetch(PDO::FETCH_ASSOC);
        if ($type == 'all') $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        if ($type == 'insert') $result = $this->db->lastInsertId();
        $sth->closeCursor();
        $result = $result ?: [];
        $this->debugInfo = [
            'sql' => $sql,
            'sqlWithBinds' => $sqlWithBinds,
            'binds' => $bind,
            'timeQuery' => (microtime(true) - $timeStartQuery)
        ];
        return $result;
    }




    // str  - PDO::PARAM_STR (по умолчанию)
    // int  - PDO::PARAM_INT
    // bool - PDO::PARAM_BOOL
    // null - PDO::PARAM_NULL
    protected function arrBindByName(&$sth, &$bind)
    {
        foreach ($bind as $key => &$value) {
            $bindOptions = $this->getBindOptions($key);
            $alias = $bindOptions['alias'];
            $maxlength = 0; // $bindOptions['maxlength'] == -1 ? 0 : $bindOptions['maxlength'];
            $type = PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT;
            switch ($bindOptions['type']) {
                case 'int': $type = PDO::PARAM_INT; break;
                case 'bool': $type = PDO::PARAM_BOOL; break;
                case 'null': $type = PDO::PARAM_NULL; break;
            }
            // if ($maxlength > 0) $type |= PDO::PARAM_INPUT_OUTPUT; (!!!!) Не имеет смысла, т.к. inout параметры не работают
            $sth->bindParam(":$alias", $value, $type, $maxlength);
        }
    }

}
<?php
namespace GreenPig\Database;

use GreenPig\Exception\GreenPigDatabaseException;
use GreenPig\BaseFun;



class Oracle extends DB
{
    private $clobArr       = [];
    private $optionsCommit = OCI_COMMIT_ON_SUCCESS;
    private $sqlBuf;
    private $bindBuf;


    /**
     * Oracle constructor.
     * @param array $options
     * @throws GreenPigDatabaseException
     */
    protected function __construct($nameConnection, $options)
    {
        $usr = empty($options['username']) ? null : $options['username'];
        $pas = empty($options['password']) ? null : $options['password'];
        $cs = empty($options['connection_string']) ? null : $options['connection_string'];
        $chr = empty($options['character_set']) ? null : $options['character_set'];
        $mod = empty($options['session_mode']) ? null : $options['session_mode'];
        if ($usr && $pas) {
            $this->db = oci_connect($usr, $pas, $cs, $chr, $mod);
            if (!$this->db) {
                $error = oci_error();
                throw new GreenPigDatabaseException($error['message'], $error, null, null, null, $options);
            }
        } else {
            throw new GreenPigDatabaseException('Не переданы нужные для подключения к базе Oracle параметры.', $options);
        }
        parent::__construct($nameConnection, $options);
    }


    public function disconnect()
    {
        $isDisconnect = false;
        if ($this->db) {
            oci_close($this->db);
            $isDisconnect = true;
        }
        $this->db = null;
        return $isDisconnect;
    }


    public function all($sql, &$bind)
    {
        return $this->_execute($sql, $bind);
    }


    public function first($sql, &$bind)
    {
        return $this->_execute($sql, $bind, 'one');
    }


    public function execute($sql, &$bind)
    {
        return $this->_execute($sql, $bind);
    }


    public function insert($sql, &$bind, $table = '', $pk = null)
    {
        $debugInfo = [];
        $rowid = null;
        $bind['greenpig_alias_insert_rowid [32]'] = &$rowid;
        $sql = "$sql returning rowid into :greenpig_alias_insert_rowid";
        $this->_execute($sql, $bind);
        $debugInfo[] = $this->getDebugInfo();
        $result = null;
        if ($rowid && $table) {
            $selectBind = ['greenpig_alias_rowid' => $rowid];
            $insertRow = $this->_execute("select * from $table where rowid = :greenpig_alias_rowid", $selectBind, 'one');
            $debugInfo[] = $this->getDebugInfo();
            $insertRow = BaseFun::arrKeyTrimLower($insertRow);
            $pk = BaseFun::trimLower($pk);
            $result = $pk ? $insertRow[$pk] : $insertRow;
        }
        $this->debugInfo = $debugInfo;
        return $result;
    }


    public function beginTransaction()
    {
        $this->optionsCommit = OCI_DEFAULT;
    }


    /* @throws GreenPigDatabaseException */
    public function commit()
    {
        if (!oci_commit($this->db)) {
          $error = oci_error($this->db);
          throw new GreenPigDatabaseException($error['message'], $error);
        }
        $this->optionsCommit = OCI_COMMIT_ON_SUCCESS;
        $this->clearClobArr();
    }


    public function rollBack()
    {
        oci_rollback($this->db);
        $this->optionsCommit = OCI_COMMIT_ON_SUCCESS;
        $this->clearClobArr();
    }


    // ----------------------------------------------------------------------------------


    /* @throws GreenPigDatabaseException */
    private function _execute($sql, &$bind, $typeSelect = 'all')
    {
        // Необходимо вызвать generateSqlWithVal() до oci_bind_by_name(), т.к. последняя преобразует числа в $bind к строке
        $sqlWithBinds = $this->generateSqlWithVal($sql, $bind);
        $timeStartQuery = microtime(true);
        $stmt = oci_parse($this->db, $sql);
        $this->arrBindByName($stmt, $bind);
        if (!oci_execute($stmt, $this->optionsCommit)) {
          $error = oci_error($stmt);
          throw new GreenPigDatabaseException($error['message'], $error, $sql, $bind, $sqlWithBinds);
        }
        $stmtType = trim(mb_strtolower(oci_statement_type($stmt)));
        $this->setBindFromClobArr($bind);
        $result = [];
        if ($stmtType == 'select') {
            $index = 0;
            $indexStop = $typeSelect == 'one' ? 1 : 1000000;
            // OCI_ASSOC - Возвращает ассоциативный массив (по умолчанию массив как с ассоциативными так и с числовыми индексами).
            // OCI_RETURN_NULLS - создаёт элементы для полей равных null. Значение элемента будет равно PHP null.
            // OCI_RETURN_LOBS  - Возвращает содержимое полей типа LOB, вместо LOB указателя.
            // --- перехватываем warning ---
            $this->sqlBuf = $sql;
            $this->bindBuf = $bind;
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                throw new GreenPigDatabaseException($errstr, null, $this->sqlBuf, $this->bindBuf, null, null);
            });
            while (($tmp = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS)) && ($index++ < $indexStop)) {
                $result[] = $tmp;
            }
            restore_error_handler();
            // ---
            if ($typeSelect == 'one') $result = isset($result[0]) ? $result[0] : [];
        }
        if ($this->optionsCommit === OCI_COMMIT_ON_SUCCESS) {
          $this->clearClobArr();
          oci_free_statement($stmt);
        }
        $this->debugInfo = [
            'sql' => $sql,
            'sqlWithBinds' => $sqlWithBinds,
            'binds' => $bind,
            'timeQuery' => (microtime(true) - $timeStartQuery)
        ];
        return $result;
    }


    protected function arrBindByName(&$stmt, &$bind)
    {
        foreach ($bind as $key => &$val) {
            $bindOptions = $this->getBindOptions($key);
            $alias = $bindOptions['alias'];
            $type = SQLT_CHR;
            if ($bindOptions['type'] == 'int') $type = OCI_B_INT;
            if ($bindOptions['type'] == 'clob') {
                $this->clobArr[$alias] = oci_new_descriptor($this->db, OCI_D_LOB);
                oci_bind_by_name($stmt, ":$alias", $this->clobArr[$alias], -1, OCI_B_CLOB);
                $this->clobArr[$alias]->writeTemporary($val);
            } else {
                oci_bind_by_name($stmt, ":$alias", $val, $bindOptions['maxlength'], $type);
            }
        }
    }


    protected function clearClobArr()
    {
        foreach ($this->clobArr as $key => &$val) $val->free();
        $this->clobArr = [];
    }


    protected function setBindFromClobArr(&$bind)
    {
        foreach ($bind as $key => &$val) {
          $bindOptions = $this->getBindOptions($key);
          if (isset($this->clobArr[$bindOptions['alias']])) {
            $val = $this->clobArr[$bindOptions['alias']]->load();
          }
        }
    }

}
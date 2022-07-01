<?php
namespace GreenPig;


use GreenPig\Exception\GreenPigLogException;


class Log
{
    const LOG_INFO = 'info';
    const LOG_DEBUG = 'debug';
    const LOG_WARNING = 'warning';
    const LOG_ERROR = 'error';

    private $query;
    private $isWrite;
    private $tLog;
    private $tValLog;
    private $properties = [];
    private $type;
    private $message;
    private $id;



    /**
     * Log constructor.
     * @param $query Query
     * @param $settings
     * @throws Exception\GreenPigException
     * @throws GreenPigLogException
     */
    public function __construct(&$query, $settings)
    {
        $this->query = &$query;
        $this->settings = $settings;
        $this->isWrite = $this->getIsWrite($settings);
        $this->tLog = [
            'nameTable' => BaseFun::getSettings($settings, 'log/tablelog/nametable', true, false) ?: 'log',
            'id' => BaseFun::getSettings($settings, 'log/tablelog/id', true, false) ?: 'id',
            'type' => BaseFun::getSettings($settings, 'log/tablelog/type', true, false) ?: 'type',
            'title' => BaseFun::getSettings($settings, 'log/tablelog/title', true, false) ?: 'title',
            'message' => BaseFun::getSettings($settings, 'log/tablelog/message', true, false) ?: 'message',
            'ip' => BaseFun::getSettings($settings, 'log/tablelog/ip', true, false) ?: 'ip',
            'file_name' => BaseFun::getSettings($settings, 'log/tablelog/file_name', true, false) ?: 'file_name',
            'number_line' => BaseFun::getSettings($settings, 'log/tablelog/number_line', true, false) ?: 'number_line',
            'date_create' => BaseFun::getSettings($settings, 'log/tablelog/date_create', true, false) ?: 'date_create'
        ];
        $this->tValLog = [
            'nameTable' => BaseFun::getSettings($settings, 'log/tablevallog/nametable', true, false) ?: 'val_log',
            'id' => BaseFun::getSettings($settings, 'log/tablevallog/id', true, false) ?: 'id',
            'log_id' => BaseFun::getSettings($settings, 'log/tablevallog/log_id', true, false) ?: 'log_id',
            'parent_id' => BaseFun::getSettings($settings, 'log/tablevallog/parent_id', true, false) ?: 'parent_id',
            'property' => BaseFun::getSettings($settings, 'log/tablevallog/property', true, false) ?: 'property',
            'val' => BaseFun::getSettings($settings, 'log/tablevallog/val', true, false) ?: 'val'
        ];
    }


    private function getIsWrite($settings) {
        $formattedSettings = BaseFun::arrKeyTrimLower($settings);
        $isWrite = isset($formattedSettings['log']['iswrite']) ? $formattedSettings['log']['iswrite'] : true;
        if (is_array($isWrite)) {
            // проверяем массив типов на валидность
            $bufIsWrite = [];
            foreach ($isWrite as $sType) {
                $bufIsWrite[] = $this->validType($sType);
            }
            $isWrite = $bufIsWrite;
        } elseif (!is_bool($isWrite)) {
            throw new GreenPigLogException('The isWrite setting must be either an array or bool.', $isWrite);
        }
        return $isWrite;
    }


    private function validType($type)
    {
        $type = BaseFun::trimLower($type);
        if (in_array($type, [self::LOG_INFO, self::LOG_DEBUG, self::LOG_ERROR, self::LOG_WARNING])) return $type;
        throw new GreenPigLogException("Invalid type log! Valid values: error, warning, debug, info.", $type);
    }


    private function isWriteLog()
    {
        if (is_array($this->isWrite)) return array_search($this->type, $this->isWrite) !== false;
        return $this->isWrite;
    }


    public function writeLog($type, $title, $message)
    {
        if ($this->isWriteLog()) {
            $fileName = null;
            $numberLine = null;
            $trace = null;
            $traceBacktrace = debug_backtrace();
            if (isset($traceBacktrace[1])) $trace = $traceBacktrace[1];
            if ($trace) {
                // --- для windows ---
                $dir = str_replace('\\', '/', $trace['file']);
                $rootDir = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                // ---
                $fileName = str_replace($rootDir, '', $dir);
                $numberLine = $trace['line'];
            }
            $this->type = $this->validType($type);
            $this->message = $message;
            $this->id = $this->query->insert($this->tLog['nameTable'], [
                $this->tLog['type'] => $this->type,
                $this->tLog['title'] => $title,
                $this->tLog['message'] => $message,
                $this->tLog['ip'] => $_SERVER['REMOTE_ADDR'],
                $this->tLog['file_name'] => $fileName,
                $this->tLog['number_line'] => $numberLine
            ], $this->tLog['id']);
        }
        return $this->id;
    }


    public function save($nameInstaceLog)
    {
        // $this->query::$logs[$nameInstaceLog] = $this;     Это работает только в php 7
        $query = &$this->query;
        $query::$logs[$nameInstaceLog] = $this;
        return $this;
    }


    public function setMessage($txt)
    {
        if ($this->isWriteLog()) {
            $this->message = $txt;
            $this->query->update(
                $this->tLog['nameTable'],
                [$this->tLog['message'] => $txt],
                [$this->tLog['id'], '=', $this->id]
            );
        }
        return $this;
    }


    public function getMessage()
    {
        return $this->message;
    }


    /**
     * @param string $type Допустимые значения: info, debug, warning, error
     * @return $this
     * @throws GreenPigLogException
     */
    public function setType($type)
    {
        if ($this->isWriteLog()) {
            $this->type = $this->validType($type);
            $this->query->update(
                $this->tLog['nameTable'],
                [$this->tLog['type'] => $this->type],
                [$this->tLog['id'], '=', $this->id]
            );
        }
        return $this;
    }


    /**
     * @return string Возможные значения: info, debug, warning, error
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * @param $key - чувствителен к ригитру пользователь сам должен следить за правильностью написания
     * @param $val
     * @return $this
     */
    public function prop($key, $val)
    {
        if ($this->isWriteLog()) {
            $data = $this->getPropInfoAndValid($key);
            $newId = $this->query->insert($this->tValLog['nameTable'], [
                $this->tValLog['log_id'] => $this->id,
                $this->tValLog['parent_id'] => $data['parentId'],
                $this->tValLog['property'] => $data['nameProp'],
                $this->tValLog['val'] => $val,
            ], $this->tValLog['id']);
            // добовляем элемент в массив $this->properties
            $prop = &$data['currentProp'];
            if (isset($prop[$data['nameProp']])) $prop[$data['nameProp']]['id'][] = $newId;
            else $prop[$data['nameProp']] = ['id' => [$newId], 'child' => []];
        }
        return $this;
    }


    /**
     *  Возвращает массив:
     * 'prop' - нужный уровень вложенности, попутно проверяем на ошибки все уровни (по ссылке)
     * 'parentId' - id родительского свойсва либо false
     * 'nameProp' - имя добовляемого свойсва
     *
     * Проверяем по следующим правилам:
     * 1) Чтобы для каждого уровня от 1 до n-1 выполнялись условия:
     *      а) Существовали все элементы массива $properties
     *      б) В массиве 'id' был только 1 элемент
     * 2) Если на n'ом уровне элемент существует, то у него массив 'child' должен быть пустым
     * 3) Если на n'ом уровне элемент НЕ существует, то все хорошо
     *
     * $this->properties:
     * [
     *   'sizeBackup' => ['id' => [1]],
     *   'server' => [
     *       'id' => [2],
     *       'child' => [
     *           'freePlace' => ['id' => [3]],
     *           'percentFreePlace' => ['id' => [4]],
     *           'email' => ['id' => [5]]
     *       ]
     *   ],
     *   'email' => ['id' => [6,7,8]],
     * ]
     *
     * @param $path string - название свойства. Если необходимо указать что свойство path дочернее к свойству server, то пишем: 'server/path'
     * @return array
     */
    private function getPropInfoAndValid($path)
    {
        if (!is_string($path)) throw new GreenPigLogException('Property error.', $path);
        $arrPath = preg_split("/\//", trim($path));
        $level = count($arrPath);
        if ($level === 0) throw new GreenPigLogException('Property error.', $path);
        $parentId = null;
        $prop = &$this->properties;
        if ($level > 1) {
            for ($i=0; ($level-1) > $i; $i++) {
                if (isset($prop[$arrPath[$i]])) {
                    $prop = &$prop[$arrPath[$i]];
                    if (count($prop['id']) !== 1) {
                        throw new GreenPigLogException('You cannot add non-unique properties to an element with child properties.', $path);
                    }
                    $parentId = $prop['id'][0];
                    $prop = &$prop['child'];
                } else throw new GreenPigLogException('Parental property does not exist.', $path);
            }
        }
        $nameProp = $arrPath[$level-1];
        if (isset($prop[$nameProp]) && (count($prop[$nameProp]['child']) > 0)) {
            throw new GreenPigLogException('You cannot add non-unique properties to an element with child properties.', $path);
        }
        return [
            'currentProp' => &$prop,
            'parentId' => $parentId,
            'nameProp' => $nameProp
        ];
    }


    /**
     * @param $id int | array
     * @return array
     * @throws Exception\GreenPigException
     * @throws GreenPigLogException
     */
    public function getLog($id)
    {
        $tL = $this->tLog;
        $tVL = $this->tValLog;
        // --- преобразовываем дату к формату, который указан в настройках ---
        $date = " DATE_FORMAT(l.{$tL['date_create']}, ";
        if (BaseFun::getSettings($this->settings, 'rdbms') === 'oracle') $date = " TO_CHAR(l.{$tL['date_create']}, ";
        $date .= " '" . BaseFun::getSettings($this->settings, 'date/sql', false) . "') as {$tL['date_create']} ";
        // ---
        $this->query->sql("SELECT * FROM (
                            SELECT  l.{$tL['id']},
                                    l.{$tL['type']},
                                    l.{$tL['title']},
                                    l.{$tL['message']},
                                    l.{$tL['ip']},
                                    l.{$tL['file_name']},
                                    l.{$tL['number_line']},
                                    $date,
                                    vl.{$tVL['id']} val_log_id,
                                    vl.{$tVL['log_id']},
                                    vl.{$tVL['parent_id']},
                                    vl.{$tVL['property']},
                                    vl.{$tVL['val']}
                            FROM {$tL['nameTable']} l 
                            LEFT JOIN {$tVL['nameTable']} vl ON vl.{$tVL['log_id']} = l.{$tL['id']}
                         ) t /*_where_*/ ")
                    ->where('/*_where_*/', $id)
                    ->all();
        // необходимо возвращать с ключами в нижнем регистре
        return $this->query->getData('lower');
    }


    public function createTablesForLog($table)
    {
        $rdbms = BaseFun::getSettings($this->settings, 'rdbms');
        $table = BaseFun::trimLower($table);
        $tL = $this->tLog;
        $tVL = $this->tValLog;
        // -------------------------------------------------------------------------------------------------------------
        $mySqlLog = "CREATE TABLE {$tL['nameTable']} (
                      {$tL['id']} INT NOT NULL AUTO_INCREMENT,
                      {$tL['type']} VARCHAR(10) NOT NULL,
                      {$tL['title']} VARCHAR(255) NOT NULL,
                      {$tL['message']} VARCHAR(4000) NULL,
                      {$tL['ip']} VARCHAR(64) NULL,
                      {$tL['file_name']} VARCHAR(255) NULL,
                      {$tL['number_line']} INT NULL,
                      {$tL['date_create']} DATETIME NOT NULL DEFAULT now(),
                      PRIMARY KEY ({$tL['id']}))
                    ENGINE = InnoDB";
        $mySqlValLog = "CREATE TABLE {$tVL['nameTable']} (
                          {$tVL['id']} INT NOT NULL AUTO_INCREMENT,
                          {$tVL['log_id']} INT NOT NULL,
                          {$tVL['parent_id']} INT NULL,
                          {$tVL['property']} VARCHAR(255) NOT NULL,
                          {$tVL['val']} TEXT(65000) NOT NULL,
                          PRIMARY KEY ({$tVL['id']}))
                        ENGINE = InnoDB";
        // -------------------------------------------------------------------------------------------------------------
        $OracleLog = "create table {$tL['nameTable']} (
                          {$tL['id']}          number generated always as identity,
                          {$tL['type']}        varchar2(10) not null,
                          {$tL['title']}       varchar2(255) not null,
                          {$tL['message']}     varchar2(4000),
                          {$tL['ip']}          varchar2(64),
                          {$tL['file_name']}   varchar2(255),
                          {$tL['number_line']} number,
                          {$tL['date_create']} date default sysdate not null
                        )";
        $lPK = "gp_{$tL['nameTable']}_{$tL['id']}_pk";
        $OracleLogPK = "alter table {$tL['nameTable']} add constraint $lPK primary key ({$tL['id']})";
        $OracleValLog = "create table {$tVL['nameTable']} (
                              {$tVL['id']}        number generated always as identity,
                              {$tVL['log_id']}    number not null,
                              {$tVL['parent_id']} number,
                              {$tVL['property']}  varchar2(255) not null,
                              {$tVL['val']}       clob not null
                            )";
        $lvPK = "gp_{$tVL['nameTable']}_{$tVL['id']}_pk";
        $OracleValLogPK = "alter table {$tVL['nameTable']} add constraint $lvPK primary key ({$tVL['id']})";
        // -------------------------------------------------------------------------------------------------------------
        if ($rdbms === 'oracle') {
            if ($table === 'all' || $table === 'log') {
                $this->query->sql($OracleLog)->execute();
                $this->query->sql($OracleLogPK)->execute();
            }
            if ($table === 'all' || $table === 'val_log') {
                $this->query->sql($OracleValLog)->execute();
                $this->query->sql($OracleValLogPK)->execute();
            }
        } else {
            if ($table === 'all' || $table === 'log') $this->query->sql($mySqlLog)->execute();
            if ($table === 'all' || $table === 'val_log') $this->query->sql($mySqlValLog)->execute();
        }
    }


    public function deleteLog($numberDay, $type, $title)
    {
        if (!is_int($numberDay)) throw new GreenPigLogException('$numberDay it should be integer.', $numberDay);
        $rdbms = BaseFun::getSettings($this->settings, 'rdbms');
        $tL = $this->tLog;
        $tVL = $this->tValLog;
        $type = $this->validType($type);
        $binds['numberDay'] = abs($numberDay);
        $idsForDel = [];
        $whereSql = $rdbms === 'oracle' ? 'SYSDATE - :numberDay' : 'NOW() - INTERVAL :numberDay DAY';
        $result = $this->query
                        ->sql("SELECT {$tL['id']} id FROM {$tL['nameTable']} /*where*/")
                        ->binds($binds)
                        ->where('/*where*/', [
                            ["LOWER({$tL['type']})", '=', $type],
                            [$tL['title'], 'flex' => $title],
                            [$tL['date_create'], '<', 'sql' => $whereSql]
                        ])
                        ->all();
        if (count($result) > 0) {
            $nameId = false;
            if (isset($result[0]['ID'])) $nameId = 'ID';
            if (isset($result[0]['id'])) $nameId = 'id';
            if ($nameId) $idsForDel = $this->query->column($nameId);
        }
        $countDeleteL = 0;
        $countDeleteVL = 0;
        if (count($idsForDel)) {
            $countDeleteVL = $this->query->delete($tVL['nameTable'], [$tVL['log_id'], 'in', $idsForDel]);
            $countDeleteL = $this->query->delete($tL['nameTable'], [$tL['id'], 'in', $idsForDel]);
        }
        return ['log'=> $countDeleteL, 'val_log' => $countDeleteVL];
    }


    /**
     * Из плоского ответа из базы $arr формируем такой ответ для конаничного вида:
     *  [
     *      [
     *          'id' => 8,
     *          'type' => 'info',
     *          'mode' => 'project №001',
     *          'message' => 'create backup',
     *          'ip' => '172.19.0.1',
     *          file_name => '',
     *          number_line => '',
     *          date_create => '2021-09-28 13:36:08',
     *          properties => [
     *              'sizeBackup' => [
     *                  'val' => [10249],
     *                  'child' => []
     *              ],
     *              'server' => [
     *                  'val' => ['192.168.0.99'],
     *                  'child' => [
     *                      'freePlace' => [
     *                          'val' => [23944556],
     *                          'child' => []
     *                      ],
     *                      'percentFreePlace' => [
     *                          'val' => ['20%'],
     *                          'child' => []
     *                      ]
     *                  ]
     *              ],
     *              'email' => [
     *                  'val' => ['admin@mail.ru', 'support@mail.ru', 'vasya@mail.ru'],
     *                  'child' => []
     *              ]
     *          ]
     *      ],
     *      .....
     *  ]
     *
     *  Для канонического вида значение из property это ключ массива, а его значение обязательно имеет следующую структуру:
     *  [
     *      property => [
     *          'val' => [значение],
     *          'child' => []
     *      ]
     *  ]
     *  Т.е. массив состоит из двух элементов:
     *      val - значение которого также является массивом, даже если там одно значение.
     *      child - массив с дочерними элементами, если они отсутствуют то массив пустой.
     *  Если в val находится json, то он никак не преобразовывается, остается просто строкой.
     *
     *
     *  Так же есть обычный вид, который максимально человекочитаем, а также если в val находится json,
     *  то он преобразуется в массив:
     *      .....
     *      properties => [
     *          'sizeBackup' => 10249,
     *          'server' => [
     *              'val' => '192.168.0.99',
     *              'child' => [
     *                  'freePlace' => 23944556,
     *                  'percentFreePlace' => '20%'
     *              ]
     *          ],
     *          'email' => ['admin@mail.ru', 'support@mail.ru', 'vasya@mail.ru']
     *      ]
     *      .....
     *
     *
     * @param $arr array (ключи всегда приходят в нижнем регистре)
     * @return array
     */
    public function getDataLog($arr, $isCanon = true) {
        $id = $this->tLog['id'];
        $parent_id = $this->tValLog['parent_id'];
        // ---------- группируем логи ----------
        $groupLog = [];
        foreach ($arr as $a) {
            if (isset($groupLog[$a[$id]])) {
                $groupLog[$a[$id]]['properties'][] = $this->addPropertyBufGroupLog($a);
            } else {
                $groupLog[$a[$id]] = $this->addBasicBufGroupLog($a);
                if (isset($a['val_log_id'])) {
                    $groupLog[$a[$id]]['properties'][] = $this->addPropertyBufGroupLog($a);
                }
            }
        }
        // ---------- строим древовидную структуру для свойств ----------
        foreach ($groupLog as &$gl) {
            // формируем массив, ключами которого являются id свойств у которого есть потомки, а значения - это массив этих потомков
            $parents = [];
            foreach ($gl['properties'] as $p) {
                $parents[$p[$parent_id] ?: 0][] = $p;
            }
            // рекурсивно строим иерархию
            if ($isCanon) $gl['properties'] = isset($parents[0]) ? $this->buildCanonLevelsLog($parents[0], $parents) : [];
            else $gl['properties'] = isset($parents[0]) ? $this->buildLevelsLog($parents[0], $parents) : [];
        }
        // ---------- возвращаем массив с индексами от 0 до n ----------
        return array_values($groupLog);
    }

    private function addBasicBufGroupLog($arr)
    {
        // ---------- log ----------
        $id = $this->tLog['id'];
        $type = $this->tLog['type'];
        $title = $this->tLog['title'];
        $message = $this->tLog['message'];
        $ip = $this->tLog['ip'];
        $file_name = $this->tLog['file_name'];
        $number_line = $this->tLog['number_line'];
        $date_create = $this->tLog['date_create'];
        // ----------
        return [
            $id => $arr[$id],
            $type => $arr[$type],
            $title => $arr[$title],
            $message => $arr[$message],
            $ip => $arr[$ip],
            $file_name => $arr[$file_name],
            $number_line => $arr[$number_line],
            $date_create => $arr[$date_create],
            'properties' => []
        ];
    }

    private function addPropertyBufGroupLog($arr)
    {
        // ---------- val_log ----------
        $log_id = $this->tValLog['log_id'];
        $parent_id = $this->tValLog['parent_id'];
        $property = $this->tValLog['property'];
        $val = $this->tValLog['val'];
        // ----------
        return [
            'val_log_id' => $arr['val_log_id'],
            $log_id => $arr[$log_id],
            $parent_id => $arr[$parent_id],
            $property => $arr[$property],
            $val => $arr[$val]
        ];
    }

    private function buildCanonLevelsLog($arr, $parents)
    {
        // ---------- val_log ----------
        $property = $this->tValLog['property'];
        $val = $this->tValLog['val'];
        // ----------
        $result = [];
        foreach ($arr as $a) {
            if (isset($result[$a[$property]])) $result[$a[$property]]['val'][] = $a[$val];
            else $result[$a[$property]] = ['val' => [$a[$val]], 'child' => []];
            if (isset($parents[$a['val_log_id']])) { // значит у этого элемента есть дочки
                $result[$a[$property]]['child'] = $this->buildCanonLevelsLog($parents[$a['val_log_id']], $parents);
            }
        }
        return $result;
    }

    private function buildLevelsLog($arr, $parents)
    {
        // ---------- val_log ----------
        $property = $this->tValLog['property'];
        $val = $this->tValLog['val'];
        // ----------
        $result = [];
        foreach ($arr as $a) {
            $v = json_decode($a[$val]) ?: $a[$val];
            if (isset($result[$a[$property]])) {
                if (is_array($result[$a[$property]])) $result[$a[$property]][] = $v;
                else $result[$a[$property]] = [$result[$a[$property]], $v];
            } else {
                if (is_array($v)) $result[$a[$property]] = [$v];
                else $result[$a[$property]] = $v;
            }
            // --- значит у этого элемента есть дочки ---
            if (isset($parents[$a['val_log_id']])) {
                $result[$a[$property]] = [
                    'val' => $result[$a[$property]],
                    'child' => $this->buildLevelsLog($parents[$a['val_log_id']], $parents)
                ];
            }
        }
        return $result;
    }

}
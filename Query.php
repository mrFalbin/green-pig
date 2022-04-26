<?php
namespace GreenPig;

use GreenPig\Exception\GreenPigQueryException;
use GreenPig\Exception\GreenPigDatabaseException;


class Query
{
    private $db;
    public  $nameConnection;
    private $settings;
    private $baseSQL = '';
    private $partsSql = [];
    private $binds = [];
    private $numberAlias = 0;
    private $sort = '';
    private $pagination = false;
    private $paginationLastRequest = false;
    private $bindsLastRequest = [];
    private $rawData = '';
    private $debugInfo = [];
    private $bufDebugInfo = []; // промежуточный массив, накапливающий инфу о вспомогательных запросах
    private $timeStartQuerys;
    private $gpAliasTable = 'greenpig_alias_table_';
    private $gpAliasRownum = 'greenpig_alias_rownum_';
    // --- log ---
    public static $logs = [];



    public function __construct($db, &$settings, $nameConnection)
    {
        $this->db = $db;
        $this->settings = &$settings;
        $this->nameConnection = $nameConnection;
    }



    // -------------------------------------------- зеленая группа -----------------------------------------------------



    public function sql($baseSQL)
    {
        $this->baseSQL = $baseSQL;
        return $this;
    }


    public function sqlPart($alias, $sql)
    {
        $this->partsSql[$alias] = $sql;
        return $this;
    }


    public function binds($binds)
    {
        $this->binds = $binds;
        return $this;
    }


    public function addBinds($binds)
    {
        $this->binds = array_merge($this->binds, $binds);
        return $this;
    }


    public function bind($alias, $value)
    {
        $this->binds[$alias] = $value;
        return $this;
    }


    public function linkBind($alias, &$value)
    {
        $this->binds[$alias] = &$value;
        return $this;
    }


    public function getBind($alias = false)
    {
        if ($alias) {
            $alias = BaseFun::trimLower($alias);
            foreach ($this->bindsLastRequest as $k => $v) {
                $name = BaseFun::trimLower($this->getNameBind($k));
                if (($alias === BaseFun::trimLower($k)) || ($alias === $name)) return $v;
            }
            return null;
        }
        return $this->bindsLastRequest;
    }


    public function where($alias, $where, $beginKeyword = 'where')
    {
        $where = $this->preprocessorWhere($where);
        $wh = new Where($this->settings);
        $this->sqlPart($alias, $wh->where($where, $beginKeyword, $this->getNewNumberAlias()));
        $this->addBinds($wh->getBinds());
        $this->numberAlias = $wh->numberAlias;
        return $this;
    }


    private function preprocessorWhere($where)
    {
        if (is_array($where) && count($where)) {
            $isArrId = true;
            foreach ($where as $k => $v) {
                if (!is_int($k) || !BaseFun::isInt($v)) $isArrId = false;
            }
            if ($isArrId) {
                $where = ['id', 'in', $where];
            }
        }
        elseif (BaseFun::isInt($where)) $where = ['id', '=', $where];
        return $where;
    }


    // ---------- whereJoin ----------
    public function whereJoin($aliasJoin, $nameTable, $aliasTable, $conditionJoin, $aliasWhere, $where, $beginKeywordWhere = 'where')
    {
        $aliasTable = BaseFun::trimLower($aliasTable);
        $conditionJoin = BaseFun::trimLower($conditionJoin);
        $obWh = new Where($this->settings);
        if ($obWh->validLogicalOperation($where)['scenario'] !== -1) $where = [$where];
        $sqlJoin = $this->genJoinEditWhere($where, $aliasTable, $nameTable, $conditionJoin);
        if (trim($sqlJoin)) $this->sqlPart($aliasJoin, $sqlJoin);
        $this->sqlPart($aliasWhere, $obWh->where($where, $beginKeywordWhere, $this->getNewNumberAlias()));
        $this->addBinds($obWh->getBinds());
        $this->numberAlias = $obWh->numberAlias;
        return $this;
    }


    private function genJoinEditWhere(&$arrWhere, $userAliasTable, $nameTable, $conditionJoin) {
        $where = new Where($this->settings);
        $aliasTable = 'greenpig_alias_join_table_' . $this->getNewNumberAlias();
        $sqlJoin = '';
        $isRepAliasInWhere = false;
        foreach ($arrWhere as &$wh) {
            // 0 индекс логического выражения может быть 'or' или 'and', поэтому работаем только с массивами.
            // Валидацию не проводим, она будет позже на этапе построения sql
            if (is_array($wh)) {
                if ($where->validLogicalExpression($wh)) $sqlJoin .= $this->genJoinEditWhere($wh, $userAliasTable, $nameTable, $conditionJoin);
                elseif($where->validLogicalOperation($wh) !== -1) {
                    $wh[0] = BaseFun::trimLower($wh[0]);
                    $wh[0] = str_replace("$userAliasTable.", "$aliasTable.", $wh[0], $countRep);
                    if ($countRep > 0) $isRepAliasInWhere = true;
                }
            }
        }
        if ($isRepAliasInWhere) {
            $conditionJoinRepAlias = str_replace("$userAliasTable.", "$aliasTable.", $conditionJoin);
            $sqlJoin .= " INNER JOIN $nameTable $aliasTable ON $conditionJoinRepAlias ";
        }
        return $sqlJoin;
    }
    // ---------- end whereJoin ----------


    // ---------- whereDetailedSearch (для плагина https://www.npmjs.com/package/detailed-search) ----------
    public function whereDetailedSearch($aliasWhere, $searchQuery, $beginKeywordWhere = 'where', $aliasJoin = null, $nameTable = null, $aliasTable = null, $conditionJoin = null)
    {
        // --- валидация ---
        if (!(($aliasJoin === null && $nameTable === null  && $aliasTable === null && $conditionJoin === null) ||
            ($aliasJoin !== null && $nameTable !== null  && $aliasTable !== null && $conditionJoin !== null))) {
            throw new GreenPigQueryException('All parameters JOIN are empty, or all are filled.', [
                'aliasJoin' => $aliasJoin, 'nameTable' => $nameTable, 'aliasTable' => $aliasTable, 'conditionJoin' => $conditionJoin
            ]);
        }
        // --- where ---
        $arrWhere = [];
        foreach ($searchQuery as $sqBlock) {
            $arrWhereBlock = ['or'];
            foreach ($sqBlock as $sqLine) {
                $arrWhereBlock[] = $this->dsLogicalOperations($sqLine);
            }
            $arrWhere[] = $arrWhereBlock;
        }
        // ------------
        if ($aliasJoin !== null) $this->whereJoin($aliasJoin, $nameTable, $aliasTable, $conditionJoin, $aliasWhere, $arrWhere, $beginKeywordWhere);
        else $this->where($aliasWhere, $arrWhere, $beginKeywordWhere);
        return $this;
    }


    private function dsLogicalOperations($sqLine)
    {
        $type = empty($sqLine['info']['type']) ? $sqLine['type'] : $sqLine['info']['type'];
        $column = $sqLine['info']['column'];
        $secondColumns = empty($sqLine['info']['secondColumns']) ? null : $sqLine['info']['secondColumns'];
        // --- генерируем основную часть where ---
        if ($type == 'text') {
            if (isset($sqLine['exclude'])) $whereLine = [$column, 'notFlex' => $sqLine['exclude']];
            else $whereLine = [$column, 'flex' => $sqLine['value']];
        } else {
            if (isset($sqLine['exclude'])) {
                if ($type === 'date') $whereLine = [$column, '<>', 'date' => $sqLine['exclude']];
                else $whereLine = [$column, '<>', $sqLine['exclude']];
            } else if (isset($sqLine['value'])) {
                if ($type === 'date') $whereLine = [$column, '=', 'date' => $sqLine['value']];
                else $whereLine = [$column, '=', $sqLine['value']];
            } else if (isset($sqLine['start']) && isset($sqLine['end'])) {
                if ($type === 'number') $whereLine = [$column, 'between', (int)$sqLine['start'], (int)$sqLine['end']];
                else if ($type === 'date') $whereLine = [$column, 'between', 'date1' => $sqLine['start'], 'date2' => $sqLine['end']];
            } else {
                if (isset($sqLine['start'])) {
                    if ($type === 'number') $whereLine = [$column, '>=', (int)$sqLine['start']];
                    else if ($type === 'date') $whereLine = [$column, '>=', 'date' => $sqLine['start']];
                } else if (isset($sqLine['end'])) {
                    if ($type === 'number') $whereLine = [$column, '<=', (int)$sqLine['end']];
                    else if ($type === 'date') $whereLine = [$column, '<=', 'date' => $sqLine['end']];
                }
            }
        }
        // -----
        if (is_array($secondColumns)) {
            // $secondColumns: ключ массива - название колонки, значение массива - значение ячейки
            $whereLine = [$whereLine];
            foreach ($secondColumns as $cl => $val) {$whereLine[] = [$cl, '=', $val];}
        }
        return $whereLine;
    }
    // ---------- end whereDetailedSearch ----------


    public function sort($nameColumn)
    {
        $this->sort = $nameColumn;
        return $this;
    }


    public function pagination($currentPage, $numberRowOnPage)
    {
        $this->pagination = [
            'currentPage' => $currentPage,
            'numberRowOnPage' => $numberRowOnPage,
            'numberAllRow' => null,
            'numberAllPage' => null
        ];
        return $this;
    }



    // -------------------------------------------- голубая группа -----------------------------------------------------



    public function oneOrError($nameColumn = null)
    {
        $this->startDI();
        $countRow = $this->_count('*');
        if ($countRow > 1) throw new GreenPigQueryException('The response from the database contains more than one line!', null, [
            ['Number of rows in the sample:', $countRow],
            ['sql:', $this->baseSQL]
        ]);
        $result = $this->_first($nameColumn);
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = $result;
        return $result;
    }



    public function first($nameColumn = null)
    {
        $this->startDI();
        $result = $this->_first($nameColumn);
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = $result;
        return $result;
    }

    private function _first($nameColumn = null)
    {
        $result = $this->_execute($this->baseSQL, 'first');
        if ($nameColumn) {
            $nameColumn = BaseFun::trimLower($nameColumn);
            $result = BaseFun::arrKeyTrimLower($result); // !!!!! если не массив (у mysql)
            $result = isset($result[$nameColumn]) ? $result[$nameColumn] : null;
        }
        return $result;
    }



    public function count($nameColumn = '*')
    {
        $this->startDI();
        $result = $this->_count($nameColumn);
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = $result;
        return $result;
    }

    private function _count($nameColumn)
    {
        $aliasTable = $this->gpAliasTable . $this->getNewNumberAlias();
        $sql = "SELECT COUNT($nameColumn) as count_row FROM ( 
                /* --- start user request --- */ 
                {$this->baseSQL} 
                /* --- end user request --- */ 
                ) $aliasTable ";
        $result = $this->_execute($sql, 'first');
        $result = BaseFun::arrKeyTrimLower($result);
        return (int)$result['count_row'];
    }


    public function execute()
    {
        $this->startDI();
        $rdbms = BaseFun::getSettings($this->settings, 'rdbms');
        $sql = $this->baseSQL;
        $sort = $this->genOrderSelectGroup();
        $this->genPagination(null);
        $aliasTable = $this->gpAliasTable . $this->getNewNumberAlias();
        if ($sort['order']) {
            $sql = "select * from (
                    /* --- start user request --- */
                    $sql
                    /* --- end user request --- */
                    ) $aliasTable order by {$sort['order']}";
        }
        if (!empty($this->pagination)) {
            if ($rdbms == 'oracle') $sql = $this->genPaginationSqlOracle($sql, !empty($this->sort));
            if ($rdbms == 'mysql') $sql = $this->genPaginationSqlMysql($sql, !empty($this->sort));
        }
        $result = $this->_execute($sql, 'execute');
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = $result;
        return $result;
    }


    // ---------------------- блок функций для all() ----------------------
    /**
     *
     *               Возможные варианты:
     *     ----------------------------------------
     *     № | агрегация | пагинация | сортировка |
     *     ----------------------------------------
     *     1 |     0     |     0     |     0      |
     *     2 |     0     |     0     |     1      |
     *     3 |     0     |     1     |     0      |
     *     4 |     0     |     1     |     1      |
     *     5 |     1     |     0     |     0      |
     *     6 |     1     |     0     |     1      |
     *     7 |     1     |     1     |     0      |
     *     8 |     1     |     1     |     1      |
     *     ----------------------------------------
     *
     *  Для 1 - 6 случаев подайдет блок схема:
     *
     *                   sql
     *                    |
     *            __ is sort _(yes)_
     *           |                 |
     *           |           sql = sortSql
     *           |_________________|
     *                    |
     *           __ is pagination _(y)_
     *          |                     |
     *          |        sql = sql + (limit or where)
     *          |____________________|
     *                    |
     *               execute sql
     *
     *  7ой и особенно 8ой случай особые. Т.к. тут одновременно и агрегация и пагинация, то здесь необходимо делать
     *  группировку по PK верхнего уровня $options. А при группировках слетает сортировка, и вообще сортировка, помещенная
     *  на уровень ниже в запросе не дает гарантию, что сортировка будет правильной, из-за этого приходится извращаться.
     *  Также при группировке возможно сортировать только по столбуам верхнего уровня, указанных в $options.
     */
    public function all($options = null, $isKeyPK = false)
    {
        $this->startDI();
        // --- Подготовка и проверка данных ---
        $sql = $this->baseSQL;
        $rdbms = BaseFun::getSettings($this->settings, 'rdbms');
        $scenario = $this->getScenarioAll($options);
        $options = $this->validOptionsForAll($options);
        $orderSelectGroup = $this->genOrderSelectGroup($options);
        $this->genPagination($options);
        $pk = $scenario['aggregation'] ? $this->getPkAtCurrentLevel($options) : null;
        // --- генерим sql в соответствии со сценариями ---
        if ($scenario['scenario'] < 7) {
            if ($scenario['sort']) {
                $aliasTable1 = $this->gpAliasTable . $this->getNewNumberAlias();
                $sql = " select * from ( 
                         /* --- start user request --- */ $sql /* --- end user request --- */ 
                         ) $aliasTable1 
                         order by {$orderSelectGroup['order']}";
            }
            if ($scenario['pagination']) {
                if ($rdbms == 'oracle') $sql = $this->genPaginationSqlOracle($sql, $scenario['sort']);
                if ($rdbms == 'mysql') $sql = $this->genPaginationSqlMysql($sql, $scenario['sort']);
            }
        }
        if ($scenario['scenario'] >= 7) {
            if ($rdbms == 'oracle') $sql = $this->genPaginationWithGroupSqlOracle($sql, $pk, $orderSelectGroup);
            if ($rdbms == 'mysql') $sql = $this->genPaginationWithGroupSqlMysql($sql, $pk, $orderSelectGroup);
        }
        // --- выполнение запроса и агригация, если нужно ---
        $result = $this->_execute($sql, 'all');
        if ($scenario['aggregation']) {
            $result = BaseFun::arrKeyTrimLower($result);
            $optionsOldLogics = $this->convertOptionsToOldLogics($options);
            $result = $this->aggregator($optionsOldLogics, $result);
            if ($isKeyPK) $this->_allAggregatorKeyPK($result, $optionsOldLogics);
            else $result = $this->_allAggregatorKeyNotPK($result, $optionsOldLogics);
        }
        $this->rawData = $result;
        $this->endDI();
        $this->clearThisObject();
        return $result;
    }


    private function getPkAtCurrentLevel($option)
    {
        if (empty($option['pk'])) throw new GreenPigQueryException('In the option array, at each nesting level, there must be a pk key.', $option);
        return $option['pk'];
    }


    private function validOptionsForAll($options)
    {
        if (!empty($options)) {
            if (!is_array($options)) throw new GreenPigQueryException('Options must be an array.', $options);
            $options = BaseFun::arrKeyTrimLower($options);
            $options = BaseFun::arrValTrimLower($options);
            $this->isValidPkForOptions($options);
        }
        return $options;
    }


    private function isValidPkForOptions($options)
    {
        $this->getPkAtCurrentLevel($options);
        foreach ($options as $opt) {
            if (is_array($opt)) $this->isValidPkForOptions($opt);
        }
    }


    // $isSort нужен только для правильно расставления комментариев /* --- start user request --- */
    private function genPaginationSqlOracle($sql, $isSort)
    {
        $aliasRownum = $this->gpAliasRownum . $this->getNewNumberAlias();
        $aliasTable1 = $this->gpAliasTable . $this->getNewNumberAlias();
        $aliasTable2 = $this->gpAliasTable . $this->getNewNumberAlias();
        $aliasIndexStart = "greenpig_alias_is_" . $this->getNewNumberAlias();
        $aliasIndexEnd = "greenpig_alias_ie_" . $this->getNewNumberAlias();
        $indexStart = $this->pagination['numberRowOnPage'] * ($this->pagination['currentPage'] - 1) + 1;
        $indexEnd = $indexStart + $this->pagination['numberRowOnPage'];
        $this->addBinds([
            $aliasIndexStart => $indexStart,
            $aliasIndexEnd => $indexEnd
        ]);
        if (!$isSort) $sql = " /* --- start user request --- */ $sql /* --- end user request --- */ ";
        $sql = "select * from (
                    select rownum $aliasRownum, $aliasTable1.* from (
                        $sql                               
                    ) $aliasTable1
                ) $aliasTable2
                where $aliasTable2.$aliasRownum >= :$aliasIndexStart and $aliasTable2.$aliasRownum < :$aliasIndexEnd";
        return $sql;
    }


    private function genPaginationSqlMysql($sql, $isSort)
    {
        $aliasTable = $this->gpAliasTable . $this->getNewNumberAlias();
        $indexStart = $this->pagination['numberRowOnPage'] * ($this->pagination['currentPage'] - 1);
        $numberRowOnPage = $this->pagination['numberRowOnPage'];
        $aliasIndexStart = ':greenpig_alias_is_' . $this->getNewNumberAlias();
        $aliasNumberRowOnPage = ':greenpig_alias_ie_' . $this->getNewNumberAlias();
        $this->addBinds([
            "$aliasIndexStart [int]" => $indexStart,
            "$aliasNumberRowOnPage [int]" => $numberRowOnPage
        ]);
        if (!$isSort) $sql = " /* --- start user request --- */ $sql /* --- end user request --- */ ";
        $sql = "select * from ($sql) $aliasTable limit $aliasIndexStart, $aliasNumberRowOnPage ";
        return $sql;
    }


    private function genPaginationWithGroupSqlOracle($sql, $pk, $orderSelectGroup)
    {
        $aliasWith = $this->gpAliasTable . $this->getNewNumberAlias();
        $selectOrGroup = $orderSelectGroup['order'] ? $orderSelectGroup['selectOrGroup'] : $pk;
        $order = $orderSelectGroup['order'] ? 'order by ' . $orderSelectGroup['order'] : '';
        $aliasRownum = $this->gpAliasRownum . $this->getNewNumberAlias();
        $aliasTable0 = $this->gpAliasTable . $this->getNewNumberAlias();
        $aliasTable1 = $this->gpAliasTable . $this->getNewNumberAlias();
        $aliasTable2 = $this->gpAliasTable . $this->getNewNumberAlias();
        $aliasIndexStart = "greenpig_alias_is_" . $this->getNewNumberAlias();
        $aliasIndexEnd = "greenpig_alias_ie_" . $this->getNewNumberAlias();
        $indexStart = $this->pagination['numberRowOnPage'] * ($this->pagination['currentPage'] - 1) + 1;
        $indexEnd = $indexStart + $this->pagination['numberRowOnPage'];
        $this->addBinds([
            $aliasIndexStart => $indexStart,
            $aliasIndexEnd => $indexEnd
        ]);
        // Если не использовать with, то очень тяжелые запросы выполняются практически бесконечно,
        // а с данной конструкцией все летает.
        $sql = "with $aliasWith as (
                    /* --- start user request --- */
                    $sql
                    /* --- end user request --- */
                )
                
                select * from ($aliasWith)
                where $pk in (
                    select $pk from (
                        select rownum $aliasRownum, $aliasTable1.* from (
                            select $selectOrGroup from ($aliasWith) $aliasTable0
                            group by $selectOrGroup
                            $order
                        ) $aliasTable1
                    ) $aliasTable2
                    where $aliasTable2.$aliasRownum >= :$aliasIndexStart and $aliasTable2.$aliasRownum < :$aliasIndexEnd
                )
                $order";
        return $sql;
    }


    private function genPaginationWithGroupSqlMysql($sql, $pk, $orderSelectGroup)
    {
        $sqlUser = " /* --- start user request --- */ $sql /* --- end user request --- */ ";
        $selectOrGroup = $orderSelectGroup['order'] ? $orderSelectGroup['selectOrGroup'] : $pk;
        $order = $orderSelectGroup['order'] ? 'order by ' . $orderSelectGroup['order'] : '';
        $aliasTable1 = $this->gpAliasTable . $this->getNewNumberAlias();
        $aliasTable2 = $this->gpAliasTable . $this->getNewNumberAlias();
        $aliasTable3 = $this->gpAliasTable . $this->getNewNumberAlias();
        $indexStart = $this->pagination['numberRowOnPage'] * ($this->pagination['currentPage'] - 1);
        $numberRowOnPage = $this->pagination['numberRowOnPage'];
        $aliasIndexStart = ':greenpig_alias_is_' . $this->getNewNumberAlias();
        $aliasNumberRowOnPage = ':greenpig_alias_ie_' . $this->getNewNumberAlias();
        $this->addBinds([
            "$aliasIndexStart [int]" => $indexStart,
            "$aliasNumberRowOnPage [int]" => $numberRowOnPage
        ]);
        $sql = "select * from ($sqlUser) $aliasTable1
                where $pk in (
                    select $pk from (
                        select $selectOrGroup from ( $sqlUser ) $aliasTable2
                        group by $selectOrGroup
                        $order
                        limit $aliasIndexStart, $aliasNumberRowOnPage
                    ) $aliasTable3   
                )
                $order";
        return $sql;

    }


    // Определяет сценарий
    private function getScenarioAll($options = null)
    {
        $a = empty($options) ? 0 : 1;
        $p = empty($this->pagination) ? 0 : 1;
        $s = empty($this->sort) ? 0 : 1;
        return [
            'scenario' => bindec("$a$p$s") + 1,
            'aggregation' => $a,
            'pagination' => $p,
            'sort' => $s
        ];
    }


    // $options из функции all(), (уже с отформатированными ключами и значениями)
    private function genOrderSelectGroup($options = null)
    {
        $result = [
            'order' => '',
            'selectOrGroup' => ''
        ];
        if ($this->sort) {
            // --- проверяем $this->sort на валидность и приводим его в пристойный вид  ---
            if (is_string($this->sort)) $sort = [$this->sort];
            elseif (is_array($this->sort)) $sort = $this->sort;
            else throw new GreenPigQueryException('Wrong variable format sort.', $this->sort);
            $sort = BaseFun::arrValTrimLower($sort);
            foreach ($sort as $srt) {
                if (!is_string($srt)) throw new GreenPigQueryException('Wrong variable format sort.', $this->sort);
            }
            // ---
            // в случае агрегации в сортировке могут учавствовать только колонки указанные в $options на верхнем уровне
            $validSort = [];
            if ($options) {
                $selectOrGroup = [];
                $pk = $this->getPkAtCurrentLevel($options);
                $keyOpt = [];
                foreach ($options as $optKey => $optVal) {
                    if (is_string($optVal)) $keyOpt[$optVal] = $optVal;
                }
                foreach ($sort as $srt) {
                    $buf = str_replace(' desc', '', $srt);
                    if (!empty($keyOpt[$buf])) {
                        $validSort[] = $srt;
                        $selectOrGroup[] = $buf;
                    }
                }
                $indexPK = array_search($pk, $selectOrGroup, true);
                if($indexPK === false) array_unshift($selectOrGroup, $pk);
                else {
                    if ($indexPK !== 0) {
                        $buf = $selectOrGroup[0];
                        $selectOrGroup[0] = $selectOrGroup[$indexPK];
                        $selectOrGroup[$indexPK] = $buf;
                    }
                }
                $result['selectOrGroup'] = implode(", ", $selectOrGroup);
            } else $validSort = $sort;
            $result['order'] = implode(", ", $validSort);
        }
        return $result;
    }


    private function genPagination($options)
    {
        if (!empty($this->pagination)) {
            $pk = false;
            if (!empty($options)) $pk = $this->getPkAtCurrentLevel($options);
            $pk = $pk ? "distinct $pk" : '*';
            $countRow = $this->_count($pk);
            $this->pagination['numberAllRow'] = $countRow;
            $this->pagination['numberAllPage'] = ceil($countRow / $this->pagination['numberRowOnPage']);
        }
    }


    // --------------------------------------------
    // AGGREGATOR (старый код из первой версии, надо переписать)
    // --------------------------------------------
    private function aggregator($option, $rawData) {
        $pk = $this->_getPrimaryKeyForAggregator($option);
        $adaptedOption = []; // остается только 2 уровня вложенности
        foreach ($option as $nameColumn => $alias) {
            if (is_array($alias)) $adaptedOption[$nameColumn] = $this->_getAdaptedOptionForAggregator($alias);
            else $adaptedOption[$nameColumn] = $alias;
        }
        $processedData = $this->_getProcessedDataForAggregator($pk, $adaptedOption, $rawData);
        // рекурсивно обрабатываем сгруппированные части
        foreach ($processedData as $index => $pd) {
            foreach ($option as $nameNewColumn => $opt) {
                if (is_array($opt)) {
                    $lowerLevelPk = $this->_getPrimaryKeyForAggregator($opt);
                    if ($lowerLevelPk) {
                        $processedData[$index][$nameNewColumn] = $this->aggregator($opt, $pd[$nameNewColumn]);
                    }
                }
            }
        }
        return $processedData;
    }

    private function convertOptionsToOldLogics($options) {
        $optionsOldLogics = [];
        foreach ($options as $key => $val) {
            if (is_array($val)) $optionsOldLogics[$key] = $this->convertOptionsToOldLogics($val);
            else {
                if ($key === 'pk') $optionsOldLogics[$val] = 'pk';
                else $optionsOldLogics[$val] = $val;
            }
        }
        return $optionsOldLogics;
    }

    private function _getPrimaryKeyForAggregator($option) {
        $pk = [];
        foreach ($option as $nameColumn => $alias) {
            if (is_string($alias) && mb_strtolower(trim($alias)) === 'pk') $pk[] = $nameColumn;
        }
        if (count($pk) > 1) throw new \Exception('В массиве option на каждом уровне вложенности pk должен быть ТОЛЬКО ОДИН.');
        if (count($pk) === 0) throw new \Exception('В массиве option на каждом уровне вложенности ДОЛЖЕН быть pk.');
        return $pk[0];
    }

    private function _getAdaptedOptionForAggregator($option) {
        $adaptedOption = [];
        foreach ($option as $nameColumn => $alias) {
            if (is_array($alias)) $adaptedOption = array_merge($adaptedOption, $this->_getAdaptedOptionForAggregator($alias));
            else $adaptedOption[] = $nameColumn;
        }
        return $adaptedOption;
    }

    private function _getProcessedDataForAggregator($pk, $option, $rawData) {
        $processedData = [];
        foreach ($rawData as $rd) {
            $pLine = $this->_processedLineForAggregator($pk, $option, $rd);
            // повторяющаяся запись
            if (isset($processedData[$rd[$pk]])) {
                foreach ($option as $nameNewColumn => $opt) {
                    if (is_array($opt)) $processedData[$rd[$pk]][$nameNewColumn][] = $pLine[$nameNewColumn][0];
                }
            } else $processedData[$rd[$pk]] = $pLine; // новая запись
        }
        return $processedData;
    }

    private function _processedLineForAggregator($pk, $option, $rawDataLine) {
        $bData = [];
        foreach ($option as $nameColumn => $alias) {
            if (is_string($alias) && $nameColumn !== $pk) {
                $bData[$alias] = $rawDataLine[$nameColumn];
            } else if (is_array($alias)) {
                $nameNewColumn = $nameColumn;
                $arrNameColumn = $alias; // Одномерный массив названий колонок (после приведения опций к двумерному массиву)
                $bbData = [];
                foreach ($arrNameColumn as $nc) {
                    $bbData[$nc] = $rawDataLine[$nc];
                }
                $bData[$nameNewColumn][] = $bbData;
            }
        }
        return $bData;
    }

    // ---

    private function _allAggregatorKeyNotPK($aggregatorData, $options)
    {
        $pk = $this->_getPrimaryKeyForAggregator($options);
        $result = [];
        foreach ($aggregatorData as $pkVal => $arr) {
            $buf = [];
            $buf[$pk] = $pkVal;
            foreach ($arr as $nameColumn => $val) {
                if (is_array($val)) $buf[$nameColumn] = $this->_allAggregatorKeyNotPK($val, $options[$nameColumn]);
                else $buf[$nameColumn] = $val;
            }
            $result[] = $buf;
        }
        return $result;
    }

    private function _allAggregatorKeyPK(&$aggregatorData, $options)
    {
        $pk = $this->_getPrimaryKeyForAggregator($options);
        foreach ($aggregatorData as $pkVal => &$arr) {
            $arr = [$pk => $pkVal] + $arr;
            foreach ($arr as $nameColumn => &$val) {
                if (is_array($val)) $this->_allAggregatorKeyPK($val, $options[$nameColumn]);
            }
        }
    }



    // -------------------------------------------- красная группа -----------------------------------------------------



    public function insert($table, $parameters, $primaryKey = null)
    {
        $this->startDI();
        $sqlData = $this->decomposedParameters($parameters);
        $sql = "INSERT INTO $table ({$sqlData['insertColumn']}) VALUES ({$sqlData['insertValue']}) ";
        $this->addBinds($sqlData['bind']);
        $rdbms = BaseFun::getSettings($this->settings, 'rdbms');
        if ($rdbms == 'oracle') $result = $this->_execute($sql, 'insert', $table, $primaryKey);
        else $result = $this->_execute($sql, 'insert');
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = $result;
        return $result;
    }


    //todo: Тупая реализация, нужно переделать
    public function inserts($table, $parameters) {
        // startDI нет, т.к. дергается insert в котором это есть
        foreach ($parameters as $param) {
            $this->insert($table, $param);
        }
        $this->rawData = count($parameters);
        return count($parameters);
    }


    public function update($table, $parameters, $where)
    {
        $this->startDI();
        $where = $this->preprocessorWhere($where);
        if (!(is_array($where) && count($where))) throw new GreenPigQueryException('Where expression is incorrectly composed.', $where);
        $wh = new Where($this->settings);
        $sqlWhere = $wh->where($where, 'WHERE', $this->getNewNumberAlias());
        $this->getNewNumberAlias($wh->numberAlias);
        $this->addBinds($wh->getBinds());
        $sql = "SELECT count(*) count_update FROM $table $sqlWhere";
        $countChange = $this->_execute($sql, 'first');
        $countChange = (int)BaseFun::arrKeyTrimLower($countChange)['count_update'];
        $sqlData = $this->decomposedParameters($parameters);
        $sql = "UPDATE $table SET {$sqlData['update']} $sqlWhere";
        $this->addBinds($sqlData['bind']);
        $this->_execute($sql, 'execute');
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = $countChange;
        return $countChange;
    }


    public function insertUpdate($table, $paramInsert, $paramUpdate)
    {
        $this->startDI();
        $typeQuery = 'error';
        $table = BaseFun::trimUpper($table);
        $sqlDataInsert = $this->decomposedParameters($paramInsert);
        $sqlDataUpdate = $this->decomposedParameters($paramUpdate);
        $rdbms = BaseFun::getSettings($this->settings, 'rdbms');
        if ($rdbms == 'oracle') {

            $sysSql = "SELECT distinct cc.COLUMN_NAME FROM user_cons_columns cc
                   JOIN user_constraints c ON c.constraint_name = cc.constraint_name
                   WHERE c.table_name = :nametable AND (c.constraint_type = 'P' OR c.constraint_type = 'U')";
            $columnNameU = $this->_execute($sysSql, 'all', null, null, ['nametable' => $table]);
            $columnNameU = BaseFun::arrKeyTrimUpper($columnNameU);
            $paramInsert = BaseFun::arrKeyTrimUpper($paramInsert);
            $whArr = [];
            foreach ($columnNameU as $cn) {
                $n = $cn['COLUMN_NAME'];
                if (isset($paramInsert[$n])) $whArr[] = [$n, '=', $paramInsert[$n]];
            }
            $wh = new Where($this->settings);
            $sqlWhere = $wh->where($whArr, 'WHERE', $this->getNewNumberAlias());
            $this->getNewNumberAlias($wh->numberAlias);
            // ---
            $sql = "BEGIN
                  :v_dtype := 'insert';
                  INSERT INTO $table ({$sqlDataInsert['insertColumn']}) VALUES ({$sqlDataInsert['insertValue']});
                EXCEPTION
                  WHEN DUP_VAL_ON_INDEX THEN
                    :v_dtype := 'update';
                    UPDATE $table SET {$sqlDataUpdate['update']} $sqlWhere;
                END;";
            $this->addBinds($sqlDataInsert['bind'])
                ->addBinds($sqlDataUpdate['bind'])
                ->addBinds($wh->getBinds())
                ->addBinds(['v_dtype [7]' => &$typeQuery])
                ->_execute($sql, 'execute');

        } elseif ($rdbms == 'mysql') {

            $sql = "INSERT INTO $table ({$sqlDataInsert['insertColumn']}) VALUES ({$sqlDataInsert['insertValue']}) ";
            $this->addBinds($sqlDataInsert['bind']);
            try {
                $this->_execute($sql, 'insert');
                $typeQuery = 'insert';
            } catch (GreenPigDatabaseException $e) {
                if (strpos($e->getMessage(), 'Integrity constraint violation') !== false) {
                    $sql = "INSERT INTO $table ({$sqlDataInsert['insertColumn']}) VALUES ({$sqlDataInsert['insertValue']})
                            ON DUPLICATE KEY 
                            UPDATE {$sqlDataUpdate['update']}";
                    $this->addBinds($sqlDataUpdate['bind']);
                    $this->_execute($sql, 'execute');
                    $typeQuery = 'update';
                } else throw new GreenPigDatabaseException($e->getMessage(), $e, $sql, $this->binds);
            }

        }
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = [];
        return $typeQuery;
    }


    // Если значение не массив, то биндим через параметры, если массив то вставляем в sql как есть
    private function decomposedParameters($parameters)
    {
        $insertColumn = array_keys($parameters);
        $insertColumn = implode(", ", $insertColumn);
        $insertVal = [];
        $bind = [];
        $update = [];
        foreach ($parameters as $col => $val) {
            if (is_array($val)) {
                $insertVal[] = " {$val[0]} ";
                $update[] = " $col = {$val[0]} ";
            } else {
                $alias = 'greenpig_alias_inup_' . $this->getNewNumberAlias();
                $insertVal[] = " :$alias ";
                $bind[$alias] = $val;
                $update[] = " $col = :$alias ";
            }
        }
        $insertVal = implode(", ", $insertVal);
        $update = implode(", ", $update);
        return [
            'insertColumn' => $insertColumn,
            'insertValue' => $insertVal,
            'bind' => $bind,
            'update' => $update
        ];
    }


    public function delete($table, $where)
    {
        $this->startDI();
        $where = $this->preprocessorWhere($where);
        if (is_array($where) && (count($where) === 0)) throw new GreenPigQueryException("Where expression is incorrectly composed. If you want to delete all records in this database table, then pass 'true'.", $where);
        if ($where === true) $where = []; // Удаляем все записи в таблице
        //---
        $wh = new Where($this->settings);
        $sqlWhere = $wh->where($where, 'WHERE', $this->getNewNumberAlias());
        $this->getNewNumberAlias($wh->numberAlias);
        $this->addBinds($wh->getBinds());
        $sql = "SELECT count(*) count_delete FROM $table $sqlWhere";
        $countDelete = $this->_execute($sql, 'first');
        $countDelete = (int)BaseFun::arrKeyTrimLower($countDelete)['count_delete'];
        $sql = "DELETE FROM $table $sqlWhere";
        $this->_execute($sql, 'execute');
        $this->endDI();
        $this->clearThisObject();
        $this->rawData = $countDelete;
        return $countDelete;
    }


    public function beginTransaction()
    {
        $this->db->beginTransaction();
        return $this;
    }


    public function commit()
    {
        $this->db->commit();
        return $this;
    }


    public function rollBack()
    {
        $this->db->rollBack();
        return $this;
    }



    // --------------------------------------------- синяя группа ------------------------------------------------------



    public function getData($formatting = false)
    {
        $rawData = $this->rawData;
        $formatting = is_string($formatting) ? BaseFun::trimLower($formatting) : false;
        if (($formatting == 'upper') && is_array($rawData)) $rawData = BaseFun::arrKeyTrimUpper($rawData);
        if (($formatting == 'lower') && is_array($rawData)) $rawData = BaseFun::arrKeyTrimLower($rawData);
        return $rawData;
    }


    public function getPagination()
    {
        return $this->paginationLastRequest;
    }


    public function column($nameColumn)
    {
        $rawData = $this->rawData;
        if (is_array($rawData) && count($rawData)) {
            if (is_array($rawData[0])) {
                $result = [];
                foreach ($rawData as $el) $result[] = $el[$nameColumn];
                return $result;
            } else return $rawData[$nameColumn];
        }
        return [];
    }


    public function map($pk, $columns = [])
    {
        $rawData = $this->rawData;
        if (is_array($rawData) && count($rawData)) {
            $result = [];
            if ($columns) {
                foreach ($rawData as $el) {
                    $buf = [];
                    foreach ($columns as $c) {
                        $buf[$c] = $el[$c];
                    }
                    $result[$el[$pk]] = $buf;
                }
            } else {
                foreach ($rawData as $el) {
                    $result[$el[$pk]] = $el;
                }
            }
            return $result;
        }
        return [];
    }


    public function debugInfo()
    {
        $isDebug = BaseFun::getSettings($this->settings, 'debugquery', false);
        $result = 'Debug is disabled in the settings.';
        if ($isDebug) $result = $this->debugInfo;
        return $result;
    }



    // -----------------------------------------------------------------------------------------------------------------



    private function _execute($sql, $typeSelect, $table = null, $primaryKey = null, $sysBinds = null)
    {
        $sql = $this->genSqlPart($sql);
        $binds = [];
        if ($sysBinds) $binds = $sysBinds;
        else $this->getActualBinds($sql, $binds);
        $result = [];
        if ($typeSelect == 'first') $result = $this->db->first($sql, $binds);
        elseif ($typeSelect == 'all') $result = $this->db->all($sql, $binds);
        elseif ($typeSelect == 'execute') $result = $this->db->execute($sql, $binds);
        elseif ($typeSelect == 'insert') {
            if (!empty($table)) $result = $this->db->insert($sql, $binds, $table, $primaryKey);
            else $result = $this->db->insert($sql, $binds);
        }
        else throw new GreenPigQueryException('Wrong variable format typeSelect.', $typeSelect);
        $dbDebugInfo = $this->db->getDebugInfo();
        if (count($this->bufDebugInfo) > 1000) $this->bufDebugInfo = [];
        if (!empty($dbDebugInfo[0]) && is_array($dbDebugInfo[0])) {
            foreach ($dbDebugInfo as $di) {
                $this->bufDebugInfo[] = $di;
            }
        } else $this->bufDebugInfo[] = $dbDebugInfo;
        return $result;
    }


    // Если в массиве биндов значений больше чем нужно, то игнорируем лишние. Эта функция позволила решить проблему
    // с функцией update(), когда у нас есть ручные бинды как для where части, так и для update части.
    private function getActualBinds($sql, &$actualBinds)
    {
        foreach ($this->binds as $k => &$v) {
            $name = ':' . $this->getNameBind($k);
            if (strpos($sql, $name) !== false) $actualBinds[$k] = &$v;
        }
    }


    private function getNameBind($alias)
    {
        $nameBind = trim(str_replace(":", "", $alias));
        preg_match_all("/\[(.+)\]/", $nameBind, $bindOptions);
        if (isset($bindOptions[0][0])) $nameBind = str_replace($bindOptions[0][0], "", $nameBind);
        return trim($nameBind);
    }


    private function genSqlPart($sql)
    {
        foreach ($this->partsSql as $alias => $part) {
            $sql = str_replace($alias, $part, $sql);
        }
        return $sql;
    }


    private function getNewNumberAlias($numberAlias = null)
    {
        if (is_int($numberAlias)) $this->numberAlias = $numberAlias;
        return ++$this->numberAlias;
    }


    private function startDI()
    {
        $this->timeStartQuerys = microtime(true);
    }


    private function endDI()
    {
        $isDebug = BaseFun::getSettings($this->settings, 'debugquery', false);
        if ($isDebug) {
            if (count($this->debugInfo) > 0) $numberQuery = $this->debugInfo[count($this->debugInfo) - 1]['numberQuery'] + 1;
            else $numberQuery = 1;
            $maxNumberQuery = (int)BaseFun::getSettings($this->settings, 'debugquery', false);
            while (count($this->debugInfo) >= $maxNumberQuery) array_shift($this->debugInfo);
            $this->debugInfo[] = [
                'numberQuery' => $numberQuery,
                'querys' => $this->bufDebugInfo,
                'allTime' => (microtime(true) - $this->timeStartQuerys)
            ];
        }
        $this->bufDebugInfo = [];
    }


    private function clearThisObject()
    {
        $this->baseSQL = '';
        $this->partsSql = [];
        $this->bindsLastRequest = $this->binds;
        $this->binds = [];
        $this->numberAlias = 0;
        $this->sort = '';
        $this->paginationLastRequest = $this->pagination;
        $this->pagination = false;
    }



    // -------------------------------------------------- LOG ----------------------------------------------------------



    public function newLog($type, $title, $message)
    {
        $log = new Log($this, $this->settings);
        $log->writeLog($type, $title, $message);
        return $log;
    }


    /**
     * @param $nameInstaceLog string
     * @return \GreenPig\Log
     */
    public function log($nameInstaceLog)
    {
        return self::$logs[$nameInstaceLog];
    }


    public function clearLog($nameInstaceLog = null)
    {
        if (is_string($nameInstaceLog)) {
            unset(self::$logs[$nameInstaceLog]);
        } else self::$logs = [];
        return $this;
    }


    /**
     * @param $id array|int
     * @return array
     */
    public function getLog($id)
    {
        return $this->_getLog($id, false);
    }


    public function getCanonLog($id)
    {
        return $this->_getLog($id, true);
    }


    private function _getLog($id, $isCanon)
    {
        $log = new Log($this, $this->settings);
        $data = $log->getDataLog($log->getLog($id), $isCanon);
        if (is_array($id)) return $data;
        else return isset($data[0]) ? $data[0] : [];
    }


    public function createTablesForLog($table = 'all')
    {
        $log = new Log($this, $this->settings);
        $log->createTablesForLog($table);
    }


    public function deleteLog($numberDay, $type, $title)
    {
        $log = new Log($this, $this->settings);
        return $log->deleteLog($numberDay, $type, $title);
    }

}
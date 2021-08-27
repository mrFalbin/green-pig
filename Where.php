<?php
namespace GreenPig;

use GreenPig\Exception\GreenPigWhereException;



class Where
{
    private $binds;
    private $settings;
    private $where;
    public  $numberAlias;


    public function __construct($settings) {
        $this->settings = $settings;
    }


    /**
     * $where        - логическое выражение
     * $beginKeyword - ключевое слово, которое будет вставленно перед сгенерированным sql.
     *                 Может принимать значения: where, and, or
     * $numberAlias  - номер с которого начнется нумерация псевдонимов.
     *
     * --------------------------------------------------------------------------------------------------------------- *
     *         Определимся с понятиями. ВНИМАНИЕ! Приведенные здесь определения не являются научными, это              *
     *                просто термины адаптированные для решения задачи и введенные для удобства.                       *
     * --------------------------------------------------------------------------------------------------------------- *
     *
     * Логическая операция (ЛО)  – наименьшая автономная часть where запроса.
     *                             ПР: [колонка, знакСравнения, значение]
     * Логическое выражение (ЛВ) - набор ЛО. Может включать от 1 до N ЛО, они могут быть объеденены с помошью скобок в
     *                             любом сочетании, любом количестве и любом уровне вложенности. Но при этом внутри
     *                             скобок может быть только один вид логического союза: либо AND, либо OR. Можно
     *                             выделить три варианта ЛВ:
     *    1) Короткое логическое выражение (КЛВ) - ЛВ состоит из одной ЛО (КЛВ === ЛО).
     *                                             ПР: [колонка, знакСравнения, значение]
     *    2) Базовое логическое выражение (БЛВ)  - это набор ЛО, заключенных в скобки и связанных между собой
     *                                             одним союзом (либо AND, либо OR).
     *                                             ПР: ['and', [логОперация], [логОперация]]
     *    3) Составное логическое выражение (СЛВ) - это когда ЛВ содержит в себе другие БЛВ, наровне с ЛО или без них,
     *                                              в любом сочетании, любом количестве и любом уровне вложенности.
     *                                              ПР: ['and', ['or',[логОперация],[логОперация]], ['or',[логОперация],[логОперация]] ]
     *
     *    Еще возможен вариант сокращенного напмсания БЛВ, при котором опускается логический союз, это значит что
     *    связь между логическими операциями будет AND.
     *    ПР:  [[логОперация], [логОперация]]
     */
    public function where($where, $beginKeyword, $numberAlias)
    {
        $this->where = $where;
        if (!is_array($where)) throw new GreenPigWhereException('Expression is incorrectly composed.', $where, $this->where);
        $this->binds = [];
        $this->numberAlias = $numberAlias;
        $where = $this->formattingWhere($where);
        if (count($where) === 0) return '';
        if (!$this->validLogicalExpression($where)) $where = [$where];
        return " $beginKeyword {$this->genSql($where)} ";
    }


    public function getBinds()
    {
        return $this->binds;
    }


    public function getWhereExpression()
    {
        return $this->where;
    }


    // =================================================================================================================


    // 1) Все ключи массива $where приводим к нижнему регистру и удаляем лишние пробелы.
    // 2) Для элементов массива с индексом 0, если там значение and или or (БЛВ) -  к этому элементу применяем форматирование
    // 3) Для строковых элементов массива с индексом 1 всегда применяем форматирование, т.к. это может быть только 'знакСравнения'
    private function formattingWhere($where)
    {
        $newArr = [];
        foreach ($where as $key => $val) {
            if (is_string($key)) $key = BaseFun::trimLower($key);
            if (is_string($val)) {
                if ($key === 0) {
                    $v = BaseFun::trimLower($val);
                    if ($v == 'and' || $v == 'or') $val = $v;
                }
                if ($key === 1) $val = BaseFun::trimLower($val);
                $newArr[$key] = $val;
            } elseif (is_array($val)) $newArr[$key] = self::formattingWhere($val);
            else $newArr[$key] = $val;
        }
        return $newArr;
    }


    private function genSql($lEx)
    {
        $union = $this->getUnion($lEx) ? $this->getUnion($lEx) : 'and';
        $sql = '';
        for ($i = $this->getUnion($lEx) ? 1 : 0; $i < count($lEx); $i++) {
            $un = $i < (count($lEx) - 1) ? $union : '';
            $type = $this->identifyType($lEx[$i]);
            if ($type == 'expression') $sql .= ' ('. $this->genSql($lEx[$i]) .") $un";
            else $sql .= $this->genSqlLogicalOperation($lEx[$i]) ." $un";
        }
        return $sql;
    }


    /**
     * Определяем является ли переданный элемент ЛО или ЛВ. Возможные варианты:
     * 1) expression    - логическое вырожение
     * 2) operation     - логическая операция
     */
    private function identifyType($element)
    {
        if (!is_array($element)) throw new GreenPigWhereException('Boolean expression is incorrectly composed.', $element, $this->where);
        if ($this->validLogicalExpression($element)) return 'expression';
        $LogOp = $this->validLogicalOperation($element);
        if ($LogOp['scenario'] == -1) throw new GreenPigWhereException($LogOp['txtError'], $LogOp['objError'], $this->where);
        return 'operation';
    }


    /** По формальным признакам определяем является ли переданный массив логическим выражением.
     *  Размер массива:
     *  0         - не может быть
     *  1         - В этом случае единственный элемент - логическая операция
     *  от 2 до N - а) Нулевой элемент строка со значением 'and' или 'or', все остальные элементы - массив
     *                 ПР: ['and/or', [...], [...], ... ]
     *              б) Все элементы - массив.   ПР: [[...], ... ]
     */
    private function validLogicalExpression($arr)
    {
        if (count($arr) === 0) throw new GreenPigWhereException('Boolean expression is incorrectly composed.', $arr, $this->where);
        if (count($arr) === 1) {
            if (is_array($arr[0])) return true;
            else return false;
        }
        if ($this->getUnion($arr)) return $this->isArrayOfArrays($arr, 1);
        return $this->isArrayOfArrays($arr, 0);
    }


    // Начиная с $indexStart все элементы массива $arr должны быть массивами. Массив $arr обычный, не ассоциативный.
    private function isArrayOfArrays($arr, $indexStart)
    {
        foreach (array_keys($arr) as $key) {
            if (is_string($key)) return false;
        }
        for ($i = $indexStart; $i < count($arr); $i++) {
            if (!is_array($arr[$i])) return false;
        }
        return true;
    }


    private function getUnion($el)
    {
        if (isset($el[0]) && ($el[0] == 'or' || $el[0] == 'and')) return $el[0];
        return false;
    }


    /**
     * --------------------------------------------------------------------------------------------------------------- *
     *                    Всевозможные варианты логических операций разбиты на сценарии по размеру                     *
     *                    массива лог. операции (scenario), а сценарии разбиты на действия (action).                   *
     * --------------------------------------------------------------------------------------------------------------- *
     * -1) Ошибки
     *  1) [колонка, 'названиеФункции' => значение]
     *       Колонка       - всегда строка, вставляющаяся в sql запрос без изменений, а следовательно в ней можно писать sql функции.
     *       Значение - всегда строка. В качестве ключа могут быть (action):
     *         а) flex      в) fullFlex    д) notLike
     *         б) notFlex   г) like
     *  2) [колонка, знакСравнения, значение]
     *       колонка       - всегда строка, вставляющаяся в sql запрос без изменений, а следовательно в ней можно писать sql функции.
     *       знакСравнения - всегда строка, вставляющаяся в sql запрос без изменений.
     *       Action данного сценария:
     *           а) standard - 'значение' может быть как числового, так и строкового типа.
     *           б) date     - 'значение' может быть экземпляром класса DateTime, в этом случае применится sql функция
     *                          конвертирования к дате, настройки возьмутся из массива настроек.
     *           в) in       - 'значение' является массивом, в таком случае 'знакСравнения' должен быть 'in' или 'not in'
     *           г) sql      - [колонка, знакСравнения, 'sql' => значение]
     *                         'значение' имеет строковый тип и вставляется в sql код без изменений
     *                          ПР: ['LOWER(name)', 'like', 'sql' => "LOWER(':aliasName')"]
     *           д) dateStr  - 'значение' это дата, представленная текстом, в этом случае применится sql функция
     *                          конвертирования к дате, настройки возьмутся из массива настроек. Или значение может
     *                          быть массивом, тогда перый элемент это дата, записанная текстом,
     *                          второй элемент - формат даты для sql функции.
     *  3) [колонка, 'between', значение1, значение2]
     *       колонка       - всегда строка, вставляющаяся в sql запрос без изменений, а следовательно в ней можно писать sql функции.
     *       знакСравнения - всегда строка, может принимать значения либо between либо not between
     *       Action данного сценария:
     *        а) number  - Может принимать как числовое, так и строковое значение (но подразумевается что значение1 и значение2 это числа).
     *                     ПР: ['curse', ' between', 1, '5']     sql: curse between :al_where_Pi4CRr4xNn and :al_where_WiPPS4NKiG
     *        б) date    - Элементы с индексами 2 и 3 могут быть экземплярами класса DateTime (должны быть одновеременно).
     *                     В таком случае будет преобразования к дате и ее параметры берутся из массива настроек
     *                     ПР (sql): build_date between TO_DATE(:al_where_fkD7, 'dd.mm.yyyy hh24:mi::ss') and TO_DATE(:al_where_LdyV, 'dd.mm.yyyy hh24:mi::ss')
     *        в) dateStr - Ключи в массиве должны быть date1 и date2, а значения это дата, представленная текстом,
     *                     в этом случае применится sql функция конвертирования к дате, настройки возьмутся из массива настроек.
     *                     [колонка, 'between', 'date1' => значение, 'date2' => значение]
     *                     Или значение может быть массивом, тогда перый элемент это дата, записанная текстом,
     *                     второй элемент - формат даты для sql функции.
     * -----------------------------------------------------------------------------------------------------------------
     * Функция возвращает массив:
     * [
     *    scenario => номер сценария, причем -1 означет что была ошибка
     *    action   => наименование действия (при ошибки отсутствует)
     *    txtError => текст ошибки (если не было ошибки - отсутствует)
     * ]
     */
    private function validLogicalOperation($arr)
    {
        if (!is_array($arr)) return $this->returnErrorVLO('Logical operation must be described by an array.', $arr);
        if ((count($arr) < 2) || (count($arr) > 4)) {
            return $this->returnErrorVLO("Logical operation is incorrectly composed. Parameter size 'arr' minimum 2, maximum 4.", $arr);
        }
        if(!$this->isElArrStr($arr, 0)) {
            return $this->returnErrorVLO('There must always be an array element with index 0 (the name of the '
                                              . 'column of a logical operation) and it must be a string.', $arr);
        }
        // Если длинна логической операции равна 2, это значит что второй элемент всегда имеет строковый или числовой тип и у него
        // ключ должен быть следующим: like, notLike, flex, notFlex, fullFlex.
        if (count($arr) == 2) {
            if ($this->isElArrStrOrNumber($arr,'like'))  return $this->returnVLO(1, 'like');
            if ($this->isElArrStrOrNumber($arr, 'notlike'))  return $this->returnVLO(1, 'notlike');
            if ($this->isElArrStrOrNumber($arr, 'flex'))     return $this->returnVLO(1, 'flex');
            if ($this->isElArrStrOrNumber($arr, 'notflex'))  return $this->returnVLO(1, 'notflex');
            if ($this->isElArrStrOrNumber($arr, 'fullflex')) return $this->returnVLO(1, 'fullflex');
            return $this->returnErrorVLO('If a logical operation consists of 2 elements, then the second element '
                                                  . 'always has a string or numeric type and its key must be as follows: '
                                                  . 'like, notLike, flex, notFlex, fullFlex.', $arr);
        }
        // Если длинна логической операции равна 3:
        //   а) Всегда существует элемент с индексом '1' и у него строковой тип.
        //   б) В качестве третьего элемента массива может существовать либо элемент с индексом '2' либо ключь 'sql', либо ключь 'date':
        //       - Если существует элемент с индексом '2', то он может быть cтрокой, числом, DateTime или массивом. В последнем случае
        //         элемент с индексом 1 (знакСравнения) должен принимать только такие значения: 'in' или 'not in'. Если элемент это
        //         экземпляр DateTime, то применяется sql функция приведения к дате.
        //       - Если существует элемент с ключем 'sql', то значение у него всегда строковое.
        if (count($arr) == 3) {
            if(!$this->isElArrStr($arr, 1)) {
                return $this->returnErrorVLO('The second element of the logical operation (comparison sign) must be a string.', $arr);
            }
            if (isset($arr[2])) {
                if (is_array($arr[2])) {
                    if (!($arr[1] == 'in' || $arr[1] == 'not in')) {
                        return $this->returnErrorVLO("If the third element in a logical operation is an array, ".
                            "then the second (comparison sign) can only take the values 'in' or 'not in'.", $arr);
                    }
                    elseif (count($arr[2])) return $this->returnVLO(2, 'in');
                    else return $this->returnErrorVLO('Logical operation is incorrectly composed.', $arr);
                }
                if (is_object($arr[2])) {
                    if (BaseFun::isClass($arr[2], 'DateTime')) return $this->returnVLO(2, 'date');
                    else return $this->returnErrorVLO("The third element in a boolean operation can only be a ".
                        "number, string, array, or an instance of the 'DateTime' class.", $arr);
                }
                if (is_string($arr[2]) || is_int($arr[2]) || is_float($arr[2])) {
                    if ($arr[1] == 'between' || $arr[1] == 'not between') {
                        return $this->returnErrorVLO("For a boolean operation with the 'between' keyword, four ".
                            "parameters are required.", $arr);
                    }
                    return $this->returnVLO(2, 'standard');
                }
                return $this->returnErrorVLO('Logical operation is incorrectly composed.', $arr);
            }
            if ($this->isElArrStr($arr, 'sql')) return $this->returnVLO(2, 'sql');
            elseif ($this->isElArrStrOrArr($arr, 'date')) return $this->returnVLO(2, 'dateStr');
            return $this->returnErrorVLO('Logical operation is incorrectly composed.', $arr);
        }
        // Если длинна логической операции равна 4:
        //   а) Должны существовать элементы с индексами 1, 2 и 3, причем элемент с индексом '1' может принимать только
        //      такие значения: 'between' или 'not between'
        //   б) Элементы с индексами 2 и 3 должны быть одновременно либо числовыми/строковыми, либо с типом  DateTime
        //   в) Должны быть строковые элементы с индексами 'date1' и 'date2'
        if (count($arr) == 4) {
            if (!$this->isElArrStr($arr, 1)) {
                return $this->returnErrorVLO('The second element of the logical operation (comparison sign) must be a string.', $arr);
            }
            if ($arr[1] != 'between' && $arr[1] != 'not between') {
                return $this->returnErrorVLO("The second element of the logical operation (comparison sign) can ".
                    "only take the following values: 'between' or 'not between'.", $arr);
            }
            if (isset($arr[2]) && isset($arr[3])) {
                if (((is_string($arr[2]) && preg_match('/^[\d\.\,\-]+$/', $arr[2])) || is_int($arr[2]) || is_float($arr[2])) &&
                    ((is_string($arr[3]) && preg_match('/^[\d\.\,\-]+$/', $arr[3])) || is_int($arr[3]) || is_float($arr[3]))) {
                    return $this->returnVLO(3, 'number');
                }
                if (BaseFun::isClass($arr[2], 'DateTime') &&  BaseFun::isClass($arr[3], 'DateTime')) {
                    return $this->returnVLO(3, 'date');
                }
                return $this->returnErrorVLO("The third and fourth elements of a boolean operation must be ".
                    "either numeric/string or DateTime at the same time.", $arr);
            }
            if ($this->isElArrStrOrArr($arr, 'date1') && $this->isElArrStrOrArr($arr, 'date2')) {
                return $this->returnVLO(3, 'dateStr');
            }
        }
        return $this->returnErrorVLO('Logical operation is incorrectly composed.', $arr);
    }


    private function returnVLO($scenario, $action)
    {
        return ['scenario' => $scenario, 'action' => $action];
    }


    private function returnErrorVLO($txtError, $objError)
    {
        return ['scenario' => -1, 'txtError' => $txtError, 'objError' => $objError];
    }


    private function isElArrStr($arr, $key)
    {
        return isset($arr[$key]) && is_string($arr[$key]);
    }


    private function isElArrStrOrNumber($arr, $key)
    {
        return isset($arr[$key]) &&  (is_string($arr[$key]) || is_int($arr[$key]) || is_double($arr[$key]));
    }


    private function isElArrStrOrArr($arr, $key)
    {
        return isset($arr[$key]) &&  (is_string($arr[$key]) || is_array($arr[$key]));
    }


    private function genSqlLogicalOperation($logOperation)
    {
        $typeLogOp = $this->validLogicalOperation($logOperation);
        if ($typeLogOp['scenario'] == -1) throw new GreenPigWhereException($typeLogOp['txtError'], $typeLogOp['objError'], $this->where);
        // ----- Все сводим ко второму сценарию action - sql -----
        $logOp = [];
        if ($typeLogOp['scenario'] == 1) {
            if ($typeLogOp['action'] == 'flex') $logOp = $this->flex($logOperation[0], $logOperation['flex'], false);
            elseif ($typeLogOp['action'] == 'notflex') $logOp = $this->flex($logOperation[0], $logOperation['notflex'], true);
            elseif ($typeLogOp['action'] == 'fullflex') $logOp = $this->flex($logOperation[0], '*'. $logOperation['fullflex'] .'*', false);
            elseif ($typeLogOp['action'] == 'like') {
                $alias = $this->genAliasAndSetBind(BaseFun::repStar($logOperation['like']));
                $logOp = [$logOperation[0], 'like', 'sql' => ":$alias"];
            }
            elseif ($typeLogOp['action'] == 'notlike') {
                $alias = $this->genAliasAndSetBind(BaseFun::repStar($logOperation['notlike']));
                $logOp = [$logOperation[0], 'not like', 'sql' => ":$alias"];
            }
            else throw new GreenPigWhereException('ERROR in logical operation.', $logOperation, $this->where);
        }
        elseif ($typeLogOp['scenario'] == 2) {
            if ($typeLogOp['action'] == 'standard') {
                $alias = $this->genAliasAndSetBind($logOperation[2]);
                $logOp = [$logOperation[0], $logOperation[1], 'sql' => ":$alias"];
            }
            elseif ($typeLogOp['action'] == 'date') {
                $formatDatePHP = BaseFun::getSettings($this->settings, 'date/php', false);
                $alias = $this->genAliasAndSetBind($logOperation[2]->format($formatDatePHP));
                $logOp = [$logOperation[0], $logOperation[1], 'sql' => $this->strToDate($alias)];
            }
            elseif ($typeLogOp['action'] == 'dateStr') {
                $format = null;
                if (is_array($logOperation['date'])) {
                    $val = $logOperation['date'][0];
                    $format = $logOperation['date'][1];
                } else $val = $logOperation['date'];
                $alias = $this->genAliasAndSetBind($val);
                $logOp = [$logOperation[0], $logOperation[1], 'sql' => $this->strToDate($alias, $format)];
            }
            elseif ($typeLogOp['action'] == 'in') {
                // размер массива $logOperation[2] точно больше 0
                $aliases = [];
                foreach ($logOperation[2] as $val) {
                    $aliases[] = ':' . $this->genAliasAndSetBind($val);
                }
                $aliases = implode(", ", $aliases);
                $logOp = [$logOperation[0], $logOperation[1], 'sql' => "($aliases)"];
            }
            elseif ($typeLogOp['action'] == 'sql') $logOp = $logOperation;
            else throw new GreenPigWhereException('ERROR in logical operation.', $logOperation, $this->where);
        }
        elseif ($typeLogOp['scenario'] == 3) {
            if ($typeLogOp['action'] == 'number') {
                $alias1 = $this->genAliasAndSetBind($logOperation[2]);
                $alias2 = $this->genAliasAndSetBind($logOperation[3]);
                $logOp = [$logOperation[0], $logOperation[1], 'sql' => ":$alias1 and :$alias2"];
            }
            elseif ($typeLogOp['action'] == 'date') {
                $formatDatePHP = BaseFun::getSettings($this->settings, 'date/php', false);
                $alias1 = $this->genAliasAndSetBind($logOperation[2]->format($formatDatePHP));
                $alias2 = $this->genAliasAndSetBind($logOperation[3]->format($formatDatePHP));
                $sql = $this->strToDate($alias1) .' and '. $this->strToDate($alias2);
                $logOp = [$logOperation[0], $logOperation[1], 'sql' => $sql];
            }
            elseif ($typeLogOp['action'] == 'dateStr') {
                $format1 = null;
                $format2 = null;
                if (is_array($logOperation['date1'])) {
                    $val1 = $logOperation['date1'][0];
                    $format1 = $logOperation['date1'][1];
                } else $val1 = $logOperation['date1'];
                if (is_array($logOperation['date2'])) {
                    $val2 = $logOperation['date2'][0];
                    $format2 = $logOperation['date2'][1];
                } else $val2 = $logOperation['date2'];
                $alias1 = $this->genAliasAndSetBind($val1);
                $alias2 = $this->genAliasAndSetBind($val2);
                $sql = $this->strToDate($alias1, $format1) .' and '. $this->strToDate($alias2, $format2);
                $logOp = [$logOperation[0], $logOperation[1], 'sql' => $sql];
            }
            else throw new GreenPigWhereException('ERROR in logical operation.', $logOperation, $this->where);
        }
        else throw new GreenPigWhereException('ERROR in logical operation.', $logOperation);
        if (!(isset($logOp[0]) && isset($logOp[1]) && isset($logOp['sql']))) throw new GreenPigWhereException('ERROR in logical operation.', $logOperation, $this->where);
        return " {$logOp[0]} {$logOp[1]} {$logOp['sql']} ";
    }


    private function flex($column, $value, $isNot)
    {
        $alias = $this->genAliasAndSetBind(BaseFun::repStar($value));
        $not = $isNot ? ' not ' : '';
        return ["LOWER($column)", "$not like", 'sql' => "LOWER(:$alias)"];
    }


    private function genAliasAndSetBind($val)
    {
        $alias = 'greenpig_alias_where_'. $this->numberAlias++;
        $this->binds[$alias] = $val;
        return $alias;
    }


    private function strToDate($alias, $format = null)
    {
        $sql = '';
        $nameRDBMS = BaseFun::getSettings($this->settings, 'rdbms');
        if (!$format) $format = BaseFun::getSettings($this->settings, 'date/sql', false);
        if ($nameRDBMS == 'oracle') $sql = "TO_DATE(:$alias, '$format')";
        if ($nameRDBMS == 'mysql') $sql = "STR_TO_DATE(:$alias, '$format')";
        return $sql;
    }

}
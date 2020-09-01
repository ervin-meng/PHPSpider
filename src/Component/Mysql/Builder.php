<?php

namespace PHPSpider\Component\Mysql;

use PDO;

class Builder
{
    public static $where_condition = [
        'eq'               => '=',
        'neq'              => '<>',
        'gt'               => '>',
        'egt'              => '>=',
        'lt'               => '<',
        'elt'              => '<=',
        'notlike'          => 'NOT LIKE',
        'like'             => 'LIKE',
        'in'               => 'IN',
        'exp'              => 'EXP',
        'notin'            => 'NOT IN',
        'not in'           => 'NOT IN',
        'between'          => 'BETWEEN',
        'not between'      => 'NOT BETWEEN',
        'notbetween'       => 'NOT BETWEEN',
        'exists'           => 'EXISTS',
        'notexists'        => 'NOT EXISTS',
        'not exists'       => 'NOT EXISTS',
        'null'             => 'NULL',
        'notnull'          => 'NOT NULL',
        'not null'         => 'NOT NULL',
        '> time'           => '> TIME',
        '< time'           => '< TIME',
        '>= time'          => '>= TIME',
        '<= time'          => '<= TIME',
        'between time'     => 'BETWEEN TIME',
        'not between time' => 'NOT BETWEEN TIME', 
        'notbetween time'  => 'NOT BETWEEN TIME'
    ];
        
    protected static $selectSql       = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%LOCK%%COMMENT%';
    protected static $insertSql       = '%INSERT% INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';
    protected static $insertSelectSql = 'INSERT INTO %TABLE% (%FIELD%) (%SELECT%)';
    protected static $insertAllSql    = 'INSERT INTO %TABLE% (%FIELD%) %DATA% %COMMENT%';
    protected static $updateSql       = 'UPDATE %TABLE% SET %SET% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';
    protected static $deleteSql       = 'DELETE FROM %TABLE% %USING% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    protected static $_quoteHandler;
    protected static $_prefix = '';
    protected static $_bind   = [];
    
    public static function insertAll($options)
    {
        if (!is_array(reset($options['data']))) {
            return 0;
        }
        
        $dataSet = $options['data'];

        foreach ($dataSet as $k=>&$data) {
            $value    = self::data($data, $options['field'], $options['strict'], $options['bind']);
            $values[] = 'SELECT ' . implode(',', array_values($value));
            unset($dataSet[$k]);
        }
        
        $realfields = array_keys($value);

        return str_replace(
            ['%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [self::table($options['table']), self::field($realfields), self::union($values,true,false), self::comment($options['comment'])],
            self::$insertAllSql);
    }
    
    public static function insertSelect($fields, $table, $options)
    {
        return str_replace(
            ['%TABLE%','%FIELD%','%SELECT%'],
            [self::table($table), self::field($fields), self::select($options)],
            self::$insertSelectSql);
    }
            
    public static function insert($options, $replace = false)
    {        
        if (empty($options['data'])) return 0;

        $data = self::data($options['data'], $options['field'], $options['strict'], $options['bind'], $options);
        
        $fields = array_keys($data);
        $values = array_values($data);

        return str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [$replace ? 'REPLACE' : 'INSERT', self::table($options['table']), implode(' , ', $fields), implode(' , ', $values), self::comment($options['comment'])],
            self::$insertSql);
    }
            
    public static function delete($options)
    {
        return str_replace(
            ['%TABLE%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                self::table($options['table']),
                self::using($options['using']),
                self::join($options['join'],$options),
                self::where($options['where'], $options),
                self::order($options['order'], $options),
                self::limit($options['limit']),
                self::lock($options['lock']),
                self::comment($options['comment']),
            ], self::$deleteSql);
    }

    public static function update($options)
    {
        if (empty($options['data'])) return 0;
        
        $data = self::data($options['data'], $options['field'], $options['strict'], $options['bind'], $options);

        return str_replace(
            ['%TABLE%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                self::table($options['table']),
                self::set($data),
                self::join($options['join'],$options),
                self::where($options['where'],$options),
                self::order($options['order'],$options),
                self::limit($options['limit']),
                self::lock($options['lock']),
                self::comment($options['comment']),
            ], self::$updateSql);
    }
    
    public static function select($options = [])
    {
        return str_replace(
            ['%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%', '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%'],
            [
                self::table($options['table']),
                self::distinct($options['distinct']),
                self::field($options['field'],$options),
                self::join($options['join'],$options),
                self::where($options['where'],$options),
                self::group($options['group']),
                self::having($options['having']),
                self::order($options['order'],$options),
                self::limit($options['limit']),
                self::union($options['union']),
                self::lock($options['lock']),
                self::comment($options['comment']),
                self::force($options['force']),
            ], self::$selectSql);
    }
    
    public static function distinct($distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }
    
    public static function table($tables)
    {
        $array = [];
        
        foreach ((array) $tables as $key => $val) {
            $table = self::parseTable($val);
            
            if (is_numeric($key)) {
                $array[] = $table;
            } else {
                $array[] = self::parseField($table) . ' ' . self::parseField($key);
            }
        }
        
        return implode(',', $array);
    }
                    
    public static function field($fields, $options = [])
    {  
        if ('*' == $fields || empty($fields)) {
            return '*';
        }
        
        if(is_string($fields)) {
            $fields = explode(',', $fields);
        }

        if (is_array($fields)) {
            $array = [];
            foreach ($fields as $alias => $field) {
                if (is_numeric($alias)) {
                    $array[] = self::parseField($field, $options);
                } else {
                    $array[] = self::parseField($field, $options) . ' AS ' . self::parseField($alias, $options);
                }
            }
            
            return implode(',', $array);
        }
    }
    
    public static function join($join, $options)
    {
        if (empty($join)) {
            return '';
        }
        
        $joinStr = '';
        
        foreach ($join as $item) {
            list($type, $table, $on) = $item;
            $condition = [];
            foreach ((array) $on as $val) {
                if (strpos($val,'=')) {
                    list($field1,$field2) = explode('=', $val, 2);
                    $condition[] = self::parseField($field1,$options) . '=' . self::parseField($field2,$options);
                } else {
                    $condition[] = $val;
                }
            }

            $joinStr .= ' ' . $type . ' JOIN ' . self::table($table) . ' ON ' . implode(' AND ', $condition);
        }

        return $joinStr;
    }
    
    public static function using($using, $type = 'delete')
    {
        if (empty($using)) {
            return '';
        }
        
        if ($type=='delete') {
            $using = self::table($using);
        } else if(is_array($using)) {
            $using = implode(',', $using);
        }
        
        return ' USING (' .$using. ') ';
    }
    
    public static function where(array $where, $options)
    {
        $whereStr = '';
        $binds    = $options['type'];
        
        self::bind($options['bind']);
        
        foreach ($where as $logic => $val) {
            $str = [];
            foreach ($val as $fields => $condition) {
                if (strpos($fields, '|')) { //不同字段使用相同查询条件（OR）
                    $fieldArr = explode('|',$fields);
                    $item  = [];
                    foreach ($fieldArr as $field) {
                        $item[] = self::whereItem($field,$condition,'',$options,$binds);
                    }
                    $str[] = ' ' . $logic . ' ( ' . implode(' OR ', $item) . ' )';
                } elseif (strpos($fields, '&')) { //不同字段使用相同查询条件（AND）
                    $fieldArr = explode('&', $fields);
                    $item  = [];
                    foreach ($fieldArr as $field) {
                        $item[] = self::whereItem($field,$condition,'',$options,$binds);
                    }
                    $str[] = ' ' . $logic . ' ( ' . implode(' AND ', $item) . ' )';
                } else { //对字段使用表达式查询
                    $fields = is_string($fields) ? $fields : '';
                    $str[] = ' ' . $logic . ' ' . self::whereItem($fields,$condition,$logic,$options,$binds);
                }
            }

            $whereStr .= empty($whereStr) ? substr(implode(' ', $str), strlen($logic) + 1) : implode(' ', $str);
        }

        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }
    
    public static function wherePk($pk, $data, &$options)
    {
        $table = is_array($options['table']) ? key($options['table']) : $options['table'];
        
        if (!empty($options['alias'][$table])) {
            $alias = $options['alias'][$table];
        }
        
        if (is_string($pk)) {
            $key = isset($alias) ? $alias . '.' . $pk : $pk;
            if (is_array($data)) { //根据主键查询
                $where[$key] = isset($data[$pk]) ? $data[$pk] : ['in', $data];
            } else {
                $where[$key] = strpos($data, ',') ? ['IN', $data] : $data;
            }
        } elseif (is_array($pk) && is_array($data) && !empty($data)) { //根据复合主键查询
            foreach ($pk as $key) {
                if (isset($data[$key])) {
                    $attr         = isset($alias) ? $alias . '.' . $key : $key;
                    $where[$attr] = $data[$key];
                } else {
                    throw new Exception('miss complex primary data');
                }
            }
        }

        if (!empty($where)) {
            if (isset($options['where']['AND'])) {
                $options['where']['AND'] = array_merge($options['where']['AND'], $where);
            } else {
                $options['where']['AND'] = $where;
            }
        }
        
        return;
    }

    public static function whereItem($field,$val,$rule = '',$options = [],$binds = [],$bindName = null)
    {
        if (!is_array($val)) {
            $val = ['=', $val];
        }
        
        list($op,$value) = $val;

        if (is_array($op)) { //对一个字段使用多个查询条件 ['id'=>[['gt',3],['lt',10],'or']]
            $logic = end($val);
            if (is_string($logic) && in_array($logic,['AND', 'and', 'OR', 'or'])) {
                $rule = array_pop($val);
            }
            foreach ($val as $k => $v) {
                $bindName = 'where_' . str_replace('.', '_', $field) . '_' . $k;
                $str[] = self::whereItem($field,$v,$rule,$options,$binds,$bindName);
            }
            return '( ' . implode(' ' . $rule . ' ', $str) . ' )';
        }

        if (!in_array($op,self::$where_condition)) { //检查条件操作符
            $op = strtolower($op);
            if (isset(self::$where_condition[$op])) {
                $op = self::$where_condition[$op];
            } else {
                throw new \Exception('where condition operator error:' . $op);
            }
        }

        $bindType = isset($binds[$field]) ? $binds[$field] : PDO::PARAM_STR;
        $bindName = $bindName ?: 'where_' . str_replace(['.', '-'], '_', $field);
        
        if (preg_match('/\W/', $bindName)) { //处理带非单词字符的字段名
            $bindName = md5($bindName);
        }

        if (is_scalar($value)
            && array_key_exists($field,$binds)
            && !in_array($op, ['EXP', 'NOT NULL', 'NULL', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])
            && strpos($op, 'TIME') === false) {
            if (strpos($value, ':') !== 0 || !isset(self::$_bind[substr($value, 1)])) {
                if (isset(self::$_bind[$bindName])) {
                    $bindName .= '_' . str_replace('.', '_', uniqid('', true));
                }
                self::$_bind[$bindName] = [$value,$bindType];
                $value = ':' . $bindName;
            }
        }

        $key = $field ? self::parseField($field,$options) : '';
        $whereStr = '';
        
        if ('EXP' == $op) { //表达式查询
            $whereStr .= '( ' . $key . ' ' . $value . ' )';
        } 
        elseif (in_array($op, ['=', '<>', '>', '>=', '<', '<='])) {//比较运算
            $whereStr .= $key . ' ' . $op . ' ' . self::parseValue($value);
        } elseif (in_array($op,['LIKE','NOT LIKE'])) { //模糊匹配
            if (is_array($value)) {
                foreach ($value as $item) {
                    $array[] = $key . ' ' . $op . ' ' . self::parseValue($item);
                }
                $logic = isset($val[2]) ? $val[2] : 'AND';
                $whereStr .= '(' . implode($array, ' ' . strtoupper($logic) . ' ') . ')';
            } else {
                $whereStr .= $key . ' ' . $op . ' ' . self::parseValue($value);
            }
        } elseif (in_array($op, ['NOT NULL', 'NULL'])) { //NULL 查询
            $whereStr .= $key . ' IS ' . $op;
        } elseif (in_array($op, ['NOT IN', 'IN'])) { //IN 查询
            $value = is_array($value) ? $value : explode(',', $value);
            if (array_key_exists($field, $binds)) {
                $bind  = [];
                $array = [];
                foreach ($value as $k => $v) {
                    if (isset(self::$_bind[$bindName . '_in_' . $k])) {
                        $bindKey = $bindName . '_in_' . uniqid() . '_' . $k;
                    } else {
                        $bindKey = $bindName . '_in_' . $k;
                    }
                    self::$_bind[$bindKey] = [$v, $bindType];
                    $array[]        = ':' . $bindKey;
                }
                $zone = implode(',', $array);
            } else {
                $zone = implode(',',self::parseValue($value));
            }
            $whereStr .= $key . ' ' . $op . ' (' . (empty($zone) ? "''" : $zone) . ')';
        } elseif (in_array($op, ['NOT BETWEEN', 'BETWEEN'])) { //BETWEEN 查询
            $data = is_array($value) ? $value : explode(',', $value);
            if (array_key_exists($field, $binds)) {
                if (isset(self::$_bind[$bindName . '_between_1'])) {
                    $bindKey1 = $bindName . '_between_1' . uniqid();
                    $bindKey2 = $bindName . '_between_2' . uniqid();
                } else {
                    $bindKey1 = $bindName . '_between_1';
                    $bindKey2 = $bindName . '_between_2';
                }
                self::$_bind[$bindKey1] = [$data[0], $bindType];
                self::$_bind[$bindKey2] = [$data[1], $bindType];
                $between = ':' . $bindKey1 . ' AND :' . $bindKey2;
            } else {
                $between = self::parseValue($data[0]) . ' AND ' . self::parseValue($data[1]);
            }
            
            $whereStr .= $key . ' ' . $op . ' ' . $between;
        } elseif (in_array($op, ['NOT EXISTS', 'EXISTS'])) {
            $whereStr .= $op . ' (' . $value . ')';
        } elseif (in_array($op, ['< TIME', '> TIME', '<= TIME', '>= TIME'])) {
            $whereStr .= $key . ' ' . substr($op, 0, 2) . ' ' . self::dateTime($value, $field, $options, $bindName, $bindType);
        } elseif (in_array($op, ['BETWEEN TIME', 'NOT BETWEEN TIME'])) {
            if (is_string($value)) {
                $value = explode(',', $value);
            }
            $whereStr .= $key . ' ' . substr($op, 0, -4) . self::dateTime($value[0], $field, $options, $bindName . '_between_1', $bindType) . ' AND ' . self::dateTime($value[1], $field, $options, $bindName . '_between_2', $bindType);
        }

        return $whereStr;
    }
    
    public static function dateTime($value, $key, $options = [], $bindName = null, $bindType = null)
    {
        if (strpos($key, '.')) {
            list($table, $key) = explode('.', $key);
            if (isset($options['alias']) && $pos = array_search($table, $options['alias'])) {
                $table = $pos;
            }
        } else {
            $table = $options['table'];
        }
        
        //$type = $this->query->getTableInfo($table, 'type');
        
        if (isset($type[$key])) {
            $info = $type[$key];
        }
        
        if (isset($info)) {
            if (is_string($value)) {
                $value = strtotime($value) ?: $value;
            }

            if (preg_match('/(datetime|timestamp)/is', $info)) {
                $value = date('Y-m-d H:i:s', $value);
            } elseif (preg_match('/(date)/is', $info)) {
                $value = date('Y-m-d', $value);
            }
        }
        $bindName = $bindName ?: $key;
        self::$_bind[$bindName] = [$value, $bindType];
        
        return ':' . $bindName;
    }
    
    public static function group($group)
    {
        return !empty($group) ? ' GROUP BY ' . $group : '';
    }
    
    public static function having($having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }
    
    public static function order($order, $options = [])
    {
        if (is_array($order)) {
            $array = [];
            foreach ($order as $key => $val) {
                if (is_numeric($key)) {
                    if ( $val == '[rand]') {
                        $array[] = self::rand();
                    } elseif (false === strpos($val, '(')) {
                        $array[] = self::parseField($val,$options);
                    } else {
                        $array[] = $val;
                    }
                } else {
                    $sort = in_array(strtolower(trim($val)), ['asc', 'desc']) ? ' ' . $val : '';
                    $array[] = self::parseField($key, $options) . ' ' . $sort;
                }
            }
            $order = implode(',', $array);
        }
        
        return !empty($order) ? ' ORDER BY ' . $order : '';
    }
    
    public static function rand()
    {
        return 'rand()';
    }
    
    public static function limit($limit)
    {
        return (!empty($limit) && false === strpos($limit, '(')) ? ' LIMIT ' . $limit . ' ' : '';
    }

    public static function union(array $union, $all = false, $parse = true)
    {
        if (isset($union['type'])) {
            $all = $union['type'];
            unset($union['type']);
        }
        
        if (empty($union)) {
            return '';
        }
                
        $type = $all ? ' UNION ALL ' : ' UNION ';
        
        $sql = implode($type,$union);
        
        if($parse) {
            $sql = self::parseTable($sql);
        }

        return $sql;
    }
                               
    public static function lock($lock = false)
    {
        return $lock ? ' FOR UPDATE ' : '';
    }
    
    public static function comment($comment)
    {
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }
        
    public static function force($index)
    {
        if (empty($index)) {
            return '';
        }

        if (is_array($index)) {
            $index = join(",", $index);
        }

        return sprintf(" FORCE INDEX ( %s ) ", $index);
    }
    
    public static function savepoint($pointname)
    {
        return 'SAVEPOINT ' . $pointname;
    }
    
    public static function savepointRollBack($pointname)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $pointname;
    }
    
    public static function bind($data = false)
    {
        $bind = self::$_bind;
        
        if (is_bool($data) && !$data) {
           self::$_bind = [];
        } else if (is_array($data)) {
            $bind = self::$_bind = array_merge(self::$_bind, $data);
        }
        
        return $bind;
    }
    
    public static function data(array $data, $fields, $strict = false, $bind = false, $alias = [])
    {
        if (empty($data)) {
            return [];
        }
        
        if($bind !== false) {
            $fields = array_keys($fields);
            $types  = array_values($fields);
            self::bind($bind);
        }

        foreach ($data as $field => $val) {
            if (false === strpos($field, '.') && !in_array($field,$fields,true)) {
                if($strict) {
                    throw new \Exception('fields not exists:[' . $field . ']');
                }
                continue;
            }
            unset($data[$field]);
            $item = !empty($alias) ? self::parseField($field,$alias) : $field;
            if(isset($val[0]) && 'exp' == $val[0]) {
                $data[$item] = $val[1];
            } else if(is_null($val)) {
                $data[$item] = 'NULL';
            } else if(is_scalar($val)) {
                if ($bind !== false) {
                    $bindkey = substr($val, 1);
                    if (0 === strpos($val,':') && isset(self::$_bind[$bindkey])) {
                        $data[$item] = $val;
                    } else {
                        $bindKey = '__data__' . str_replace('.', '_', $field);
                        $bindType = isset($types[$field])? $param[$field] : PDO::PARAM_STR;
                        self::$_bind[$bindKey] = [$val,$bindType];
                        $data[$item] = ':'.$bindKey;
                    }
                } else {
                    $data[$item] = self::parseValue($val);
                }
            } else if(is_object($val) && method_exists($val, '__toString')) {
                $data[$item] = $val->__toString();
            }
        }

        return $data;
    }
    
    public static function set($data)
    {
        foreach ($data as $key => $val) {
            $set[] = $key . '=' . $val;
        }
        
        return implode(',', $set);
    }
    
    public static function parseTable($table)
    {
        if (false !== strpos($table, '__')) {
            $table = preg_replace_callback("/__([A-Za-z0-9_-]+)__/sU", 
            function ($match){
                return self::$_prefix . strtolower($match[1]);
            }
            , $table);
        }
        
        return $table;
    }

    public static function parseField($key, $alias = [])
    {
        $key = trim($key);
        
        if (strpos($key, '$.') && false === strpos($key, '(')) { // JSON字段支持
            list($field,$name) = explode('$.', $key);
            $key = 'json_extract(' . $field . ', \'$.' . $name . '\')';
        } elseif (strpos($key, '.') && !preg_match('/[,\'\"\(\)`\s]/', $key)) {//解析查询中带数据表字段
            list($table,$key) = explode('.', $key, 2);
            if(array_key_exists($table,$alias['table'])) {//table别名
                $table = self::parseTable($alias['table'][$table]);
            } else if(array_key_exists($key,$alias['join_table'])) {//jointable 别名
                $table = self::parseTable($alias['join_table'][$table]);
            }
        }
        
        if (!preg_match('/[,\'\"\*\(\)`.\s]/', $key)) {
            $key = '`' . $key . '`';
        }
        
        if (isset($table)) {
            $key = '`' . $table . '`.' . $key;
        }
        
        return $key;
    }
            
    public static function parseValue($value)
    {
        if (is_string($value)) {
            if (strpos($value, ':') !== 0 || !isset(self::$_bind[substr($value, 1)])) {
                $value = call_user_func(self::$_quoteHandler,$value);
            }
        } elseif (is_array($value)) {
            $value = array_map([self,'parseValue'], $value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $value = 'null';
        }
        
        return $value;
    }
        
    public static function fieldType2PdoParamType($type)
    {
        if (preg_match('/(int|double|float|decimal|real|numeric|serial|bit)/is', $type)) {
            $bind = PDO::PARAM_INT;
        } elseif (preg_match('/bool/is', $type)) {
            $bind = PDO::PARAM_BOOL;
        } else {
            $bind = PDO::PARAM_STR;
        }
        
        return $bind;
    }
            
    public static function registerQuoteHandler($handler)
    {
        if(is_callable($handler)) {
            self::$_quoteHandler = $handler;
        } else {
            throw new \Exception('quote handler is not callable');
        }
    }
    
    public static function setPrefix($prefix)
    {
        self::$_prefix = $prefix;
    }
}

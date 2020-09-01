<?php

namespace PHPSpider\Component\Mysql;

use PDO;
use PHPSpider\Utils\Debug;
use PHPSPider\Utils\Logger;

class Querier
{
    protected $_set_options  = ['fetchSql','fetchPdo','fetchExplain','strict'];
    protected $_sql_options  = ['distinct','field','table','join','where','group','having','order','limit','union','lock','comment','force'];
    protected $_data_options = ['data','type','bind'];
    protected $_where_key = ['exists','notexists']; 
    protected $_where_logic = ['and','or','xor'];
    protected $_where_condition = ['like','notlike','null','notnull','in','notin','between','notbetween','exp'];
    protected $_aggregate_funcs = ['avg','count','max','min','sum'];
    protected $_config;
    protected $_options;
    protected $_connector;
    protected $_PDOStatement = null;
    protected $_rows = 0;  //selected rows or affected rows

    public $querySql; //最近一次执行的query的SQL
    public $queryBind; //最近一次执行query的绑定参数
    public $transTimes = 0;
    
    protected static $_info  = []; //数据表信息
    protected static $_event = []; //钩子
               
    public function insertSelect($table, $fields)
    {
        $options = $this->formatOptions();
        $options['field'] = $this->getField();
        
        $sql  = Builder::insertSelect($fields,$table,$options);
        $bind = Builder::bind();
        
        return $options['fetchSql'] ? $this->_sql($sql,$bind) : $this->_query($sql,$bind);
    }
    
    public function insertGetId(array $data = [], $replace = false)
    {
        return $this->insert($data,$replace,true);
    }
    
    public function insert(array $data = [], $replace = false, $getLastInsID = false)
    {
        $options = $this->data($data)->formatOptions();

        $options['field']  = $this->getField('param');
        $options['strict'] = $this->getStrict();
        
        $sql  = Builder::insert($options,$replace);
        $bind = Builder::bind();

        if ($options['fetchSql']) {
            return $this->_sql($sql,$bind);
        }
        
        $pk   = $this->getPk();
        $result = $this->_query($sql,$bind);
        
        if ($result) {
            if ($getLastInsID && $lastInsId = $this->getLastInsID()) {
                if (is_string($pk)) {
                    $data[$pk] = $lastInsId;
                }
                $result = $lastInsId;
            }
            $options['data'] = $data;
            $this->trigger('after_insert', $options);
        }
        
        return $result;
    }
    
    public function insertAll(array $dataSet = [])
    {
        $options = $this->data($dataSet)->formatOptions();
        
        $options['field'] = $this->getField('fields');
        $options['strict'] = $this->getStrict();
        
        $sql  = Builder::insertAll($options);
        $bind = Builder::bind();

        return $options['fetchSql'] ? $this->_sql($sql,$bind) : $this->_query($sql,$bind);
    }
    
    public function delete($data = null)
    {
        $options = $this->formatOptions();

        if (!is_null($data) && true !== $data) {
            $pk = $this->getPk();
            Builder::wherePk($pk,$data,$options); //AR模式分析主键条件
        }
        //如果条件为空 不进行删除操作 除非设置 1=1
        if (true !== $data && empty($options['where'])) {
            throw new \Exception('delete without condition');
        }
        
        $sql  = Builder::delete($options);
        $bind = Builder::bind();
        
        if ($options['fetchSql']) {
            return $this->_sql($sql,$bind);
        }
        
        $result = $this->_query($sql,$bind);
        
        if ($result) {
            $this->trigger('after_delete', $options);
        }
        
        return $result;
    }
    
    public function update($field=[], $value = '')
    {
        $options = $this->data($field,$value)->formatOptions();
        $pk      = $this->getPk();
        
        if (empty($options['where'])) {
            //如果存在主键数据 则自动作为更新条件
            if (is_string($pk) && isset($options['data'][$pk])) {
                $where[$pk] = $options['data'][$pk];
                unset($options['data'][$pk]);
            } elseif (is_array($pk)) { //增加复合主键支持
                foreach ($pk as $field) {
                    if (isset($options['data'][$field])) {
                        $where[$field] = $options['data'][$field];
                    } else {
                        throw new Exception('miss complex primary data'); //如果缺少复合主键数据则不执行
                    }
                    unset($options['data'][$field]);
                }
            }
            
            if (!isset($where)) {
                throw new \Exception('miss update condition'); //如果没有任何更新条件则不执行
            } else {
                $options['where']['AND'] = $where;
            }
        }

        $options['field']  = $this->getField('param');
        $options['strict'] = $this->getStrict();
        
        $sql  = Builder::update($options);
        $bind = Builder::bind();
        
        if ($options['fetchSql']) {
            return $this->_sql($sql,$bind);
        } 

        $result = '' == $sql ? 0 : $this->_query($sql,$bind);

        if ($result) {
            if (is_string($pk) && isset($where[$pk])) {
                $options['data'][$pk] = $where[$pk];
            }
            $this->trigger('after_update', $options);
        }
        
        return $result;
    }
        
    public function increase($field, $step = 1, $lazyTime = 0)
    {
        return $this->update($field, ['exp', $field . '+' . $step]);
    }
    
    public function decrease($field, $step = 1, $lazyTime = 0)
    {
        return $this->update($field, ['exp', $field . '-' . $step]);
    }
    
    public function select($data = null,$sub=false,$master=false)
    {
        $options = $this->formatOptions();
        
        if (!is_null($data)) { //主键条件分析
            Builder::wherePk($data,$options);
        }

        $sql  = Builder::select($options);
        $bind = Builder::bind();

        if ($options['fetchExplain'] || $options['fetchSql'] || $sub) {
            $sql = $this->_sql($sql,$bind,false);
            if($options['fetchExplain']) {
                return $this->explain($sql);
            } else if($options['fetchSql']) {
                return $sql;
            }
            
            return '( ' . $sql . ' )';
        }

        $options['data'] = $data;

        if ($resultSet = $this->trigger('before_select', $options)) { //返回true 替换query查询 返回false继续执行
        } else {
            $resultSet = $this->_query($sql,$bind,true,$master,$options['fetchPdo']);
        }
                
        return $resultSet;
    }
        
    public function find($data = null, $master = false)
    {
        $options = $this->limit(1)->formatOptions();
        
        if (!is_null($data)) {
            Builder::wherePk($data, $options); //AR模式分析主键条件
        } 

        $sql  = Builder::select($options);
        $bind = Builder::bind();
        
        if ($options['fetchSql']) {
            return $this->_sql($sql,$bind);
        }
        
        $pk = $this->getPk();

        if (is_string($pk) && !is_array($data)) {
            $item[$pk] = $data;
            $data = $item;
        }
        
        $options['data'] = $data;

        if ($result = $this->trigger('before_find',$options)) {
        } else {
            $result = $this->_query($sql,$bind,true,$master,$options['fetchPdo']);
            if (!($result instanceof \PDOStatement) && isset($result[0])) {
                $result = $result[0];
            }
        }

        return $result;
    }
    
    public function value($field, $default = null, $master = false)
    {
        if (isset($this->_options['field'])) unset($this->_options['field']);

        $options = $this->fetchPdo()->field($field)->limit(1)->formatOptions();

        $sql  = Builder::select($options);
        $bind = Builder::bind();

        if ($options['fetchSql']) {
            return $this->_sql($sql,$bind);
        }

        $pdo    = $this->_query($sql,$bind,true,$master,$options['fetchPdo']);
        $result = $pdo->fetchColumn();

        return false !== $result ? $result : $default;
    }
    
    public function column($field, $key = '')
    {
        if (isset($this->_options['field'])) unset($this->_options['field']);

        if ($key && '*' != $field) {
            $field = $key . ',' . $field;
        }

        $options = $this->fetchPdo()->field($field)->formatOptions();

        $sql  = Builder::select($options);
        $bind = Builder::bind();

        if ($options['fetchSql']) {
            return $this->_sql($sql,$bind);
        }

        $pdo = $this->_query($sql,$bind,true,$options['master'],$options['fetchPdo']);
        $result = [];

        if (1 == $pdo->columnCount()) {
            $result = $pdo->fetchAll(PDO::FETCH_COLUMN);
        } elseif($resultSet = $pdo->fetchAll(PDO::FETCH_ASSOC)) {
            $fields = array_keys($resultSet[0]);
            $count  = count($fields);
            $key1   = array_shift($fields);
            $key2   = $fields ? array_shift($fields) : '';
            $key    = $key ?: $key1;

            if (strpos($key, '.')) {
                list($alias, $key) = explode('.', $key);
            }

            foreach ($resultSet as $val) {
                if ($count > 2) {//大于两列返回数组
                    $result[$val[$key]] = $val;
                } elseif (2 == $count) {//第二列
                    $result[$val[$key]] = $val[$key2];
                } elseif (1 == $count) { //第一列
                    $result[$val[$key]] = $val[$key1];
                }
            }
        }
                    
        return $result;
    }
    
    protected function _query($sql, $bind = [], $read = false, $master = null, $pdo = false)
    {
        if (is_null($master)) {
            $master = !$read;
        }
        
        $link = $this->_connector->getLink($master);
        
        if (!$link) {
            return false;
        }

        $this->querySql  = $sql;
        $this->queryBind = $bind;
        $this->_options  = [];
        
        if($read) {
            $this->free();
        } else if(is_object($this->_PDOStatement) && $this->_PDOStatement->queryString != $sql) {
            $this->free();
        }

        try {
            $this->debug(true);
            if (empty($this->_PDOStatement)) {
                $this->_PDOStatement = $link->prepare($sql);
            }
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']); //是否为存储过程调用
            if ($procedure) {
                $this->_bindParam($bind);
            } else {
                $this->_bindValue($bind);
            }
            
            $this->_PDOStatement->execute();
            
            $this->debug(false);

            return $this->getResult($read,$pdo,$procedure);
        } catch (\PDOException $e) {
            if ($this->_config['break_reconnect'] && $this->_connector->isBreak($e)) {
                $this->_connector->close();
                return $this->_query($sql,$bind,$read,$master,$pdo);
            }
            
            throw $e;
        }
    }
    
    protected function _bindValue(array $bind = [])
    {
        foreach ($bind as $key => $val) {
            $param = is_numeric($key) ? $key + 1 : ':' . $key;
            if (is_array($val)) {
                if (PDO::PARAM_INT == $val[1] && '' === $val[0]) {
                    $val[0] = 0;
                }
                $result = $this->_PDOStatement->bindValue($param, $val[0], $val[1]);
            } else {
                $result = $this->_PDOStatement->bindValue($param, $val);
            }
            
            if (!$result) {
                throw new \Exception("Error occurred  when binding parameters '{$param}'");
            }
        }
    }
    
    protected function _bindParam($bind)
    {
        foreach ($bind as $key => $val) {
            $param = is_numeric($key) ? $key + 1 : ':' . $key;
            if (is_array($val)) {
                array_unshift($val, $param);
                $result = call_user_func_array([$this->_PDOStatement, 'bindParam'], $val);
            } else {
                $result = $this->_PDOStatement->bindValue($param, $val);
            }
            if (!$result) {
                $param = array_shift($val);
                throw new Exception("Error occurred  when binding parameters '{$param}'");
            }
        }
    }
    
    protected function _procedure()
    {
        $item = [];
        
        do {
            $result = $this->_PDOStatement->fetchAll($this->_config['result_type']);
            if ($result) {
                $item[] = $result;
            }
            
        } while ($this->_PDOStatement->nextRowset());
                
        return $item;
    }
    
    protected function _sql($sql, array $bind = [], $master = true)
    {
        $link = $this->_connector->getLink($master);
        $recordSql = (isset($this->_options['fetchSql']) && $this->_options['fetchSql'] == 2)?1:0;

        $this->_options = [];

        foreach ($bind as $key => $val) {
            $value = is_array($val) ? $val[0] : $val;
            $type  = is_array($val) ? $val[1] : PDO::PARAM_STR;
            if (PDO::PARAM_STR == $type) {
                $value = $link->quote($value);
            } elseif (PDO::PARAM_INT == $type) {
                $value = (float) $value;
            }

            //判断占位符
            $sql = is_numeric($key) ?
            substr_replace($sql, $value, strpos($sql, '?'), 1) :
            str_replace(
                [':' . $key . ')', ':' . $key . ',', ':' . $key . ' '],
                [$value . ')', $value . ',', $value . ' '],
                $sql . ' ');
        }

        $sql = rtrim($sql);
        
        if ($recordSql) {
            $this->log($sql);
        }

        return $sql;
    }

    /**
     * $table 
     * @string [db.]table|__table__[ alias]
     * @array [string,string1]
     * @array [alias=>string,alias1=>string1]
     */
    public function table($table)
    {
        if (is_string($table) && strpos($table,')') ===false) {
            $table = explode(',', $table);
        }

        if (is_array($table)) {
            foreach ($table as $key=>$item) {
                if (is_numeric($key)) { //索引数组 item是表名
                    $this->_options['table'][] = $item;
                } else { //关联数组 key是表名 item是别名
                    $this->_options['table'][$item] = $key;
                }
            }
            
        }
        
        return $this;
    }
    
    public function partition($data, $field, $rule = [])
    {
        $this->options['table'] = $this->getPartitionTableName($data, $field, $rule);
        return $this;
    }
    
    /**
     * $field  
     * @bool True 获取全部字段
     * @string ([table.]|[alias.])field|field [ alias]
     * @array [string,string1]
     * @array [alias=>string]
     */
    public function field($field, $except = false, $tableName = '', $prefix = '')
    {
        if (empty($field)) return $this;
        
        if (true === $field) {
            $fields = $this->getTableInfo($tableName,'fields');
            $field  = $fields ?: ['*'];
        } else if (is_string($field)) { //字符串处理成数组
            $field = array_map('trim', explode(',', $field));
        }

        if ($except && $field!==true) { //字段排除
            $fields = $this->getTableInfo($tableName,'fields');
            $field  = $fields ? array_diff($fields, $field) : $field;
        }

        if ($tableName || $prefix) { //添加统一的前缀
            $prefix = $prefix ?: $tableName;
            $fields = [];
            foreach ($field as $key => $val) {
                if (is_numeric($key)) {
                    $fields[] = $prefix . '.' . $val;
                } else {
                    $fields[$val] = $prefix . '.' . $key;
                }
            }
            $field = $fields;
        }

        $field = !isset($this->_options['field'])?$field:array_merge($this->_options['field'],$field);
        $this->_options['field'] = array_unique($field);

        return $this;
    }

    /*
     * $join 
     * @string table [alias]
     * @array  [table,condition]
     * innerJoin(table,condition) 方式1
     * innerJoin([table=>condition]) 方式2 其他方式都报错
     * conditon 是数组时 默认用and连接 如果想使用or方式请用字符串 不支持using用法
     */
    protected function _join($type, $table, $condition = null)
    {
        if (is_array($table)) {
            foreach ($table as $key => $value) {
                if(!is_numeric($key)) {
                    $this->_join($type,$key,$value);
                } else {
                    throw new \Exception($type."Join method parameter is malformed");
                }
            }
        } else {
            $table = trim($table);
            if(strrpos(' ', $table) && strrpos(')', $table)===false) {
                list($table,$alias) = explode(' ', $tableName);
                $this->_options['join_table'][$alias] = $table;
            } else {
                $this->_options['join_table'][] = $table;
            }
            
            $this->_options['join'][] = [strtoupper($type),$table, $condition];
        }
        
        return $this;
    }

    /**
     * field,op,condition id,=,5
     * expression id=5
     * array id=>5
     * array 【id=5,age=1】
     */
    protected function _where($op, $arguments)
    {
        if(in_array($op,$this->_where_key)) //关键字
        {
            $logic = $arguments[1];
            $condition = $arguments[0];
            $this->_options['where'][strtoupper($logic)][] = ['exists', $condition];
            return $this;
        }
        
        if(in_array($op,$this->_where_condition)) //条件运算
        {
            $logic = array_pop($arguments);
            $field = array_shift($arguments);
            $op = Builder::$where_condition[$op];
            $condition = empty($arguments)?null:current($arguments);
            $param = [];
        }
        else if(in_array($op,$this->_where_logic)) //逻辑运算
        { 
            $logic = $op;
            $field = array_shift($arguments);
            $op = isset($arguments[0])?$arguments[0]:null;
            $condition = isset($arguments[1])?$arguments[1]:null;
            $param = $arguments;
        }

        $logic = strtoupper($logic);
              
        if (is_string($field) && preg_match('/[,=\>\<\'\"\(\s]/',$field)) //表达式查询
        {
            $where[] = ['exp', $field];
            
            if (is_array($op)) 
            {
                $this->bind($op);
            }
        }
        elseif (is_array($op)) 
        {
            $where[$field] = $param;
        } 
        elseif (is_null($op) && is_null($condition)) //批量查询
        {
            if (is_array($field))
            {
                $where = $field;

                foreach ($where as $k => $val) 
                {
                    if(is_numeric($k))
                    {
                        throw new \Exception("where method parameter is malformed");
                    }
                    
                    $this->_options['multi'][$logic][$k][] = $val;
                }
            } 
            elseif ($field && is_string($field))
            {
                $this->_options['multi'][$logic][$field][] = $where[$field] = ['null', ''];
            }
        } 
        elseif (in_array(strtolower($op), ['null', 'notnull', 'not null'])) //null查询
        {
            $this->_options['multi'][$logic][$field][] = $where[$field] = [$op, ''];
        } 
        elseif (is_null($condition)) //相等查询
        {   
            $where[$field] = ['eq', $op];
            
            if ('AND' != $logic) 
            {
                $this->_options['multi'][$logic][$field][] = $where[$field];
            }
        } 
        else 
        {
            $where[$field] = [$op, $condition, isset($param[2]) ? $param[2] : null];
            
            if ('exp' == strtolower($op) && isset($param[2]) && is_array($param[2]))
            {
                $this->bind($param[2]);
            }
            
            $this->_options['multi'][$logic][$field][] = $where[$field];
        }
        
        if (!empty($where)) 
        {
            if (!isset($this->_options['where'][$logic])) 
            {
                $this->_options['where'][$logic] = [];
            }
            
            if (is_string($field) && $this->checkMultiField($field,$logic)) 
            {
                $where[$field] = $this->_options['multi'][$logic][$field];
            } 
            elseif (is_array($field)) 
            {
                foreach ($field as $key => $val) 
                {
                    if ($this->checkMultiField($key,$logic)) 
                    {
                        $where[$key] = $this->_options['multi'][$logic][$key];
                    }
                }
            }
            
            $this->_options['where'][$logic] = array_merge($this->_options['where'][$logic], $where);            
        }

       return $this;
    }
      
    /*
     * order('field','asc')
     * order('field asc')
     * order(['field'=>'asc','field asc'])
     */
    public function order($field, $order = null)
    {
        if (!empty($field)) 
        {
            if (!isset($this->_options['order'])) 
            {
                $this->_options['order'] = [];
            }
            
            if (is_string($field) && !empty($order)) 
            {
                $field = [$field => $order];
            } 

            if (is_array($field)) 
            {
                $this->_options['order'] = array_merge($this->_options['order'], $field);
            } 
            else 
            {
                $this->_options['order'][] = $field;
            }
        }
        
        return $this;
    }
    
    public function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) 
        {
            list($offset, $length) = explode(',', $offset);
        }
        
        if($length)
        {
            $this->_options['limit'] = intval($offset) .','.intval($length);
        }
        else{
            $this->_options['limit'] = intval($offset);
        }
        
        return $this;
    }
    
    public function union($union, $all = false)
    {
        $this->_options['union']['type'] = $all;

        if (is_array($union)) 
        {
            $this->_options['union'] = array_merge($this->_options['union'], $union);
        } 
        else 
        {
            $this->_options['union'][] = $union;
        }
        
        return $this;
    }
                    
    public function page($page, $listRows = null)
    {
        if (is_null($listRows) && strpos($page, ',')) 
        {
            list($page, $listRows) = explode(',', $page);
        }
        
        $page = max([1,$page]);
        $listRows = $listRows > 0 ? $listRows : 20;
        $offset = $listRows * ($page - 1);
        $this->limit($offset,$listRows);
        
        return $this;
    }
        
    public function data($field, $value = null)
    {
        if (is_array($field)) {
            $this->_options['data'] = isset($this->_options['data']) ? array_merge($this->_options['data'], $field) : $field;
        } else {
            $this->_options['data'][$field] = $value;
        }
        
        $this->_options['type'] = $this->getFieldsParam();
        
        return $this;
    }

    public function bind($key, $value = false, $type = PDO::PARAM_STR)
    {
        if(!isset($this->_options['bind']))
        {
            $this->_options['bind'] = [];
        }
        
        if (is_array($key)) 
        {
            $this->_options['bind'] = array_merge($this->_options['bind'], $key);
        } 
        else {
            $this->_options['bind'][$key] = [$value, $type];
        }
        
        return $this;
    }
            
    public function getTable($name='')
    {
        $table = '';
        
        if($name)
        {
            $table = Builder::parseTable($name,$this->_config['prefix']);
            
            if($name == $table)
            {
                $table = $this->_config['prefix'].$name;
            }
            
        }
        else if (!empty($this->_options['table'])) 
        {
            if(is_array($this->_options['table'])){
                
                $table = key($this->_options['table']);
                
                if(is_numeric($table))
                {
                    $table = current($this->_options['table']);
                }
            }
            else{
                $table = $this->_options['table'];
            }
            
            $table = Builder::parseTable($table,$this->_config['prefix']);
        }
        
        return $table;
    }
    
    public function getPartitionTableName($data,$field,$rule=[])
    {
        if ($field && isset($data[$field]))
        {
            $value = $data[$field];
            $type  = $rule['type'];
            
            switch ($type) 
            {
                case 'id': //按照id范围分表
                    $seq  = floor($value / $rule['expr']) + 1;
                    break;
                case 'year': //按照年份分表
                    if (!is_numeric($value)) 
                    {
                        $value = strtotime($value);
                    }
                    $seq = date('Y', $value) - $rule['expr'] + 1;
                    break;
                case 'mod': //按照id的模数分表
                    $seq = ($value % $rule['num']) + 1;
                    break;
                case 'md5': //按照md5的序列分表
                    $seq = (ord(substr(md5($value), 0, 1)) % $rule['num']) + 1;
                    break;
                default:
                    if (function_exists($type)) //支持指定函数哈希
                    {
                        $seq = (ord(substr($type($value), 0, 1)) % $rule['num']) + 1;
                    } 
                    else  //按照字段的首字母的值分表
                    {
                        $seq = (ord($value{0}) % $rule['num']) + 1;
                    }
            }
            
            return $this->getTable() . '_' . $seq;
        } 
        else //当设置的分表字段不在查询条件或者数据中 进行联合查询，必须设定 partition['num']
        {
            $tableName = [];
            for ($i = 0; $i < $rule['num']; $i++) 
            {
                $tableName[] = 'SELECT * FROM ' . $this->getTable() . '_' . ($i + 1);
            }

            $tableName = '( ' . implode(" UNION ", $tableName) . ') AS partition';
            
            return $tableName;
        }
    }
    
    public function getField($open=false)
    {
        $field = $this->_options['field'];

        if(empty($field))
        {
            $field = '*';
        }
        else if(is_array($field) && trim($field[0]) == '*')
        {
            $field = '*';
        }
        else if(is_string($field))
        {
            $field = trim($field);
        }
        
        if($open)
        {
            if($field=='*')
            {
                $field = $this->getTableInfo('',$open);
            }
            else if($open != 'fields')
            {
                $field = is_string($field)?explode(',',$field):$field;
                $field = array_intersect_key(array_flip($field),$this->getTableInfo('',$open));
            }   
        }

        return $field;
    }

    public function getStrict()
    {
        if(isset($this->_options['strict']))
        {
            return $this->_options['strict'];
        }
        else
        {
            return $this->_config['fields_strict'];
        }
    }
        
    public function getTableInfo($tableName = '', $fetch = '')
    {
        if (!$tableName) 
        {
            $tableName = $this->getTable();
        }

        if (strpos($tableName, ')') || strpos($tableName, ',')) //子查询或多表返回空
        {
            return [];
        }

        list($tableName) = explode(' ', $tableName);
        
        if (!strpos($tableName, '.')) 
        {
            $schema = $this->_config['dbname'] . '.' . $tableName;
        } 
        else {
            $schema = $tableName;
        }

        if (!isset(self::$_info[$schema])) 
        {            
            $info = $this->showColumns($tableName);
            $param = $type = $pk = [];

            foreach ($info as $field => $val) 
            {
                self::$_info[$schema]['type'][$field] = $val['type'];
                self::$_info[$schema]['param'][$field] = Builder::fieldType2PdoParamType($val['type']);
                
                if (!empty($val['primary'])) 
                {
                    $pk[] = $field;
                }
            }
            
            if (!empty($pk))//设置主键
            {
                $pk = count($pk) > 1 ? $pk : $pk[0];
            } 
            
            self::$_info[$schema]['fields'] = array_keys($info);
            self::$_info[$schema]['pk'] = $pk;
        }
        
        return $fetch ? self::$_info[$schema][$fetch] : self::$_info[$schema];
    }
    
    public function getPk($table = '')
    {
        return $this->getTableInfo($table,'pk');
    }
    
    public function getFields($table='')
    {
        return $this->getTableInfo($table,'fields');
    }

    public function getFieldsType($table='')
    {
        return $this->getTableInfo($table,'type');
    }

    public function getFieldsParam($table='')
    {
        return $this->getTableInfo($table,'param');
    }
                                
    public function getResult($read=true,$pdo,$procedure)
    {
        if($read)
        {
            if ($pdo) 
            {
                return $this->_PDOStatement;
            }
            
            if($procedure)
            {
                $result = $this->_procedure();
            }
            else{
                $result = $this->_PDOStatement->fetchAll($this->_config['result_type']);
            }
            
            $this->_rows = count($result);
        }
        else{
            $result = $this->_rows  = $this->_PDOStatement->rowCount();
        }
        
        return $result;
    }
    
    public function getLastInsID()
    {
        return $this->_connector->getLink(true)->lastInsertId();
    }
    
    public function getLastSql()
    {
        return $this->_sql($this->querySql,$this->queryBind);
    }

    public function startTrans()
    {
        ++$this->transTimes;
         
        $link = $this->_connector->getLink(True);
        
        if (1 == $this->transTimes) 
        {
            $link->beginTransaction();
        } 
        elseif ($this->transTimes > 1) {
            $sql = Builder::savepoint('trans' . $this->transTimes);
            $link->exec($sql);
        }
    }

    public function commit()
    {
        $link = $this->_connector->getLink(True);

        if (1 == $this->transTimes)
        {
            $link->commit();
        }
        
        $this->transTimes = max(0, $this->transTimes - 1);
    }
    
    public function rollback()
    {
        $link = $this->_connector->getLink(True);

        if (1 == $this->transTimes) 
        {
            $link->rollBack();
        } 
        elseif ($this->transTimes > 1) 
        {
            $sql = Builder::SavepointRollBack('trans' . $this->transTimes);
            $link->exec($sql);
        }

        $this->transTimes = max(0, $this->transTimes - 1);
    }
    
    public function explain($sql = '')
    {
        $querySql = !empty($sql)?$sql:$this->querySql;
        $link = $this->_connector->getLink(true);
        $pdo  = $link->query("EXPLAIN " . $querySql);
        $result = array_change_key_case($pdo->fetch(PDO::FETCH_ASSOC));
        
        if (isset($result['extra']))
        {
            if (strpos($result['extra'], 'filesort') || strpos($result['extra'], 'temporary')) //如果使用文件排序或临时表记录日志
            {
                $this->log(['[SQL:{$querySql}] [EXPLAIN:'.json_encode($result).']']);
            }
        }
        
        return $result;
    }
    
    public function showTables($dbName = '')
    {
        $info = [];
        $sql = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ';
        $link = $this->_connector->getLink(true);
        
        $this->debug(true);
        $pdo = $link->query($sql);
        $this->debug(false, $sql);
        
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        if($result)
        {
            foreach ($result as $key => $val) 
            {
                $info[$key] = current($val);
            }
        }
        return $info;
    }
    
    public function showColumns($tableName)
    {
        if (false === strpos($tableName, '`')) 
        {
            if (strpos($tableName, '.')) 
            {
                $tableName = str_replace('.', '`.`', $tableName);
            }
            $tableName = '`' . $tableName . '`';
        }
        
        $info= [];
        $sql = 'SHOW COLUMNS FROM ' . $tableName;
        $link = $this->_connector->getLink(true);
        
        $this->debug(true);
        $pdo = $link->query($sql);
        $this->debug(false, $sql);
        
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
                
        if ($result) 
        {
            foreach ($result as $val) 
            {
                $val = array_change_key_case($val);
                
                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => (bool) ('' === $val['null']), // not null is empty, null is yes
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                ];
            }
        }
        
        return $info;
    }
            
    private function checkMultiField($field, $logic)
    {
        return isset($this->_options['multi'][$logic][$field]) && count($this->_options['multi'][$logic][$field]) > 1;
    }
    
    public function formatOptions()
    {
        $options = array_merge($this->_set_options,$this->_sql_options,$this->_data_options);

        foreach($options as $option)
        {
            if(!isset($this->_options[$option]))
            {
                $this->_options[$option] = [];
            }
        }
        
        return $this->_options;
    }
        
    public function event($event, $callback)
    {
        self::$_event[$event] = $callback;
    }

    protected function trigger($event, $params = [])
    {
        $result = false;
        
        if (isset(self::$_event[$event])) 
        {
            $callback = self::$_event[$event];
            $result   = call_user_func_array($callback, [$params, $this]);
        }
        
        return $result;
    }
    
    protected function debug($start,$sql='')
    {
        if (empty($this->_config['debug']))  
        {
            return false;
        }
        
        if ($start)
        {
            Debug::begin('__QUERY__');
        } 
        else 
        {
            $result  = [];
            $sql     = $sql ?: $this->getLastsql();
   
            if ($this->_config['sql_explain'] && 0 === stripos(trim($sql), 'select'))
            {
                $result = $this->explain($sql);
            }

            $info = Debug::end('__QUERY__');
            
            $msg = "[TIMEUSED:{$info['timeused']}] [MEMORYUSED:{$info['memoryused']}] [SQL:{$sql}] [EXPLAIN:".json_encode($result)."]";
            
            $this->log($msg);
            $this->trigger($sql,$result);//SQL监听
        }
    }
    
    protected function log($msg)
    {
        $ignoreItems = ['logId','rip','uri'];
        Logger::write($msg,$this->_config['logfile'],'d',$ignoreItems);
    }
    
    public function free()
    {
        if (!is_null($this->_PDOStatement)) 
        {
            $this->_PDOStatement = null;
        }
    }
    
    public function __construct($config) 
    {        
        $this->_config = $config;
        $this->_connector = new Connector($config);
        
        Builder::registerQuoteHandler([$this->_connector->getLink(true),'quote']);
        Builder::setPrefix($this->_config['prefix']);
    }
    
    public function __destruct()
    {
        $this->_connector->close();
    }
    
    public function __call($method,$arguments) 
    {
        if(in_array($method,$this->_aggregate_funcs)) //聚合函数
        {
            $field = isset($arguments[0])?$arguments[0]:'*';
            return $this->value($method.'(' . $field . ') AS aggregate_'.$method, 0, true);
        }
        elseif(in_array($method,$this->_set_options)) //设置 set_option 参数
        {
            $bool = isset($arguments[0])?$arguments[0]:true;
            $this->_options[$method] = $bool;
        }
        elseif(strpos($method,'where')!==false) //设置where option
        {
            $op = substr($method,5)?:'and';
            $this->_where(strtolower($op),$arguments);
        }
        else if(substr($method,-4)=='Join') //设置join option
        {
            $type = substr($method,0,-4)?:'inner';
            array_unshift($arguments,$type);
            call_user_func_array([$this,'_join'],$arguments);
        }
        elseif(in_array($method,$this->_sql_options)) //设置其他 sql_option 参数
        {
            if(!isset($arguments[0]))
            {
                throw new \Exception('method '.$method.' miss argument');
            }
            
            $this->_options[$method] = $arguments[0];
        }
        
        return $this;
    }
}
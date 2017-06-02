<?php
/**
 * 数据库封装类
 * @author akong
 * 2013-7-22 15:30:50
 */
namespace Helper;
class OldDbClassSyncHelper{
	var $tablepre;
	var $version = '';
	var $query_num = 0;
	var $link = '';
	var $sql='';
	var $transTimes = 0;
	var $_config = array(
		'host' 		=> '127.0.0.1',
		'port'  => 3306,
		'database' => null,
		'username' => null,
		'password' => null,
		'_charset'	=> 'utf8',//utf8,gbk
		'tablepre'  =>'', 
	);

	public function __construct($config)
	{
		$this->_config = $config;
		$this->tablepre=$config['tablepre'];
			
	}
	
	public function __destruct()
	{
	    if ($this->link && gettype($this->link) == "resource") {
	        mysql_close($this->link);
	    }
	}
	
	// protected
	protected function _connect()
	{
		if ($this->link) return;
		$this->link = mysql_connect(
		$this->_config['host'].":".$this->_config['port'] ,
		$this->_config['username'],
		$this->_config['password'],
		0,
		MYSQL_CLIENT_IGNORE_SPACE
		) or die("DateBase Err: " . mysql_errno() . ": " . mysql_error() );

		mysql_select_db($this->_config['database'], $this->link);
		mysql_query("SET NAMES ".$this->_config['_charset'], $this->link);
	}

	/**
	 * 获取带前缀的数据表名称
	 * @access public
	 * @param  string $table 表名
	 * @return string
	 */
	public function table($table)
	{
		return "`".$this->tablepre.$table."`";
	}

	function selectDb($dbname)
	{
		return mysql_select_db($dbname, $this->link);
	}

	/**
	 * 删除数据
	 * @access public
	 * @param  string $table 表名
	 * @param  mixed $condition 条件(数组或字符串)
	 * @param  int $limit 删除的行数（默认为0，删除所有符合条件的数据行）
	 * @param  bool $unbuffered 为 true(默认) 不获取/缓存结果
	 * @return mixed
	 */
	public function delete($table, $condition, $limit = 0, $unbuffered = true)
	{
		if(empty($condition))
		$where = '1';
		elseif(is_array($condition))
		$where = $this->implodeFieldValue($condition, ' AND ');
		else
		$where = $condition;

		$sql = "DELETE FROM ".$this->table($table)." WHERE $where ".($limit ? "LIMIT $limit" : '');
		return $this->query($sql, ($unbuffered ? '' : ''));
	}

	
	
	/**
	 * 
	 * 获取数据
	 * @param unknown_type $table
	 * @param unknown_type $condition
	 * @param unknown_type $order
	 * @param unknown_type $limit
	 * @param unknown_type $fields
	 * @param unknown_type $force_index
	 */
	function get($table, $condition=null, $order=null, $limit=null, $fields='*', $force_index=''){
		if(empty($condition))
			$where = '1';
		elseif(is_array($condition))
			$where = $this->implodeFieldValue($condition, ' AND ');
		else
			$where = $condition;
		
		$order && $order = "ORDER BY {$order}";
			
			
		$table = $this->table($table);
		$sql = "SELECT {$fields} FROM {$table} " 
			.($force_index? "FORCE INDEX(`$force_index`)": "")
			." WHERE {$where}  {$order}"
			.($limit ? " LIMIT {$limit} " : '');
		return $this->fetchAll($sql);
	}
	
	
	/**
	 * 
	 * 获取一条数据
	 * @param unknown_type $table
	 * @param unknown_type $condition
	 * @param unknown_type $field
	 */
	function getFirst($table, $condition, $field='*'){
		$data = $this->get($table, $condition, null, 1, $field);
		return ($data ? $data[0] : array());
	}
	
	/**
	 * 添加数据
	 * @access public
	 * @param  string $table 表名
	 * @param  array  $data  数据
	 * @param  bool   $return_insert_id 是否返回 INSERT 操作产生的 ID 默认返回
	 * @param  bool   $replace 是否为替换操作  默认为false
	 * @param  bool   $silent  不显示错误 默认为flase(显示)
	 * @return mixed
	 */
	public function insert($table, $data, $return_insert_id = true, $replace = false, $silent = false, $history = array()) {
		$sql = $this->implodeFieldValue($data);
		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
		$table = $this->table($table);
		$silent = $silent ? 'SILENT' : '';
		$return = $this->query("$cmd $table SET $sql", $silent);
        
        if ($return_insert_id) $insertId = $this->insertId();
        
        if ($history) {
            if (!isset($history['type'])) $history['type'] = 1;
            if (!isset($history['table_id'])) $history['table_id'] = $insertId;
            $this->history($history);
        }
        
		return $return_insert_id ? $insertId : $return;
	}
	/**
	 * 有聯合索引防止插入重復數據，以ignore方式插入數據，
	 * @param unknown $table
	 * @param unknown $data
	 * @param string $return_insert_id
	 * @param string $replace
	 * @param string $silent
	 * @return Ambigous <mixed, number>
	 */
	public function insertIgnore($table, $data, $return_insert_id = true, $replace = false, $silent = false) {
		$sql = $this->implodeFieldValue($data);
		$cmd =  'INSERT IGNORE INTO';
		$table = $this->table($table);
		$silent = $silent ? 'SILENT' : '';
		$return = $this->query("$cmd $table SET $sql", $silent);
		if($return) {
			return $return_insert_id ? $this->insertId() : $return;
		} else {
			return $return;
		}
	}

	
	
	/**
	 * 更新数据
	 * @access public
	 * @param  string $table 表名
	 * @param  array  $data  数据
	 * @param  mixed $condition 条件(数组或字符串)
	 * @param  bool   $unbuffered 是否不获取/缓存结果   默认false(获取/缓存结果 )
	 * @param  bool   $low_priority 是否延迟  默认为false
	 * @return mixed
	 */
	function update($table, $data, $condition, $unbuffered = false, $low_priority = false, $history = array())
	{
		$sql = $this->implodeFieldValue($data);
		$cmd = "UPDATE ".($low_priority ? 'LOW_PRIORITY' : '');
		$table = $this->table($table);
		$where = '';
		if(empty($condition))
		$where = '1';
		elseif(is_array($condition))
		$where = $this->implodeFieldValue($condition, ' AND ');
		else
		$where = $condition;

		$res = $this->query("$cmd $table SET $sql WHERE $where", $unbuffered ? 'UNBUFFERED' : '');
        
        if ($history) {
            $history['type'] = 3;
            if (!isset($history['table_id'])) $history['table_id'] = $condition['id'];
            $this->history($history);
        }
        
		return $res;
	}

	/**
	 * 格式化数组为sql查询
	 * @access public
	 * @param  array  $array  数据
	 * @param  string $glue 间隔符
	 * @return string
	 */
	public function implodeFieldValue($array, $glue = ',')
	{
		$sql = $comma = '';
		foreach ($array as $k => $v)
		{
			$sql .= $comma."`$k`='$v'";
			$comma = $glue;
		}
		return $sql;
	}

	/**
	 * 获取 INSERT 操作产生的 ID
	 * @return mixed
	 */
	public function insertId()
	{
		$this->_connect();
		return ($id = mysql_insert_id($this->link)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}


	/**
	 * 根据查询语句获取第一行数据
	 * @access public
	 * @param  string $sql  查询语句
	 * @return array
	 */
	public function fetchFirst($sql)
	{
		return mysql_fetch_array($this->query($sql), MYSQL_ASSOC );

	}

	/**
	 * 根据查询语句获取所有数据
	 * @access public
	 * @param  string $sql  查询语句
	 * @return array
	 */
	public function fetchAll($sql)
	{
		$res = $this->query($sql);
		if ($res !== false)
		{
			$arr = array();
			while ($row = mysql_fetch_assoc($res))
			{
				$arr[] = $row;
			}

			return $arr;
		}
		else
		{
			return false;
		}
	}


	/**
	 * 从结果集中取得指定单元的内容
	 * @access public
	 * @param  resource $resourceid  结果集
	 * @param  int/string $row  单元索引或者字段名称 默认为0(第一个单元)
	 * @return mixed
	 */
	public function result($query, $row = 0)
	{
		$query = @mysql_result($query, $row);
		return $query;
	}

	/**
	 * 根据查询语句获取第一个单元数据
	 * @access public
	 * @param  string $sql  查询语句
	 * @return mixed
	 */
	public 	function resultFirst($sql)
	{
		return $this->result($this->query($sql), 0);
	}

	/**
	 * 执行查询
	 * @access public
	 * @param  string $sql  查询语句
	 * @param  string $type
	 * @return mixed
	 */
	public function query($sql, $type = '')
	{
		$this->_connect();
		$this->sql = $sql;
		$func = $type == 'UNBUFFERED' && @function_exists('mysql_unbuffered_query') ? 'mysql_unbuffered_query' : 'mysql_query';
		if(!($query = $func($sql, $this->link))){
			//屏蔽直接输出
			//echo "database error";
			//记录错误
			$sql_err_msg= "error_no: {".mysql_errno()."}\tmsg: {".mysql_error()."}\tsql: {$sql}\n";
			write_log($sql_err_msg, "sql_error");
			//die();
		}

		$this->query_num++;
		return $query;
	}
    
    /**
     * 记录增删改日志
     * @access public
     * @return void
     */
    public function history($data)
    {
        $table = 'history';
        $data['url'] = isset($_SERVER['REQUEST_URI']) ? trim($_SERVER['REQUEST_URI']) : '';
        $data['time'] = time();
        $this->insert($table, $data, 0);
    }
	
    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans() {
        //if ( !$this->link ) return false;
        //数据rollback 支持
        if ($this->transTimes == 0) {
            $this->query('START TRANSACTION');
        }
        $this->transTimes++;
        return ;
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @return boolen
     */
    public function commit() {
        if ($this->transTimes > 0) {
            $result = $this->query('COMMIT');
            $this->transTimes = 0;
            if(!$result){
                return false;
            }
        }
        return true;
    }

    /**
     * 事务回滚
     * @access public
     * @return boolen
     */
    public function rollback() {
        if ($this->transTimes > 0) {
            $result = $this->query('ROLLBACK');
            $this->transTimes = 0;
            if(!$result){
                return false;
            }
        }
        return true;
    }

	/**
	 * 取得结果集行数
	 * @access public
	 * @param  resource $resourceid  结果集(仅对 SELECT 语句有效)
	 * @return int
	 */
	public function numRows($resourceid)
	{
		$query = mysql_num_rows($resourceid);
		return $query;
	}


	/**
	 * 取得前一次 MySQL 操作所影响的记录行数(INSERT，UPDATE 或 DELETE )
	 * @return int
	 */
	public function affectedRows()
	{
		return mysql_affected_rows($this->link);
	}

	/**
	 * 释放所有与结果标识符 result 所关联的内存
	 * @return bool
	 */
	public function freeResult($resourceid)
	{
		return mysql_free_result($resourceid);
	}


	public function error()
	{
		return (($this->link) ? mysql_error($this->link) : mysql_error());
	}

	public function errno()
	{
		return intval(($this->link) ? mysql_errno($this->link) : mysql_errno());
	}

	public function version()
	{
		$this->_connect();
		if(empty($this->version))
		{
			$this->version = mysql_get_server_info($this->link);
		}
		return $this->version;
	}

	public function fetchRow($query)
	{
		$query = mysql_fetch_row($query);
		return $query;
	}

	public function fetchFields($query) {
		return mysql_fetch_field($query);
	}

	/**
	 * 
	 * 批量插入数据
	 * 需要注意数据格式的统一
	 * @param unknown_type $table
	 * @param unknown_type $data
	 * @param unknown_type $replace
	 */
	public function batchInsert ($table, $data, $replace = false) {
        if (empty($data))  return false;
        $table = $this->table($table);
		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        
        $insert_fields_str = $insert_value_str = '';
        $insert_value_str_arr = array();
        foreach ($data as $row) {
            $insert_fields_arr = $insert_value_arr = array();
            foreach ($row as $k => $v) {
				$insert_fields_arr[] = "`$k`";
				$insert_value_arr[] = "'$v'";
            }
            $insert_fields_str = implode(', ', $insert_fields_arr);
            $insert_value_str = implode(', ', $insert_value_arr);
            $insert_value_str_arr[] = "($insert_value_str)";
        }
        
        $insert_value_str_arr_str = implode(', ', $insert_value_str_arr);
        
        $sql = "$cmd $table ({$insert_fields_str}) VALUES $insert_value_str_arr_str";
        return $this->query($sql);
    }
    
	public function count ($table, $condition = null, $distinct_field=null) {
		$table = $this->table($table);
        if(empty($condition))
			$where = '1';
		elseif(is_array($condition))
			$where = $this->implodeFieldValue($condition, ' AND ');
		else
			$where = $condition;
        
        if($distinct_field){
            $count = "COUNT(DISTINCT(`{$distinct_field}`))";
        }else{
            $count = "COUNT(*)";
        }
        $sql = "SELECT {$count} AS count_data FROM $table WHERE $where ";
        $row = $this->fetchFirst($sql);
        return $row['count_data'];
    }
}

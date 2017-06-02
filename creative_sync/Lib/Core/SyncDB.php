<?php
namespace Lib\Core;
use Lib\Core\SyncConf;
use Helper\CommonSyncHelper;
class SyncDB{
	public static $db_ins = null;
	protected $table = '';
	private static $mongo_db_ins = null;
    
	private static $mongo_new_adn = null;
	private static $dbOriginMongo = null;
	
	public static $syncOfferInfosMongo = null;
	public static $syncApiConfigMongo = null;
	private static $debug = 0;
	
	public function getDB(){
		if (!self::$db_ins) {
			$config = SyncConf::getSyncConf('db');
			self::$db_ins = new \medoo($config['mob_adn']);
		}
		return self::$db_ins;
	}
    
	public function getLastDbError(){
	    $errInfo = array();
	    if($this->getDB()->pdo->errorCode() != '00000'){
	        $errInfo = $this->getDB()->pdo->errorInfo();
	        $errInfo[3] = $this->getDB()->last_query();
	        $errInfo[4] = date('Y-m-d H:i:s');
	    }
	    $sync_offer_source = '';
	    if(defined('SYNC_OFFER_SOURCE')){
	    	$sync_offer_source = SYNC_OFFER_SOURCE;
	    }
	    if(!empty($errInfo)){
	        var_dump($errInfo);
	        $logPath = 'mysql_log';
	        if(!empty($sync_offer_source)){
	            CommonSyncHelper::commonWriteLog($logPath,strtolower($sync_offer_source),$errInfo,'array');
	        }else{
	            CommonSyncHelper::commonWriteLog($logPath,'special_tool_program',$errInfo,'array');
	        }
	    }
		return $errInfo;
	}
    
	public function select($fields = '*',$conds = array()){
		if(empty($fields)){
			$fields = '*';
		}
		$rz = $this->getDB()->select($this->table, $fields, $conds);
		if(self::$debug){
		    echo $this->getDB()->last_query()."\n";
		}
		$errInfo = $this->getLastDbError();
		return $rz;
	}
	
	public function getOne($fields = '*',$conds = array()){
		if(empty($fields)){
			$fields = '*';
		}
		$rz = $this->getDB()->get($this->table,$fields,$conds);
		if(self::$debug){
		    echo $this->getDB()->last_query()."\n";
		}
		$errInfo = $this->getLastDbError();
		return $rz;
	}
	
	public function update($upData = array(),$conds = array()){
		if(empty($upData)) return false;
		$rz = $this->getDB()->update($this->table, $upData, $conds);
		if(self::$debug){
		    echo $this->getDB()->last_query()."\n";
		}
		$errInfo = $this->getLastDbError();
		return $rz;
	}
	
	public function insert($insData = array()){
		if(empty($insData)) return false;
		$rz = $this->getDB()->insert($this->table, $insData);
		if(self::$debug){
		    echo $this->getDB()->last_query()."\n";
		}
		if(empty($rz)){
			$errInfo = $this->getLastDbError();
			#throw new \Exception($errorStr[2]);
		}else{
		    $errInfo = $this->getLastDbError();
			return $rz;			
		}
 
	}
	
	public function delete($conds = array()){
		$rz = $this->getDB()->delete($this->table, $conds);
		if(self::$debug){
		    echo $this->getDB()->last_query()."\n";
		}
		$errInfo = $this->getLastDbError();
		return $rz;
	}
	/**
	 * 
	 * @param unknown $sqlStr
	 * @param string $type:(select insert update delete other)
	 * @return boolean|multitype:|PDOStatement
	 */
	public function query($sqlStr,$type = ''){
		
		if(empty($sqlStr)){
			return false;
		}
		$rz = array();
		if($type == 'select'){
			$cot = 0;
			while (1){
				$queryObj = $this->getDB()->query($sqlStr);
				if(self::$debug){
				    echo $this->getDB()->last_query()."\n";
				}
				if(is_object($queryObj)){
				    $rz = $queryObj->fetchAll();
				    $errInfo = $this->getLastDbError();
					return $rz;
					break;
				}else{
					echo "Error: SyncDb query type 'select' throw non-object error, retry time: ".$cot."\n";
				}
				$cot ++;
				if($cot > 3){
					echo "Error: non-object retry time: ".$cot." and stop retry fail\n";
					echo "Error: sql is: ".$sqlStr."\n";
					break;
				}
			}	
		}elseif($type == 'update'){
		    $rz = $this->getDB()->query($sqlStr);
		    if(self::$debug){
		        echo $this->getDB()->last_query()."\n";
		    }
		    $errInfo = $this->getLastDbError();
			return $rz;
		}else{
		    $rz = $this->getDB()->query($sqlStr)->fetchAll();
		    if(self::$debug){
		        echo $this->getDB()->last_query()."\n";
		    }
		    $errInfo = $this->getLastDbError();
			return $rz;
		}
		
	}
	
	/**
	 * use mysql action: select
	 * @param unknown $sqlStr
	 * @param string $fetch_style
	 * @return multitype:
	 */
	public function newQuery($sqlStr,$fetch_style = 'FETCH_ASSOC'){
		$dbObj = $this->getDB();
		$queryObj = $dbObj->query($sqlStr);
		$pdo = $dbObj->pdo;
	    $fetch_style_obj = '';
	    $rz = array();
		if($fetch_style == 'FETCH_ASSOC'){
			$fetch_style_obj = $pdo::FETCH_ASSOC;
		}elseif($fetch_style == 'FETCH_COLUMN'){
			$fetch_style_obj = $pdo::FETCH_COLUMN;
		}
		$rz = $queryObj->fetchAll($fetch_style_obj); //FETCH_COLUMN
		if(self::$debug){
		    echo $this->getDB()->last_query()."\n";
		}
		$errInfo = $this->getLastDbError();
		return $rz;
	}
	
	/**
	 * use mysql action: delete update insert
	 * @param unknown $sqlStr
	 * @return number
	 */
	public function newExec($sqlStr){
	    $dbObj = $this->getDB();
	    $rz = $dbObj->exec($sqlStr);
	    if(self::$debug){
	        echo $this->getDB()->last_query()."\n";
	    }
	    $errInfo = $this->getLastDbError();
	    return $rz;
	}
	
	public function queryByFetchStyle($sqlStr,$fetch_style){
		$dbObj = $this->getDB();
		$queryObj = $dbObj->query($sqlStr);
		$pdo = $dbObj->pdo;
		$fetch_style_obj = '';
		if($fetch_style == 'FETCH_COLUMN'){
			$fetch_style_obj = $pdo::FETCH_COLUMN;
		}elseif($fetch_style == 'FETCH_ASSOC'){
			$fetch_style_obj = $pdo::FETCH_ASSOC;
		}
		if(empty($fetch_style_obj)){
			exit('queryByFetchStyle fetch_style_obj null error...');
		}
		$rz = $queryObj->fetchAll($fetch_style_obj); //FETCH_COLUMN
		if(self::$debug){
		    echo $this->getDB()->last_query()."\n";
		}
		$errInfo = $this->getLastDbError();
		return $rz;
	}
    
	//mongo ---------------------------------------------------------------
	
	public function getMongo(){
	    if (!self::$mongo_db_ins) {
	        $config = SyncConf::getSyncConf('mongo_db');
	        self::$mongo_db_ins = new \MongoQB\Builder($config['mob_adn']);
	    }
	    return self::$mongo_db_ins;
	}
	
	public function getNewAdnMongo(){
	    if (!self::$mongo_new_adn) {
	        $config = SyncConf::getSyncConf('mongo_db');
	        self::$mongo_new_adn = new \MongoQB\Builder($config['new_adn']);
	    }
	    return self::$mongo_new_adn;
	}
	
	public function getCommonMongo($db){
	    if(empty($db)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'param 1 db null');
	        return false;
	    }
	    if (!self::$syncOfferInfosMongo) {
	        $config = SyncConf::getSyncConf('mongo_db');
	        self::$syncOfferInfosMongo = new \MongoQB\Builder($config[$db]);
	    }
	    return self::$syncOfferInfosMongo;
	}
	
	public function getApiConfigMongo($db){
	    if(empty($db)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'param 1 db null');
	        return false;
	    }
	    if (!self::$syncApiConfigMongo) {
	        $config = SyncConf::getSyncConf('mongo_db');
	        self::$syncApiConfigMongo = new \MongoQB\Builder($config[$db]);
	    }
	    return self::$syncApiConfigMongo;
	}
	
	public function getSyncOfferInfosMongo(){
	    if (!self::$syncOfferInfosMongo) {
	        $config = SyncConf::getSyncConf('mongo_db');
	        self::$syncOfferInfosMongo = new \MongoQB\Builder($config['SyncOfferInfos']);
	    }
	    return self::$syncOfferInfosMongo;
	}
	
	public static function getOriginMongo($connectTime = '',$socketTimeout = ''){
	    $config = SyncConf::getSyncConf('mongo_db');
	    $timeout = empty($connectTime)?$config['timeout']:$connectTime;
	    if (!self::$dbOriginMongo) {
	        $server = "mongodb://{$config['old_mob_adn']['host']}:{$config['old_mob_adn']['port']}";
	        self::$dbOriginMongo = new \MongoClient($server,
	            array(
	                'connectTimeoutMS' => $timeout ? $timeout : 100,
	                'socketTimeoutMS' => empty($socketTimeout)?300000:$socketTimeout //待优化
	            ));
	    }
	    return self::$dbOriginMongo;
	}
	
	public static function getOriginCollection($mongoClient,$dbName,$collectionName){
	    if(empty($mongoClient)){
	        echo "mongoClient null error.\n";
	    }
	    if(empty($dbName)){
	        echo "dbName null error.\n";
	    }
	    if(empty($collectionName)){
	        echo "collectionName null error.\n";
	    }
	    $db = $mongoClient->selectDB($dbName);
        $collection = new \MongoCollection($db, $collectionName);
	    return $collection;
	}
}
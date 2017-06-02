<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
class PackagenameMapApkUrlSyncModel extends SyncDB{
	public $table;
	public $dbObj;
	public function __construct(){
		$this->table = 'apk_url_packagename_map';
		$this->dbObj = $this->getDB();
	}

    public function addPackageNameUrl($trace_app_id,$url,$type = 0){
    	$data = array(
    			'trace_app_id' => $trace_app_id,
    			'url' => $url,
    			'type' => $type,
    	);
    	$insertId = $this->insert($data);
    	return $insertId;
    }
    
    public function getPackageNameApkUrl($trace_app_id,$type = 0){
    	if(empty($trace_app_id)){
    		return false;
    	}
    	$conds = array();
    	$conds['AND']['trace_app_id'] = trim($trace_app_id);
    	$conds['AND']['type'] = $type;
    	$rz = $this->getOne('*',$conds);
    	return $rz;
    }
}
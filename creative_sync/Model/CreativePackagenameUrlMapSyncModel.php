<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
class CreativePackagenameUrlMapSyncModel extends SyncDB{
	public $table;
	public $dbObj;
	public function __construct(){
		$this->table = 'creative_packagename_url_map';
		$this->dbObj = $this->getDB();
	}

    public function addPackageNameUrl($trace_app_id,$url,$creative_type){
    	$data = array(
    			'trace_app_id' => $trace_app_id,
    			'url' => $url,
    			'type' => $creative_type,
    	);
    	$insertId = $this->insert($data);
    	return $insertId;
    }
    
    public function getPackageNameUrl($trace_app_id,$creative_type){
    	if(empty($trace_app_id) || empty($creative_type)){
    		return false;
    	}
    	$conds = array();
    	$conds['AND']['trace_app_id'] = trim($trace_app_id);
    	$conds['AND']['type'] = $creative_type;
    	$rz = $this->getOne('*',$conds);
    	return $rz;
    }
}
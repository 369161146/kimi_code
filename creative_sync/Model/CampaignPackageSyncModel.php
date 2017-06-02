<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
use Helper\OfferSyncHelper;
class CampaignPackageSyncModel extends SyncDB{
	public $table;
	public $dbObj;
	public function __construct(){
		$this->table = 'campaign_package';
		$this->dbObj = $this->getDB();
	}
    
    public function addPackageName($trace_app_id){
        if(empty($trace_app_id)){
            return false;
        }
    	$data = array(
    			'trace_app_id' => trim($trace_app_id),
    			'special_type' => '',
    			'admin_user_id' => 0,
    	        'mtime' => date('Y-m-d H:i:s'),
    	);
    	$insertId = $this->insert($data);
    	return $insertId;
    }
    
    public function getPackageName($trace_app_id){
    	if(empty($trace_app_id)){
    		return false;
    	}
    	$conds = array();
    	$conds['AND']['trace_app_id'] = trim($trace_app_id);
    	$rz = $this->getOne('*',$conds);
    	return $rz;
    }
    
    public function updateSpecialTypeByPackageName($trace_app_id,$updateSpecialType){
        if(empty($trace_app_id) || empty($updateSpecialType)){
            return false;
        }
        $special_types = array_keys(OfferSyncHelper::special_types());
        if(!in_array($updateSpecialType, $special_types)){
            return false;
        }
        $conds = array();
        $conds['trace_app_id'] = $trace_app_id;
        $upData = array();
        $upData['special_type'] = $updateSpecialType;
        $this->update($upData,$conds);
        return true;   
    }
}
<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
use Model\CamListSyncModel;
use Helper\CommonSyncHelper;
use Helper\OfferSyncHelper;
class ConfigSystemModel extends SyncDB{
        
	public function __construct(){
		$this->table = 'config_system';
		$this->dbObj = $this->getDB();
		
	}
    
	public function getInfoByKey($ckey){
	    if(empty($ckey)){
	        return false;
	    }
	    $conds = array();
	    $conds['ckey'] = trim($ckey);
	    $rz = $this->select(array('id','ckey','cvalue','status'),$conds);
	    if(empty($rz[0])){
	        return array();
	    }
	    if(intval($rz[0]['status']) !== 1){
	    	return false;
	    }
	    if(empty($rz[0]['cvalue'])){
	    	return false;
	    }
	    return $rz[0]['cvalue'];
	}
}
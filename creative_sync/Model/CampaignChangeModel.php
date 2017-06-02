<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
use Model\CamListSyncModel;
use Helper\CommonSyncHelper;
use Helper\OfferSyncHelper;
class CampaignChangeModel extends SyncDB{
        
	public function __construct(){
		$this->table = 'campaign_change';
		$this->dbObj = $this->getDB();
		
	}
    
	public function getInfoByOfferId($offer_id,$field){
	    if(empty($offer_id) || empty($field)){
	        return false;
	    }
	    $conds = array();
	    $conds['AND']['campaign_id'] = $offer_id;
	    $conds['AND']['field'] = $field;
	    $rz = $this->select('*',$conds);
	    if(empty($rz[0])){
	        return array();
	    }
	    return $rz[0];
	}
}
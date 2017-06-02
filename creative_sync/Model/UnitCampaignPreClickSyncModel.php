<?php
namespace Model;
use Lib\Core\SyncDB;
use Helper\ImageSyncHelper;
use Lib\Core\SyncConf;
use Model\CamListSyncModel;
use Helper\SyncQueueSyncHelper;
use Lib\Core\SyncApi;
class UnitCampaignPreClickSyncModel extends SyncDB{
	public static $offerSource;
	public static $subNetWork;
	public $dbObj = null;
	public static $syncConf = array();
	
	public function __construct(){
		$this->table = 'unit_campaign_pre_click';
		$this->dbObj = $this->getDB();
	}
	
	public function deleteUnitCampaigns($exists,$data){
		if($exists['pre_click'] != $data['pre_click']){
			$conds = array();
			$conds['campaign_id'] = $exists['id'];
			$rz = $this->delete($conds);
			if(!empty($rz)){
			    return true;
			}
			return false;
		}else{
			return false;
		}
	}
	
}
<?php
namespace Model;
use Lib\Core\SyncDB;
use Helper\ImageSyncHelper;
use Lib\Core\SyncConf;
use Model\CamListSyncModel;
use Helper\SyncQueueSyncHelper;
use Lib\Core\SyncApi;
use Helper\CommonSyncHelper;
class HistorySyncModel extends SyncDB{
    const PRICE_CHANGE_LOG_NAME = 'price_change';
	public static $offerSource;
	public static $subNetWork;
	public $dbObj = null;
	public static $syncConf = array();
	
	public function __construct($offerSource,$subNetWork){
		self::$subNetWork = $subNetWork;
		self::$offerSource = $offerSource;
		$apiConf = SyncConf::getSyncConf('apiSync');
		self::$syncConf = $apiConf[self::$offerSource][self::$subNetWork];
		$this->table = 'campaign_history';
		$this->dbObj = $this->getDB();
	}
	
	public function checkPriceChangeToLog($exists,$data){
		if($exists['price'] != $data['price']){
			$insertD = array();
			$insertD = array(
					'campaign_id' => $exists['id'],
					'old_value' => $exists['price'],
					'new_value' => $data['price'],
					'type' => 2, //类型，1为original_price，2为price
					'desc' => 'campaign source is: '.self::$offerSource,
					'ctime' => date('Y-m-d H:i:s'),
			);
			$this->writePriceChangeFileLog($insertD,'update'); 
   #$this->insert($insertD);  //20160513关闭价格变动数据入campaign_history表
			return true;
		}else{
			return false;
		}
	}
	
	public function writePriceChangeFileLog($insertD,$updateType){
	    if(!empty($insertD)){
	        if($updateType == 'update'){
	            $insertD['type'] = 'price_update';
	        }else{
	            $insertD['type'] = 'price_insert';
	        }
	        CommonSyncHelper::commonWriteLog(self::PRICE_CHANGE_LOG_NAME,strtolower(self::$offerSource),$insertD,'array');
	    }
	}
	
}
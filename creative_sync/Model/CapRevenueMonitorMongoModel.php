<?php
namespace Model;
use Lib\Core\SyncDB;
use Helper\ImageSyncHelper;
use Lib\Core\SyncConf;
use Model\CamListSyncModel;
use Helper\SyncQueueSyncHelper;
use Lib\Core\SyncApi;
use Helper\CommonSyncHelper;
use Alchemy\Zippy\Zippy;
class CapRevenueMonitorMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
    function __construct(){
        $this->mongoObj = $this->getCommonMongo('sync_campaign');
        self::$collection = 'daily_cap_revenue_monitor';
    }
    
    function insertData($offerId,$new_cap,$old_cap){
        if(empty($offerId)){
            return false;
        }
        $insertD = array();
        $insertD['offer_id'] = (int)$offerId;
        $insertD['new_daily_cap'] = $new_cap;
        $insertD['old_daily_cap'] = $old_cap;
        $insertD['date'] = (int)date('YmdH');
        $insertD['timestamp'] = time();
        $insertD['date_created'] = new \MongoDate();
        $rz = $this->mongoObj->insert(self::$collection, $insertD);
        return $rz;
    }
    
}
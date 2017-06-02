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
class OfferPriceMonitorMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
    function __construct(){
        $this->mongoObj = $this->getCommonMongo('sync_campaign');
        self::$collection = 'offer_price_monitor';
    }
    
    function insertData($monitorData){
        if(empty($monitorData)){
            return false;
        }
        $monitorData['time'] = date('Y-m-d H:i:s');
        $monitorData['date_created'] = new \MongoDate();
        $rz = $this->mongoObj->insert(self::$collection, $monitorData);
        return $rz;
    }
    
    function selectData($date){
        if(empty($date)){
            return false;
        }
        $conds = array();
        $conds['date'] = $date;
        $rz = $this->mongoObj->where($conds)->get(self::$collection);
        $lastD = array();
        if(!empty($rz)){
            foreach($rz as $k => $v){
                if(!empty($v)){
                    unset($v['date_created']);
                    unset($v['_id']);
                    unset($v['date']);
                    unset($v['timestamp']);
                    $lastD[] = $v;
                }
            }  
        }
        return $lastD;
    }
}
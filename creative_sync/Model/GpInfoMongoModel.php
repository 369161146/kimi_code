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
class GpInfoMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
    function __construct(){
        $this->mongoObj = $this->getCommonMongo('sync_campaign');
        self::$collection = 'new_gp_info_cache';
    }
    
    function insertData($gpInfo){
        if(empty($gpInfo) || empty($gpInfo['trace_app_id']) || !isset($gpInfo['network'])){
            return false;
        }
        $gpInfo['time'] = date('YmdHis');
        $gpInfo['date_created'] = new \MongoDate();
        $rz = $this->mongoObj->insert(self::$collection, $gpInfo);
        return $rz;
    }
    
    function selectData($apiRow){
        if(empty($apiRow['packageName']) || !isset($apiRow['network'])){
            return false;
        }
        $conds = array();
        $conds['trace_app_id'] = trim($apiRow['packageName']);
        $conds['network'] = $apiRow['network'];
        $rz = $this->mongoObj->where($conds)->get(self::$collection);
        if(!empty($rz)){
            unset($rz[0]['_id']);
            return $rz[0];
        }
        return $rz;
    }
    
    function selectShareInfoData($apiRow){
        if(empty($apiRow['packageName'])){
            return false;
        }
        $conds = array();
        $conds['trace_app_id'] = trim($apiRow['packageName']);
        $rz = $this->mongoObj->where($conds)->get(self::$collection);
        if(!empty($rz)){
            unset($rz[0]['_id']);
            return $rz[0];
        }
        return $rz;
    }
}
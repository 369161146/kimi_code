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
class IosInfoMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
    function __construct(){
        $this->mongoObj = $this->getCommonMongo('sync_campaign');
        self::$collection = 'new_ios_info_cache';
    }
    
    function insertData($iosInfo){
        if(empty($iosInfo) || empty($iosInfo['trace_app_id']) || empty($iosInfo['country']) || !isset($iosInfo['network'])){
            return false;
        }
        $iosInfo['time'] = date('YmdHis');
        $iosInfo['date_created'] = new \MongoDate();
        $rz = $this->mongoObj->insert(self::$collection, $iosInfo);
        return $rz;
    }
    
    function selectData($apiRow){
        if(empty($apiRow['itunes_appid']) || !is_numeric($apiRow['itunes_appid']) || !isset($apiRow['network'])){
            return false;
        }
        $rz = array();
        $cot = 0;
        foreach ($apiRow['geoTargeting'] as $k_geo => $v_geo){
            if(empty($v_geo)){
                continue;
            }
            $conds = array();
            $conds['trace_app_id'] = (int)trim($apiRow['itunes_appid'],'id');
            $conds['country'] = $v_geo;
            $conds['network'] = $apiRow['network']; 
            $rz = $this->mongoObj->where($conds)->get(self::$collection);      
            if(!empty($rz[0]['trace_app_id']) && !empty($rz[0]['country']) && isset($rz[0]['network'])){
                unset($rz[0][_id]);
                $rz = $rz[0];
                break;
            }
            $cot ++;
            if($cot > 5){
                CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get cache retry over 5 time to stop',2);
                return false;
            }
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get cache retry '. $cot . ' time', 2);
        }
        if (empty($rz)) {
            return false;
        }
        return $rz;
    }
    
    function selectShareInfoData($apiRow){
        if (empty($apiRow['itunes_appid']) || ! is_numeric($apiRow['itunes_appid'])) {
            return false;
        }
        $rz = array();
        $conds = array();
        $conds['trace_app_id'] = (int) trim($apiRow['itunes_appid'], 'id');
        $rz = $this->mongoObj->where($conds)->get(self::$collection);
        if (! empty($rz[0]['trace_app_id']) && ! empty($rz[0]['country']) && isset($rz[0]['network'])) {
            unset($rz[0][_id]);
            $rz = $rz[0];
        }
        if(empty($rz)){
            return false;
        }
        return $rz;
    
    }
}
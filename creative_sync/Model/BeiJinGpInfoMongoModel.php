<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
use Helper\CommonSyncHelper;
class BeiJinGpInfoMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
    function __construct(){
        $this->mongoObj = $this->getCommonMongo('sync_campaign');
        self::$collection = 'gp_info_cache';
    }
    
    function insertData($gpInfo,$trace_app_id){
        if(empty($gpInfo) || empty($trace_app_id)){
            return false;
        }
        $gpInfo['trace_app_id'] = $trace_app_id;
        $gpInfo['time'] = date('YmdHis');
        $gpInfo['date_created'] = new \MongoDate();
        $rz = $this->mongoObj->insert(self::$collection, $gpInfo);
        return $rz;
    }
    
    function selectData($trace_app_id){
        if(empty($trace_app_id)){
            return false;
        }
        $conds = array();
        $conds['trace_app_id'] = $trace_app_id;
        $rz = $this->mongoObj->where($conds)->get(self::$collection);
        if(!empty($rz[0]['trace_app_id'])){
            unset($rz[0]['_id']);
            unset($rz[0]['trace_app_id']);
            unset($rz[0]['time']);
            unset($rz[0]['date_created']);
            $rz = $rz[0];
            return $rz;
        }else{
            return false;
        }
    }
}
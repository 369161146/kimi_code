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
class AllTypeCreativeMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
    function __construct(){
        $this->mongoObj = $this->getCommonMongo('sync_campaign');
        self::$collection = 'creative_url_cache';
    }
    
    function insertData($trace_app_id,$type,$url){
        if(empty($trace_app_id) || empty($type) || empty($url)){
            return false;
        }
        $insertD = array();
        $insertD['trace_app_id'] = $trace_app_id;
        $insertD['type'] = $type;
        $insertD['url'] = $url;
        $insertD['time'] = date('YmdHis');
        $rz = $this->mongoObj->insert(self::$collection, $insertD);
        return $rz;
    }
    
    function selectData($trace_app_id,$type){
        if(empty($trace_app_id) || empty($type)){
            return false;
        }        
        $conds = array();
        $conds['trace_app_id'] = $trace_app_id;
        $conds['type'] = $type;
        $rz = $this->mongoObj->where($conds)->get(self::$collection);
        return $rz;
    }
}
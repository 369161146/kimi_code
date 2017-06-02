<?php
namespace Cache;
use Lib\Core\SyncCache;
use Helper\CommonSyncHelper;
use Lib\Core\SyncDB;
class AdvOfferMongoCache extends SyncCache{
    
    public static $db;
    public static $collection;
    public static $dbObj;
    public static $mongoDbObj;
    function __construct(){
        self::$db = 'sync_campaign';
        self::$collection  = 'adv_offer_cache';
        if(empty(self::$mongoDbObj)){
            self::$dbObj = new SyncDB();
        }
        self::$mongoDbObj = self::$dbObj->getCommonMongo(self::$db);
    }
    
    function getCache($source_id){
        if(empty($source_id)){
            return false;
        }
        $conds = array();
        $conds['source_id'] = $source_id;
        try {
            $rz = self::$mongoDbObj->where($conds)->get(self::$collection);
        } catch (\Exception $e) {
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get cache fail source_id: '.$source_id);
            echo $e->getMessage()."\n";
            return false;
        }
        if(empty($rz)){
            return array();
        }
        return $rz[0];
    }
    
    function upsertCache($data,$row,$syncConf){
        if(empty($data) || empty($data['source_id']) || empty($row) || empty($syncConf)){
            return false;
        }
        $data['date_created'] = new \MongoDate();
        if($syncConf['network'] == 1 && $syncConf['advertiser_id'] != 582){ //is 3s and not superads advertiser 
            $data['3s_video_creatives'] = empty($row['3s_video_creatives'])?'':$row['3s_video_creatives'];
        }
        $conds = array();
        $conds['source_id'] = $data['source_id'];
        $options = array();
        $options['upsert'] = true;
        try {
            self::$mongoDbObj->set($data);
            self::$mongoDbObj->where($conds)->update(self::$collection,$options);
        } catch (\Exception $e) {
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'upsertCache fail source_id: '.$data['source_id']);
            echo $e->getMessage()."\n";
            return false;
        }
        return true;
    }
    
    function delCache($source_id){
        if(empty($source_id)){
            return false;
        }
        $conds = array();
        $conds['source_id'] = $source_id;
        try {
            self::$mongoDbObj->where($conds)->delete(self::$collection);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
    
    /**
     * check adv cache data if same as new adv data
     * @param unknown $data
     * @param unknown $advCache
     * @param unknown $getUpdateField
     * @return boolean true is same;false is not same
     */
    function checkIfSameToCache($data,$advCache,$getUpdateField,$configsJsonUpdateField,$row,$syncConf){
        foreach ($getUpdateField as $v_field){
            if($v_field == 'update'){ //check configs json field update field if diff from cache 
                $jsonFieldConfigsRz = $this->checkConfigsJsonUpdateField($data[$v_field],$advCache[$v_field],$configsJsonUpdateField);
                if(!$jsonFieldConfigsRz){
                    return false;
                }
            }
            if($data[$v_field] != $advCache[$v_field]){
                return false;
            }
        }
        //3s special video url cache diff special logic.
        if($syncConf['network'] == 1 && $syncConf['advertiser_id'] != 582){ //is 3s and not superads advertiser
            if($row['3s_video_creatives'] != $advCache['3s_video_creatives']){
                CommonSyncHelper::xEcho('debug...new 3s_video diff from 3s cache');
                return false;
            }else{
                CommonSyncHelper::xEcho('debug...new 3s_video same as 3s cache');
            }
        }
        //end
        return true;
    }
    
    /**
     * check json configs field value json field if same
     * @param unknown $advData
     * @param unknown $advCache
     * @param unknown $configsJsonUpdateField
     * @return boolean
     */
    function checkConfigsJsonUpdateField($advData,$advCache,$configsJsonUpdateField){
        $advDataArr = json_decode($advData,true);
        $advCacheArr = json_decode($advCache,true);
        foreach ($configsJsonUpdateField as $v_field){
            if($advDataArr[$v_field] != $advCacheArr[$v_field]){
                return false;
            }
        }
        return true;
    }
}
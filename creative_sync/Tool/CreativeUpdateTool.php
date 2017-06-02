<?php
require '../Lib/syncInit.php';
use Lib\Core\SyncConf;
use Helper\NoticeSyncHelper;
use Helper\CommonSyncHelper;
use Lib\Core\SyncDB;
use Helper\OfferSyncHelper;
use Helper\SyncQueueSyncHelper;
use Lib\Core\SyncApi;
use Helper\ImageSyncHelper;
class CreativeUpdateTool extends SyncDB{
    public $dbObj;
    public $mongoObj;
    public static $sourceId;
    public static $apiConf;
    public static $syncApiObj;
    public static $specialApiConf;
    public static $checkTypeArr;
    public static $cacheCollection;
    public static $apiConfCollection;
    public static $imageObj;
    public static $syncQueueObj;
    public static $groupLimit;
    
    function __construct($sourceId){
        self::$sourceId = $sourceId;
        $this->dbObj = $this->getDB();
        $this->mongoNewAdn = $this->getApiConfigMongo ('new_adn');
        $this->mongoSyncCampaign = $this->getCommonMongo ('sync_campaign');
        self::$apiConf = SyncConf::getSyncConf('apiSync');
        self::$specialApiConf = SyncConf::getSyncConf('specialApi');
        self::$syncApiObj = new SyncApi();
        self::$checkTypeArr = array( //config check 3s creative api creative type
            '320x50' => 2,
            '300x250' => 4,
            '320x480' => 6,
            '1200x627' => 42,
        	'240x350' => 101,
        	'390x200' => 102,
        	'560x750' => 103,
        	'750x560' => 104,
        	'300x300' => 41,
        	'480x320' => 5,
        );
        self::$cacheCollection = 'update_creative_cache';
        self::$apiConfCollection = 'api_config';
        self::$imageObj = new ImageSyncHelper();
        self::$syncQueueObj = new SyncQueueSyncHelper();
self::$groupLimit = 20; //请求分组配置
    }
    
    function run(){
        $handelSources = array();
        if(!empty(self::$sourceId) && is_numeric(self::$sourceId)){
            echo "begin to run souce id: ".self::$sourceId.' '.date('Y-m-d H:i:s')."\n";
            $handelSources = $this->getMongoApiConfig(self::$sourceId);
        }else{
            echo "begin to run all ".date('Y-m-d H:i:s')."\n";
            $handelSources = $this->getMongoApiConfig();
        }
        $this->handelCreative($handelSources);
    }

    public function handelCreative($handelSources){
        if(empty($handelSources)){
            echo "get no source to handle ".date('Y-m-d H:i:s')."\n";
            return false;
        }
        foreach ($handelSources as $k => $v_conf){
            echo "===>>> To handle source: ".$v_conf['source']." offer_source: ".$v_conf['offer_source'].' '.date('Y-m-d H:i:s')."\n";
            $sourceOffers = $this->getSourceOffers($v_conf['source']);
            $this->groupToCurl($sourceOffers);
        }
    }
    
    public function groupToCurl($sourceOffers){
        if(empty(self::$specialApiConf['3s_creative']['api']) || empty(self::$specialApiConf['3s_creative']['secret'])){
            echo "3s creative api or secret not config error ".date('Y-m-d H:i:s')."\n";
            return false;
        }
        $uuidMapOfferId = array();
        $uuidMapPlatform = array();
        $uuidMapTraceAppId = array();
        $circleArrStr = array();
        $cot = 0;
        $str = '';
   ##$groupLimit = 2;  //online set 100
        foreach ($sourceOffers as $k => $v){
            $uuidMapOfferId[$v['network_cid']] = $v['id'];
            $uuidMapPlatform[$v['network_cid']] = $v['platform'];
            $uuidMapTraceAppId[$v['network_cid']] = $v['trace_app_id'];
            $cot ++;
            if(empty($str)){
                $str = $v['network_cid'];
            }else{
                $str = $str.','.$v['network_cid'];
            }
            if($cot >= self::$groupLimit){
                $circleArrStr[] = trim($str,',');
                $cot = 0;
                $str = '';
            }
        }
        if(!empty($str)){
            $circleArrStr[] = trim($str);
        }
        foreach ($circleArrStr as $k_group => $v_group){
            $apiRz = '';
            $v_group = trim($v_group,',');
            $api = self::$specialApiConf['3s_creative']['api'];
            $mapArr = array('[uuid_str]');
            $repArr = array($v_group);
            $api = str_replace($mapArr, $repArr, $api);
            $headArr = array('Authorization: Basic ' . self::$specialApiConf['3s_creative']['secret']);
            $apiRz = self::$syncApiObj->syncCommonCurl($api,0,$headArr,60);
            #var_dump($rz);die;
            $filterRz = $this->filterSameAsCache($apiRz,$uuidMapOfferId,$uuidMapPlatform,$uuidMapTraceAppId);
            if(!empty($filterRz)){
                $this->upsertCreative($filterRz,$uuidMapOfferId);
            }else{
                echo "get no update 3s creative ".date('Y-m-d H:i:s')."\n";
            }
            
        }
        
    }

    public function upsertCreative($filterRz, $uuidMapOfferId){
        if (empty($filterRz)) {
            return false;
        }
        foreach ($filterRz as $k_c => $v_c) {
            $handleImgType = 'update_creative';
            $savePath = SYNC_OFFER_IMAGE_PATH . strtolower($handleImgType) . '/';
            $saveName = $v_c['type'] . '_' . $v_c['adn_offer_id'] . '_' . md5($v_c['uuid'].'_'.time()).'_'.mt_rand(1000000, 9999900).'_'.$v_c['wh_str']. '.' . 'jpg';
            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }
            $rzSaveFile = $savePath . $saveName;
            if (file_exists($$rzSaveFile)) {
                unlink($rzSaveFile);
            }
            //begin down file.
            $downRz = $this->downFile($v_c['url'], $rzSaveFile);
            if (empty($downRz)) { //down fail...
                echo "down file fail uuid: " . $v_c['uuid'] . " type: " . $v_c['type'] ." url: " . $v_c['url'] . " " . date('Y-m-d H:i:s') . "\n";
                continue;
            }
            //begin upload cdn.
            $imgUrl = $this->uploadCdnFile($rzSaveFile);
            $rdsCreativeList = $this->getRdsCreative($v_c['adn_offer_id']);
            $typeExist = $this->checkCreativeTypeExist($rdsCreativeList, $v_c['type']);
            $this->table = 'creative_list';
            if (empty($typeExist)) { //rds insert
                $this->table = 'creative_list';
                $need_creative = array();
                $need_creative['creative_name'] = strtolower($handleImgType).'_'.$v_c['type'].'_'.$v_c['adn_offer_id'].'_'.$v_c['uuid'].'_'.$v_c['wh_str'];
                $need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'], ENT_QUOTES), ENT_QUOTES);
                $need_creative['campaign_id'] = $v_c['adn_offer_id'];
                $need_creative['type'] = $v_c['type']; // 300x300 from gp url
                $need_creative['lang'] = 0;
                $need_creative['height'] = $v_c['height'];
                $need_creative['width'] = $v_c['width'];
                $need_creative['image'] = $imgUrl;
                $need_creative['text'] = '';
                $need_creative['comment'] = '';
                $need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
                $need_creative['timestamp'] = time();
                $need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
                $need_creative['user_id'] = 0; //adn 3s 都是0
                $creativeId = $this->insert($need_creative);
                self::$syncQueueObj->sendQueue($v_c['adn_offer_id'],'',0);
                echo 'insert offerId: '.$v_c['adn_offer_id'].' type: '.$v_c['type'].' '.$v_c['wh_str'].' success '.date('Y-m-d H:i:s')."\n";
            }else{ //rds update
                $conds = array();
                $conds['AND']['campaign_id'] = $v_c['adn_offer_id'];
                $conds['AND']['type'] = $v_c['type'];
                $updateD = array();
                $updateD['image'] = trim($imgUrl);
                $rz = $this->update($updateD,$conds);
                self::$syncQueueObj->sendQueue($v_c['adn_offer_id'],'',0);
                echo "old url: ".$typeExist['image']."\n";
                echo "new url: ".$updateD['image']."\n";
                echo 'update offerId: '.$v_c['adn_offer_id'].' type: '.$v_c['type'].' '.$v_c['wh_str'].' success '.date('Y-m-d H:i:s')."\n";
            }
            //last del img
            if (file_exists($$rzSaveFile)) {
                unlink($rzSaveFile);
            }
        }
    }
    
    public function uploadCdnFile($rzSaveFile){
        if(!file_exists($rzSaveFile)){
            echo "uploadCdnFile rzSaveFile not exists ".date('Y-m-d H:i:s')."\n";
            return false;
        }
        $rz = self::$imageObj->remoteCopy($rzSaveFile);
        if ($rz['code'] != 1){
            echo "uploadCdnFile Image sync fail ".date('Y-m-d H:i:s')."\n";
            return false;
        }
        $image_url = $rz['url'];
        if(file_exists($rzSaveFile)){
            unlink($rzSaveFile);
        }
        if(!CommonSyncHelper::checkUrlIfRight($image_url)){
            return false;
        }
        return $image_url;
    }
    
    public function downFile($downUrl, $rzSaveFile){
        if (!CommonSyncHelper::checkUrlIfRight($downUrl)) {
            echo "url error \n";
            return false;
        }     
        $img_c = 0;
        while (1) {
            $rz = self::$imageObj->download_remote_file_with_curl($downUrl, $rzSaveFile);
            if ($rz) {
                break;
            }
            if ($img_c >= 5) {
                break;
            } else {
                $img_c ++;
            }
        }
        return $rz;
    }
    
    public function filterSameAsCache($apiRz,$uuidMapOfferId,$uuidMapPlatform,$uuidMapTraceAppId){
        $apiRz = json_decode($apiRz,true);
        if(!is_array($apiRz)){
            echo "filterSameAsCache => api result not array\n";
            return false;
        }
        $filterApiRz = array();
        foreach ($apiRz as $k_uuid => $v_uuid){ 
            if(empty($uuidMapOfferId[$v_uuid['uuid']])){
                echo "3s uuid: ".$v_uuid['uuid']." map no adn offerId error ".date('Y-m-d H:i:s')."\n";
                continue;
            }
            if(empty($v_uuid['creative_images'])){
                echo "3s uuid: ".$v_uuid['uuid']." creative_images null error ".date('Y-m-d H:i:s')."\n";
                continue;
            }
            foreach ($v_uuid['creative_images'] as $k_type => $v_type){
                if(empty($v_type['width']) || empty($v_type['height']) || empty($v_type['url'])){
                    echo "uuid: ".$v_uuid['uuid']." had creative width or height or url null error ".date('Y-m-d H:i:s')."\n";
                    continue;
                }
                $whStr = $v_type['width'].'x'.$v_type['height'];
                if(empty(self::$checkTypeArr[$whStr])){
                   echo "creative type: ".$whStr." no config to update uuid: ".$v_uuid['uuid']." ".date('Y-m-d H:i:s')."\n";
                   continue;
                }
                if(!CommonSyncHelper::checkUrlIfRight($v_type['url'])){
                    echo "url format error uuid:".$v_uuid['uuid']." ".$v_type['url']."\n";
                    continue;
                }
                //cache logic begin
                $cache = $this->getCache($uuidMapOfferId[$v_uuid['uuid']],$v_uuid['uuid'],self::$checkTypeArr[$whStr]);
                if(empty($cache) || $cache['url'] != $v_type['url']){
                    $tmpVal = $v_type;
                    $tmpVal['adn_offer_id'] = intval($uuidMapOfferId[$v_uuid['uuid']]);
                    $tmpVal['uuid'] = $v_uuid['uuid'];
                    $tmpVal['wh_str'] = $whStr;
                    $tmpVal['type'] = intval(self::$checkTypeArr[$whStr]);
                    $tmpVal['platform'] = $uuidMapPlatform[$v_uuid['uuid']];
                    $tmpVal['trace_app_id'] = $uuidMapTraceAppId[$v_uuid['uuid']];
                    $filterApiRz[] = $tmpVal;
                    $this->mongoUpsertCache($v_type,$tmpVal);
                    echo "cache diff uuid: ".$v_uuid['uuid']."\n";
                }else{
                    echo "cache same uuid: ".$v_uuid['uuid']."\n";
                }
            }
        }
        return $filterApiRz;
    }
    
    public function getCache($offerId,$uuid,$type){
        if(empty($offerId) || empty($uuid)){
            return false;
        }
        $conds = array();
        $conds['adn_offer_id'] = intval($offerId);
        $conds['uuid'] = $uuid;
        $conds['type'] = intval($type);
        try {
            $rz = $this->mongoSyncCampaign->where($conds)->get(self::$cacheCollection);
        } catch (\Exception $e) {
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get cache fail adn_offer_id: '.$offerId);
            echo $e->getMessage()."\n";
            return false;
        }
        if(empty($rz)){
            return array();
        }
        return $rz[0];
    }
    
    public function mongoUpsertCache($creativeInfo,$tmpVal){
        $data = array();
        $data = $creativeInfo;
        $data['adn_offer_id'] = intval($tmpVal['adn_offer_id']);
        $data['uuid'] = $tmpVal['uuid'];
        $data['type'] = intval($tmpVal['type']);
        $data['wh_str'] = $tmpVal['wh_str'];
        $data['date_created'] = new \MongoDate();
        $conds = array();
        $conds['adn_offer_id'] =$data['adn_offer_id'];
        $conds['uuid'] = $data['uuid'];
        $conds['type'] = $data['type'];
        $options = array();
        $options['upsert'] = true;
        try {
            $this->mongoSyncCampaign->set($data);
            $this->mongoSyncCampaign->where($conds)->update(self::$cacheCollection,$options);
        } catch (\Exception $e) {
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'upsertCache fail adn_offer_id: '.$data['adn_offer_id']);
            echo $e->getMessage()."\n";
            return false;
        }
        return true;
    }

    function checkCreativeTypeExist($creativeInfo,$checkType){
        if(empty($creativeInfo) || empty($checkType)){
            return false;
        }
        if(!empty($creativeInfo)){
            foreach ($creativeInfo as $v){
                if($v['type'] == $checkType){
                    return $v;
                }
            }
        }
        return false;
    }
    
    public function getRdsCreative($offerId){
        if(empty($offerId)){
            return false;
        }
        $this->table = 'creative_list';
        $conds = array();
        $conds['AND']['campaign_id'] = $offerId;
        $conds['AND']['status'] = 1;
        $rz = $this->select('*',$conds);
        return $rz;
    }
    
    public function getSourceOffers($sourceId){
        $this->table = 'campaign_list';
        if(empty($sourceId)){
            echo "source id null error \n";
            return false;
        }
        $fields = array('id','network_cid','name','status','advertiser_id','source','trace_app_id','platform');
        $conds = array();
        $conds['AND']['source'] = $sourceId;
        $conds['AND']['status'] = array(1,4); //1 active 4 pending
        $rz = $this->select($fields,$conds);
        return $rz;
    }
    
    public function getMongoApiConfig($sourceId = '') {
        $conds = array();
        $conds['if_running'] = 1;
        $conds['network'] = 1;
        $conds['user_id'] = 0; //only handle adn 3s offer creative
        if(!empty($sourceId)){
            $conds['source'] = intval($sourceId);
        }
        $rz = $this->mongoNewAdn->where($conds)->get(self::$apiConfCollection);
        return $rz;
    }
    
    
}

set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting( E_ERROR | E_WARNING | E_PARSE);
#error_reporting(E_ALL);
ini_set('display_errors',1);
define('SYNC_OFFER_DEBUG',0);
define ( 'SCRIPT_BEGIN_TIME', CommonSyncHelper::microtime_float () );
define('SYNC_OFFER_IMAGE_PATH',ROOT_DEV_DIR.'/upload_files/solo_sync_image/');
$param = '';
if(!empty($argv[1])){
    $param = $argv[1];
}
$creativeUpdateToolObj = new CreativeUpdateTool ($param);
$creativeUpdateToolObj->run();
CommonSyncHelper::getRunTimeStatus ( SCRIPT_BEGIN_TIME );
echo date ( 'Y-m-d H:i:s' ) . " Run End.\n";

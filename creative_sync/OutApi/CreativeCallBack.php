<?php
require '../Lib/syncInit.php';
use Lib\Core\SyncAction;
use Lib\Core\SyncConf;
use Api\CommonSyncApi;
use Model\CamTouchPalSyncModel;
use Helper\ConvertSyncHelper;
use Model\CamListSyncModel;
use Model\CreativeSyncModel;
use Helper\ImageSyncHelper;
use Helper\NoticeSyncHelper;
use Helper\CommonSyncHelper;
use Helper\SyncQueueSyncHelper;
class CreativeCallBack extends SyncAction{
	
    public static $params = array();
    public static $syncQueueObj;
    public static $creativeSyncModelObj;
    public static $thisRequestUrl;
    public static $logFolder = 'creative_callback';
    public static $needParams = array( //here config get post or get param list
        'adn_camid', //offer_id  
        'adn_user_id', //user_id
        'mb_url', //cdn视频url  video_url  -> image
        'mb_len', //视频时长  video_length
        'mb_tct', //是否截断 video_truncation 
        'mb_size', //视频大小 video_size
        'mb_resolution' //视频分别率  video_resolution
    );
    public static $imageType = 94;
    function __construct(){
        self::$thisRequestUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
	   if(!empty($_REQUEST)){
	       foreach (self::$needParams as $k => $v_field){
	           if(isset($_REQUEST[$v_field])){
	               self::$params[$v_field] = trim($_REQUEST[$v_field]);
	           }else{
	               $this->write(400,'params_'.$v_field."_null_error");
	               exit();
	           }
	       }
	   }else{
	       $this->write(400,'all_params_empty_error');
	       exit();
	   }
	   if(empty(self::$syncQueueObj)){
	       self::$syncQueueObj = new SyncQueueSyncHelper();
	   }
	   if(empty(self::$creativeSyncModelObj)){
	       self::$creativeSyncModelObj = new CreativeSyncModel('CreativeCallBack','CreativeCallBack');
	       self::$creativeSyncModelObj->table = 'creative_list';
	   }
	}
	
	function run(){
	    $rz = $this->upsertLogic();
	    if(!$rz){
	        $this->write(401,'handle_upsert_fail');
	    }elseif($rz == 'update'){
	        $this->write(200,'update_success');
	    }elseif($rz == 'no_need_update'){
	        $this->write(200,'no_need_update');
	    }elseif($rz == 'insert'){
	        $this->write(200,'insert_success');
	    }else{
	        $this->write(402,'not_complete_success');
	    }
	    
	}
	
	function upsertLogic(){
	    $videoCreative = $this->checkHaveRewardVideo(self::$params['adn_camid']);
	    $rz = self::$creativeSyncModelObj->commonApiSaveCreative($videoCreative,self::$params,self::$imageType);
	    return $rz;
	}
	
	/**
	 * 
	 * @param unknown $offer_id
	 * @return boolean|Ambigous <boolean, unknown> false not have
	 */
	function checkHaveRewardVideo($offer_id){
	    $creativeInfo = self::$creativeSyncModelObj->getCreativeTypeInfo($offer_id);
	    $rz = self::$creativeSyncModelObj->checkCreativeTypeExist($creativeInfo, self::$imageType);
	    if(empty($rz)){
	        return false;
	    }
	    return $rz;
	}
	
	function write($status,$msg){    
	    $logInfo = array();
	    $logInfo['time'] = date('Y-m-d H:i:s');
	    $logInfo['status'] = $status;
	    $logInfo['msg'] = $msg;
	    $logInfo['url'] = self::$thisRequestUrl;
	    CommonSyncHelper::commonWriteLog(self::$logFolder,self::$logFolder,$logInfo,'array');
	    $writeArr = array();
	    $writeArr['status'] = $status;
	    $writeArr['msg'] = $msg;
	    echo json_encode($writeArr);
	}
	
}
set_time_limit(0);
if(empty($_REQUEST['debug'])){
    ini_set('display_errors', 0);
}else{
    ini_set('display_errors', 1);
}

ini_set('memory_limit', '256M');
error_reporting(E_ALL);
define('SCRIPT_BEGIN_TIME',CommonSyncHelper::microtime_float());
$obj = new CreativeCallBack();
$obj->run();


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
class CreativeOutPutAction extends SyncAction{
	
}
define('SCRIPT_BEGIN_TIME',CommonSyncHelper::microtime_float());
$SYNC_ANALYSIS_GLOBAL['run_begin_time'] = date('Y-m-d H:i:s');
set_time_limit(0);
$argvArr = $argv;
$offerSource = '';
if(!empty($argvArr[1]) && is_string($argvArr[1])){
    $offerSource = $argvArr[1];
}else{
    echo "param 1 error\n";
}
$isDebug = -1;
if(isset($argvArr[2])){
    if(in_array($argvArr[2], array(0,1))){
        $isDebug = $argvArr[2];
    }else{
        echo "param 2 error (1)\n";
    }
}else{
    echo "param 2 error (2)\n";
} 
if(!empty($offerSource) && $isDebug >= 0){
    ini_set('memory_limit', '5500M');
    define('SYNC_OFFER_SOURCE',$offerSource);
    define('SYNC_OFFER_DEBUG',$isDebug);
    define('SYNC_OFFER_COUNT_LIMIT',0); // 0无限制 , > 0 获取几条
    define('SYNC_OFFER_ONLY_GET_GEO','');
    define('SYNC_OFFER_IMAGE_PATH',ROOT_DEV_DIR.'/upload_files/solo_sync_image/');
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 1);
    $syncObj = new CreativeOutPutAction($offerSource);
    $syncObj->run();
}
echo "-----------------------------------------\n";
echo "Report\n";
CommonSyncHelper::getRunTimeStatus(SCRIPT_BEGIN_TIME);
foreach ($SYNC_ANALYSIS_GLOBAL as $k => $v){
    echo $k.": ".$v."\n";
}
echo "-----------------------------------------\n";
CommonSyncHelper::commonWriteLog('run_time_status_log',strtolower($SYNC_ANALYSIS_GLOBAL['advertiser_name']),$SYNC_ANALYSIS_GLOBAL,'array');
CommonSyncHelper::syncStatus($SYNC_ANALYSIS_GLOBAL);
echo date('Y-m-d H:i:s').": ".$offerSource." offer sync end.\n";


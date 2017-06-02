<?php
namespace Api;
use Lib\Core\SyncApi;
use Lib\Core\SyncConf;
use Helper\CommonSyncHelper;
use Helper\CommonHelper;
use Helper\OfferSyncHelper;
use Model\BeiJinGpInfoMongoModel;
class HandleVideoUrlApi extends SyncApi{
	
    public static $specialApi;
    public static $beiJinGpInfoMongoModel;
	function __construct(){
	    $specialApiArr = SyncConf::getSyncConf('specialApi');
	    if(SYNC_OFFER_DEBUG){
	        self::$specialApi = $specialApiArr['debug'];
	    }else{
	        self::$specialApi = $specialApiArr['online'];
	    }
	    
	}
    
	function handelVideo($offerId,$row,$camInfo,$is3s = true){
	    if(empty($offerId) || empty($camInfo)){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'param offerid or row not set error');
	        return false;
	    }
	    $handleApi = self::$specialApi['handle_video'];
	    $handleCallBack = self::$specialApi['handle_video_callback'];
	    if(empty($handleApi)){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'handle_video api not config');
	        return false;
	    }
	    if(empty($handleCallBack)){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'handle_video_callback api not config');
	        return false;
	    }
	    $videoSource = empty($is3s)?'not3s':'3s';
	    $handleCallBack = $handleCallBack.'?adn_camid='.$offerId.'&adn_user_id='.$camInfo['user_id'].'&videosource='.$videoSource;
	    $mapArr = array('[mb_pkg]','[mb_path]','[mb_os]','[mb_callback]');
	    $idMapPlatformArr = array_flip(getPlatform());
	    $video_creatives = '';
	    if($is3s){
	    	$video_creatives = $row['3s_video_creatives'];
	    }else{
	    	$video_creatives = $row['video_creatives'];
	    }
	    if(empty($video_creatives)){
	    	return false;
	    }
	    $replArr = array($camInfo['trace_app_id'],urlencode($video_creatives),$idMapPlatformArr[$camInfo['platform']],urlencode($handleCallBack));
	    $handleApi = str_replace($mapArr, $replArr, $handleApi);
		$cot = 1;
		$tryLimit = 2;
		while (1){
		    $this->httpCode = '';
		    $this->httpError = '';
			$rz = $this->syncCurlGet($handleApi,0,10);
			$rzArr = json_decode($rz,true);
			$cot ++;
			if($rz !== false){
				if(!empty($rzArr)){
					break;
				}else{
					if($cot > $tryLimit){
						CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'last retry(a) '.$cot.' time get no data');
						break;
					}
					CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, ' retry(a) '.$cot.' time get no data');
				}
			}
			if($cot > $tryLimit){
				CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'last retry(b) '.$cot.' time get no data');
				break;
			}
			
		}
		$successCodeArr = array(200,201);
		if(empty($rzArr) || !in_array($rzArr['code'], $successCodeArr)){
		    $logErrorMessage = array(
		        'time' => date('Y-m-d H:i:s'),
		        'httpcode' => $this->httpCode,
		        'http_error_msg' => $this->httpError,
		    	'type' => empty($is3s)?'not3s':'3s',
		        'result_content' => str_replace("\n", "", $rz),
		        'url' => $handleApi,
		    );
		    CommonSyncHelper::commonWriteLog(strtolower('HandleVideoUrlApi'),strtolower(__FUNCTION__),$logErrorMessage,'array');
		    return false;
		}
		return $rzArr;
	}
}
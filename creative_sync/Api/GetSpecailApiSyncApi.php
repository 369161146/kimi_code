<?php
namespace Api;
use Lib\Core\SyncApi;
use Lib\Core\SyncConf;
use Helper\CommonSyncHelper;
use Helper\CommonHelper;
use Helper\OfferSyncHelper;
use Model\BeiJinGpInfoMongoModel;
class GetSpecailApiSyncApi extends SyncApi{
	
    public static $specialApi;
    public static $beiJinGpInfoMongoModel;
	function __construct(){
	    $specialApiArr = SyncConf::getSyncConf('specialApi');
	    if(SYNC_OFFER_DEBUG){
	        self::$specialApi = $specialApiArr['debug'];
	    }else{
	        self::$specialApi = $specialApiArr['online'];
	    }
	    self::$beiJinGpInfoMongoModel = new BeiJinGpInfoMongoModel();
	}
    
	function getCurlBeiJingGpInfo($trace_app_id){
        if(empty(self::$specialApi['beijin_get_gp_info'])){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'api null error');
            return false;
        }
        if(empty($trace_app_id)){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'trace_app_id null error');
            return false;
        }
        $needArr = array();
        if(!empty($needArr)){
            return $needArr;
        }
        $getRzApi = self::$specialApi['beijin_get_gp_info'].$trace_app_id;
        $logErrorMessage = array(
            'get_gp_info_error_msg' => __CLASS__." ".__FUNCTION__." error api: ".$getRzApi." ".date('Y-m-d H:i:s'),
            'msg' => '',
        );
        $errorFolder = 'get_beijing_gp_info_error_msg';
		$cot = 3;
		$tryLimit = 1;
		while (1){ 
			$rz = $this->syncCurlGet($getRzApi,0,30);
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
		if(empty($rzArr)){
		    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'gp info null,url: '.$getRzApi);
		    $logErrorMessage['msg'] = 'gp info null';
		    CommonSyncHelper::commonWriteLog($errorFolder,strtolower(__FUNCTION__),$logErrorMessage,'array');
			return false;
		}
		$needformat = '>>needformat';  //为需要格式数据标记
		$fieldToAdn = array(
		    #'trace_app_id' => 'packageName',
		    'app_name' => 'appName',
		    'appdesc' => 'appDesc',
		    'startrate' => $needformat, //starRating
		    'appsize' => $needformat, //installationSize
		    'appinstall' => $needformat, //numDownloads
		    'icon' => $needformat, //icon
		    'category' => $needformat, //appType
		    'sub_category' => $needformat, //appCategory
		    'gp_images' => $needformat, //gpImages
		    'big_pic' => $needformat,
		    'new_version' => $needformat, //version
		    'content_rating' => $needformat //contentRating
		);
		$needArr = array();
		foreach ($fieldToAdn as $adn_field => $beijin_field){
		    if($beijin_field == $needformat){
		        if($adn_field == 'startrate'){
		            $needArr[$adn_field] = sprintf('%.1f',$rzArr['starRating']);
		        }elseif($adn_field == 'appsize'){
		            $needArr[$adn_field] = floor($rzArr['installationSize']/1000000);
		        }elseif($adn_field == 'appinstall'){
		            $needArr[$adn_field] = str_replace(array(',','.','+'),array('','',''),$rzArr['numDownloads']);
		        }elseif($adn_field == 'category'){
		            $needArr[$adn_field] = ucfirst(strtolower($rzArr['appType']));
		            if(!in_array($needArr[$adn_field],OfferSyncHelper::app_category())){
		                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get beijin gp api category not in(Game,Application),api: '.$getRzApi);
		                $logErrorMessage['msg'] = 'get beijin gp api category not in(Game,Application)';
		                CommonSyncHelper::commonWriteLog($errorFolder,strtolower(__FUNCTION__),$logErrorMessage,'array');
		                return false;
		            }
		        }elseif($adn_field == 'icon'){
		            if(CommonSyncHelper::checkUrlIfRight($rzArr['icon'])){
		                $needArr[$adn_field] = $rzArr['icon'];
		            }else{
		                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'icon url error,url: '.$rzArr['icon']);
		                $logErrorMessage['msg'] = 'icon url error,icon: '.$rzArr['icon'];
		                CommonSyncHelper::commonWriteLog($errorFolder,strtolower(__FUNCTION__),$logErrorMessage,'array');
		                return false;
		            } 
		        }elseif($adn_field == 'sub_category'){
		            $needArr[$adn_field] = empty($rzArr['appCategory'][0])?'':strtoupper($rzArr['appCategory'][0]);
		        }elseif($adn_field == 'gp_images'){
		            if(empty($rzArr['gpImages'])){
		                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'gpImages null error');
		                $logErrorMessage['msg'] = 'gpImages null error';
		                CommonSyncHelper::commonWriteLog($errorFolder,strtolower(__FUNCTION__),$logErrorMessage,'array');
		                return false;
		            }
		            $rzImages = array();
		            foreach ($rzArr['gpImages'] as $k => $v_url){
		                if(CommonSyncHelper::checkUrlIfRight($v_url)){
		                    $rzImages[] = trim($v_url);
		                }else{
		                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'gpImages index: '.$k.' url error,url: '.$v_url);
		                }
		            }
		            $needArr[$adn_field] = $rzImages;
		        }elseif($adn_field == 'big_pic'){
		            if(CommonSyncHelper::checkUrlIfRight($rzArr['bigJpg'])){
		                $needArr[$adn_field] = trim($rzArr['bigJpg']);
		            }else{
		                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'big image url error,url: '.$rzArr['bigJpg']);
		            }
		        }elseif($adn_field == 'new_version'){
		            $needArr[$adn_field] = empty($rzArr['version'])?'':$rzArr['version'];
		        }elseif($adn_field == 'content_rating'){
		            $needArr[$adn_field] = empty($rzArr['contentRating'])?0:$rzArr['contentRating'];
		        }
		        
		    }else{
		        $needArr[$adn_field] = $rzArr[$beijin_field];
		    }
		}
		//last check
		$checkHaveEmpty = 0;
		foreach ($needArr as $field => $v){
		    if(empty($v)){
		        $logErrorMessage['msg'] = 'field: '.$field.' value empty error';
		        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'field: '.$field.' value empty error');
		    }
		}
		if($checkHaveEmpty){
		   $logErrorMessage['msg'] = 'rz checkHaveEmpty fields';
		   CommonSyncHelper::commonWriteLog($errorFolder,strtolower(__FUNCTION__),$logErrorMessage,'array');
		   return false; 
		}
		$needArr['videoInfo'] = empty($rzArr['videoInfo'])?array():$rzArr['videoInfo'];
		return $needArr;
	}
	
	/**
	 * insert cache logic
	 * @param unknown $cacheInfo
	 * @param unknown $trace_app_id
	 * @return boolean
	 */
	function BeiJinGpInfoToCache($cacheInfo,$trace_app_id){
	    if(empty($cacheInfo)){
	        return false;
	    }
	    try {
	        $rz = self::$beiJinGpInfoMongoModel->insertData($cacheInfo,$trace_app_id);
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'to cache beijin gp info to mongo success',2);
	    } catch (\Exception $e) {
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'insert beijin gp info to mongo error: '.$e->getMessage());
	        return false;
	    }
	    return $rz;
	}
	/**
	 * get cache logic
	 * @return boolean
	 */
	function getBeiJinGpInfoCache($trace_app_id){
	    if(empty($trace_app_id)){
	        return false;
	    }
	    try {
	        $rz = self::$beiJinGpInfoMongoModel->selectData($trace_app_id);
	    } catch (\Exception $e) {
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get beijin gp info mongo cache error: '.$e->getMessage());
	        return false;
	    }
	    if(empty($rz)){
	       return false;
	    }
	    #CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get cache beijin gp mongo info success');
	    return $rz;
	}
	
	function getCurlAdnGpInfo($trace_app_id,$ifgetcontent = 0){
	    if(empty(self::$specialApi['adn_get_gp_info'])){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'api null error');
	        return false;
	    }
	    if(empty($trace_app_id)){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'trace_app_id null error');
	        return false;
	    }
	    $url = 'https://play.google.com/store/apps/details?id='.$trace_app_id;
	    if($ifgetcontent){
	        $url = 'https://play.google.com/store/apps/details?id='.$trace_app_id.'&ifgetcontent=1';
	    }
	    $getRzApi = self::$specialApi['adn_get_gp_info'].$url;
	    $cot = 3;
	    $tryLimit = 1;
	    while (1){
	        $seepTime = rand(1,3);
	        sleep($seepTime);
	        $rz = $this->syncCurlGet($getRzApi,30);
	        $rzArr = json_decode($rz,true);
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
	        $cot ++;
	    }
	    
	    if(!empty($rzArr['msg']) || empty($rzArr)){
	        $logMessage = array(
	            'get_gp_info_error_msg' => __CLASS__." ".__FUNCTION__."error api: ".$getRzApi." ".date('Y-m-d H:i:s'),
	        );
	        CommonSyncHelper::commonWriteLog('get_adn_gp_info_error_msg',strtolower(__FUNCTION__),$logMessage,'array');
	        return false;
	    }
	    $needField = array(
	        #'trace_app_id',
	        'app_name',
	        'appdesc',
	        'startrate',
	        'appsize',
	        'appinstall',
	        'icon',
	        'category',
	        'sub_category',
	    );
	    $needArr = array();
	    foreach ($needField as $field){
	         switch ($field){
	             case 'category':
	                 if(in_array($rzArr[$field], OfferSyncHelper::app_category())){
	                     $needArr[$field] = $rzArr[$field];
	                 }else{
	                     CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get adn gp api category not in(Game,Application),api: '.$getRzApi);
	                     return false;
	                 }
	                 break;
	             case 'icon':
	                 if(CommonSyncHelper::checkUrlIfRight($rzArr[$field])){
	                     $needArr[$field] = $rzArr[$field];
	                 }else{
	                     CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'icon url error,url '.$rzArr['icon']);
	                     return false;
	                 }
	                 break;
	             default:
	                 $needArr[$field] = $rzArr[$field];    
	         }
	    }
	    //last check
	    $checkHaveEmpty = 0;
	    foreach ($needField as $field){
	        if(empty($needArr[$field])){
	            $checkHaveEmpty = 1;
	            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'field: '.$field.' value empty error');
	        }
	    }
	    if($checkHaveEmpty){
	        return false;
	    }
	    if($ifgetcontent){
	        $needArr['content'] = $rzArr['content'];
	    }
	    return $needArr;
	}

}
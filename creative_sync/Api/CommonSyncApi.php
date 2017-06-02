<?php
namespace Api;
use Lib\Core\SyncApi;
use Lib\Core\SyncConf;
use Helper\AdwaysSyncApiHelper;
use Helper\CommonSyncHelper;
class CommonSyncApi extends SyncApi{
	
	private $api = '';
	private $keyArr = array();
	public static $offerSource;
	public static $subNetWork;
	public static $syncConf = array();
	
	function __construct($api,$offerSource,$subNetWork,$keyArr = array()){
		$this->api = $api;
		$this->keyArr = array();
		self::$offerSource = $offerSource;
		self::$subNetWork = $subNetWork;
		
		$apiConf = SyncConf::getSyncConf('apiSync');
		self::$syncConf = $apiConf[self::$offerSource][self::$subNetWork];
	}
    
	/**
	 * api处理层统一入口逻辑(新版重构通用入口)
	 */
	function commonHandleApi(){
	    $advertiserName = CommonSyncHelper::getAdvertiserObjName(self::$subNetWork);
	    if(empty($advertiserName)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get Advertier Obj name fail.');
	    }
	    $advertiserObj =  new $advertiserName(self::$offerSource,self::$subNetWork);
	    $rzApiData = $advertiserObj->getApiDataLogic();
	    $this->advertiserApiHttpCode = $advertiserObj->advertiserApiHttpCode;
	    
	    return $rzApiData;
	}
	
	function getCurlData_Common3s_3s(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$maxCotWhile = 3;
		$cotWhile = 0;
		while (1){
			$max_page = 2;
			$apiMerge = array();
			
			$cotFor = 0;
			for ($currentPage=1;$currentPage<=$max_page;$currentPage++){
				$newUrl = $url.'&page='.$currentPage;
				$singleUrlRetry = 0;
				while(1){
					$rz = $this->syncCurlGet($newUrl,0);
                    $this->advertiserApiHttpCode = $this->httpCode;
					$rzArr = json_decode($rz,true);
					if($rz === false){
						echo "Error: to singleUrlRetry ".__FUNCTION__." get data fail url is : ".$newUrl." retry num is: ".$singleUrlRetry."\n";
					}elseif(empty($rzArr['offers'])){
					    echo "Error: to singleUrlRetry ".__FUNCTION__." offers data fail url is : ".$newUrl." retry num is: ".$singleUrlRetry."\n";
					}else{
						break;
					}
					$singleUrlRetry ++;
					if($singleUrlRetry >=3){
						break;
					}
				}
				if($rz === false || empty($rzArr['offers'])){
					echo "Error: ".__FUNCTION__." get data fail url is : ".$newUrl." and retry ".$singleUrlRetry." time fail.\n";
					break;
				}
				if(empty($cotFor)){
					if(empty($rzArr['total']) || empty($rzArr['pagesize'])){
						echo "Error: get 3s api total param value or pagesize value empty error to stop.\n";
						break;
					}else{
						$max_page = ceil($rzArr['total']/$rzArr['pagesize']);
					}
					if(empty($max_page)){
						echo "Error: get 3s api max_page value is null to stop.\n";
						break;
					}
					echo "max_page is: ".$max_page."\n";
					echo "total_offers_num is: ".$rzArr['total']."\n";
					$apiMerge = $rzArr['offers'];
				}else{
					$apiMerge = array_merge($apiMerge,$rzArr['offers']);
				}
				echo '3s request api: url is : '.$newUrl."\n";
				$cotFor ++;
			}
			if(!empty($apiMerge)){
				break;
			}
			if($cotWhile >= $maxCotWhile){
				echo "Error: ".__FUNCTION__." get data retry ".$cotWhile." time fail to stop sync jop.\n";
				return false;
			}
			$cotWhile ++;
		}
		return $apiMerge;
	}
	
	function getCurlData_CommonMobVista_MobVista(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 1;
		$rzArr = array();
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false && !empty($rzArr['campaigns'])){
				break;
			}else{
			    echo "Error getCurlData_CommonMobVista_MobVista(".self::$offerSource."_".self::$subNetWork.") api retry ".$cot." time but fail error.\n";
			}
			if($cot > 10){
				echo "Error getCurlData_CommonMobVista_MobVista(".self::$offerSource."_".self::$subNetWork.") api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		return $rzArr['campaigns'];
	}
	
	function getCurlData_CommonAvazu_Avazu(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 1;
		$rzArr = array();
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false && !empty($rzArr['ads']['ad'])){
				break;
			}else{
			    echo "Error offer Source is: ".self::$offerSource." getCurlData_CommonAvazu_Avazu curl api retry ".$cot." time but fail error.\n";
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." getCurlData_CommonAvazu_Avazu curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		return $rzArr['ads']['ad'];
	}
	
	function getCurlData_CommonPubNative_PubNative(){
		
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 0;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false && !empty($rzArr)){
				break;
			}else{
			    echo date('Y-m-d H:i:s')." PubNative Api Fail Json Is: ".$rz."\n";
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." getCurlData_CommonPubNative_PubNative curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		if(empty($rzArr)){
			return false;
		}
		$forMat = array();
		foreach($rzArr as $v){
			foreach ($v['campaigns'] as $vv){
				$campaign = $v['app_details'];
				$campaign = array_merge($campaign,$vv);
				$campaign['creatives'] = $v['creatives'];
				$forMat[] = $campaign;
			}
		}
		return $forMat;
		
	}
	
	function getCurlData_CommonGlispa_Glispa(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 0;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false){
				if(!empty($rzArr['data'])){
					break;
				}else{
					if($cot > 10){
						echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time api can connect but field data is null error.\n";
						break;
					}
					echo "data null to retry ".$cot." time.\n";
					sleep(2);
				}
			}else{
				if($cot > 10){
					echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
					break;
				}
			}
			$cot ++;
		}
		return $rzArr['data'];
	}
	
	function getCurlData_CommonMobileCore_MobileCore(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 0;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false){
				if(!empty($rzArr['ads'])){
					break;
				}else{
					if($cot > 10){
						echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time api can connect but field data is null error.\n";
						break;
					}
					echo "ads null to retry ".$cot." time.\n";
					sleep(2);
				}
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		
		if(empty($rzArr['ads'])){
			echo "ads null rz is: \n";
			var_dump($rzArr);
			echo "rz json is: ".$rz."\n";
		}
		return $rzArr['ads'];
	}
	
	function getCurlData_CommonAppia_Appia(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$siteid = self::$syncConf['siteid'];
		$appia_account = self::$syncConf['appia_account'];
		$appia_pw = self::$syncConf['appia_pw'];
		$url = $url.$siteid;
		$creds = $appia_account.':'.$appia_pw;
		$cot = 0;
		while (1){
			$apiMerge = array();
			$limit = 25;
			$totalRecords = $limit; //初始值
			$timeOut = 0;
			$timeOutMax = 300;
			for($offset=0;$offset < $totalRecords;$offset = $offset + $limit){
				$newUrl = $url.'&offset='.$offset.'&limit='.$limit;
				$rz = $this->syncCurlGet_Appia($newUrl,$creds);
				$this->advertiserApiHttpCode = $this->httpCode;
				if($rz === false){
					echo "Error: ".__FUNCTION__." get data fail url is : ".$url."\n";
				}else{
						
					$rzArr = json_decode($rz,true);
					if(empty($offset)){
						if(!empty($rzArr['totalRecords'])){
							$totalRecords = $rzArr['totalRecords'];
						}
						echo 'Appia request api: totalRecords is : '.$rzArr['totalRecords']."\n";
					}
					$items = $rzArr['items'];
					if(!empty($items)){
						$apiMerge = array_merge($apiMerge,$items);
					}
				}
				echo 'Appia request api: url is : '.$newUrl."\n";
				$timeOut ++;
				if($timeOut > $timeOutMax){
					echo "Error: ".__FUNCTION__." Time out over ".$timeOutMax."\n";
					return false;
				}
			}
			if(!empty($apiMerge)){
				break;
			}
			if($cot > 3){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 3 time but fail error.\n";
				return false;
			}
			$cot ++;
		}
		return $apiMerge;
	}
	
	function getCurlData_CommonSupersonic_Supersonic(){

		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 1;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false && !empty($rzArr['offers'])){
				break;
			}else{
			    echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over ".$cot." time but fail error.\n";
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		return $rzArr['offers'];
		
	}
	
	function getCurlData_CommonAppNext_AppNext(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 1;
		$rzArr = array();
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false && !empty($rzArr['apps'])){
				break;
			}else{
			    echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over ".$cot." time but fail error.\n";
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		return $rzArr['apps'];
	}
	
	function getCurlData_CommonApplift_Applift(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 0;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			if($rz !== false){
				break;
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		$rzArr = json_decode($rz,true);
		if(empty($rzArr)){
			return false;
		}
		$forMat = array();
		foreach($rzArr as $v){
			foreach ($v['campaigns'] as $vv){
				$campaign = $v['app_details'];
				$campaign = array_merge($campaign,$vv);
				$campaign['creatives'] = $v['creatives'];
				$forMat[] = $campaign;
			}
		}
		return $forMat;
		
	}
	
	function getCurlData_CommonAppcoach_Appcoach(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$siteid = self::$syncConf['siteid'];
		$token = self::$syncConf['token'];
		$api = self::$syncConf['api'];
		$cot = 1;
		while (1){
			$apiMerge = array();
			$limit = 100;
			$totalRecords = $limit; //初始值 
			if(strtolower(self::$syncConf['only_platform']) == 'ios'){
			    $pf = 'ios';
			}else{
			    $pf = 'android';
			}
			#$pf = 'ios';
			$timeOut = 0;
			$timeOutMax = 300;
			for($offset=0;$offset < $totalRecords;$offset = $offset + $limit){
				$subRequestUrlRetry = 0;
				while(1){
				    $ts = time();
				    $sign = md5("GET/v1/getads".$token."limit=".$limit."&offset=0&pf=".$pf."&restype=json&siteid=".$siteid."&ts=".$ts);
				    $diffValue = $totalRecords - $offset;
				    if($diffValue < 100){
				        //special logic
				        $newUrl = str_replace(array('[offset]','[limit]','[siteid]','[pf]','[ts]','[sign]'), array($offset,$diffValue,$siteid,$pf,$ts,$sign), $api);
				        //special logic end.
				    }else{
				        $newUrl = str_replace(array('[offset]','[limit]','[siteid]','[pf]','[ts]','[sign]'), array($offset,$limit,$siteid,$pf,$ts,$sign), $api);
				    }
				    $rz = $this->syncCurlGet($newUrl,0);
				    $this->advertiserApiHttpCode = $this->httpCode;
				    $rzArr = json_decode($rz,true);
				    $subRequestUrlRetry ++;
				    if($rz === false){
				        echo "Error: to subRequestUrlRetry ".__FUNCTION__." get data fail url is : ".$newUrl." retry num is: ".$subRequestUrlRetry."\n";
				    }elseif(empty($rzArr['ads'])){
				        echo "Error: to subRequestUrlRetry ".__FUNCTION__." ads null fail url is : ".$newUrl." retry num is: ".$subRequestUrlRetry."\n";
				    }else{
				        break;
				    }
				    if($subRequestUrlRetry >=3){
				        break;
				    }
				}
				if($rz === false){
				    $apiMerge = array();
					echo "Error: ".__FUNCTION__." get data fail url is : ".$newUrl." and subRequestUrlRetry retry ".$subRequestUrlRetry." time fail.\n";
				    break;
				}elseif(empty($rzArr['ads'])){
				    $apiMerge = array();
				    echo "Error: ".__FUNCTION__." ads null fail url is : ".$newUrl." and subRequestUrlRetry retry ".$subRequestUrlRetry." time fail.\n";
				    break;
				}else{
					if(empty($offset)){
						if(!empty($rzArr['total_records'])){
							$totalRecords = $rzArr['total_records'];
						}
						echo 'Appcoach request api: total_records is : '.$rzArr['total_records']."\n";
					}
					$items = $rzArr['ads'];
					if(!empty($items)){
						$apiMerge = array_merge($apiMerge,$items);
					}
				}
				echo 'Appcoach request api: url is : '.$newUrl."\n";
				$timeOut ++;
				if($timeOut > $timeOutMax){
					echo "Error: ".__FUNCTION__." Time out over ".$timeOutMax."\n";
					return false;
				}
			}
			if(!empty($apiMerge)){
				break;
			}
			if($cot > 5){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over ".$cot." time but fail error.\n";
				return false;
			}
			$cot ++;
		}
		return $apiMerge;
	}
	
	function getCurlData_CommonTabatoo_Tabatoo(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 0;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false){
				if(!empty($rzArr['offers'])){
					break;
				}else{
					if($cot > 10){
						echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time api can connect but field data is null error.\n";
						break;
					}
					echo "ads null to retry ".$cot." time.\n";
					sleep(2);
				}
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		
		if(empty($rzArr['offers'])){
			echo "ads null rz is: \n";
			var_dump($rzArr);
			echo self::$offerSource." ".__FUNCTION__." rz json is: ".$rz."\n";
		}
		return $rzArr['offers'];
	}
	
	function getCurlData_CommonArtofclick_Artofclick(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 0;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false){
				if(!empty($rzArr['offers'])){
					break;
				}else{
					if($cot > 10){
						echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time api can connect but field data is null error.\n";
						break;
					}
					echo "offers null to retry ".$cot." time.\n";
					sleep(2);
				}
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		
		if(empty($rzArr['offers'])){
			echo "response-offers null rz is: \n";
			var_dump($rzArr);
			echo "rz json is: ".$rz."\n";
		}
		return $rzArr['offers'];
	}
	
	function getCurlData_CommonMotivefeed_Motivefeed(){
		if(!empty($this->keyArr)){
			$buildParam = http_build_query($this->keyArr);
			$url = $this->api.$buildParam;
		}else{
			$url = $this->api;
		}
		$cot = 0;
		while (1){
			$rz = $this->syncCurlGet($url,0);
			$this->advertiserApiHttpCode = $this->httpCode;
			$rzArr = json_decode($rz,true);
			if($rz !== false){
				if(!empty($rzArr['campaigns'])){
					break;
				}else{
					if($cot > 10){
						echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time api can connect but field data is null error.\n";
						break;
					}
					echo "ads null to retry ".$cot." time.\n";
					sleep(2);
				}
			}
			if($cot > 10){
				echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
				break;
			}
			$cot ++;
		}
		
		if(empty($rzArr['campaigns'])){
			echo "ads null rz is: \n";
			var_dump($rzArr);
			echo "rz json is: ".$rz."\n";
		}
		return $rzArr['campaigns'];
	}
	
	function getCurlData_CommonAdways_Adways(){
	    if(!empty($this->keyArr)){
	        $buildParam = http_build_query($this->keyArr);
	        $url = $this->api.$buildParam;
	    }else{
	        $url = $this->api;
	    }
	    $adwaysObj = new AdwaysSyncApiHelper(self::$offerSource,self::$subNetWork);
	    $cot = 0;
	    $rzArr = array();
	    while (1){
	        $rzArr = $adwaysObj->adwaysGetXmlRun();
	        if(empty($rzArr)){
	            if($cot > 3){
	                echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." adways curl simplexml_load_file api retry over ".$cot." time but fail error.\n";
	                break;
	            }else{
	                echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." adways curl simplexml_load_file api retry over ".$cot." time to retry...\n";
	            }
	        }else{
	            break;
	        }
	        $cot ++;
	    }
	    return $rzArr;
	}
	
	function getCurlData_CommonYouAppi_YouAppi(){
	    if(!empty($this->keyArr)){
	        $buildParam = http_build_query($this->keyArr);
	        $url = $this->api.$buildParam;
	    }else{
	        $url = $this->api;
	    }
	    $cot = 0;
	    while (1){
	        $rz = $this->syncCurlGet($url,0);
	        $this->advertiserApiHttpCode = $this->httpCode;
	        $rzArr = json_decode($rz,true);
	        if($rz !== false){
	            if(!empty($rzArr['data'])){
	                break;
	            }else{
	                if($cot > 10){
	                    echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time api can connect but field data is null error.\n";
	                    break;
	                }
	                echo "ads null to retry ".$cot." time.\n";
	                sleep(2);
	            }
	        }
	        if($cot > 10){
	            echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
	            break;
	        }
	        $cot ++;
	    }
	    
	    if(empty($rzArr['data'])){
	        echo "ads null rz is: \n";
	        var_dump($rzArr);
	        echo "rz json is: ".$rz."\n";
	    }
	    return $rzArr['data'];
	    
	}
        
        function getCurlData_CommonRingtonePartner_RingtonePartner() {
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet($url, 0);
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr["trackers"]["tracker"])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr["trackers"]["tracker"])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
            }
            return $rzArr["trackers"]["tracker"];
        }
        
        function getCurlData_CommonAffle_Affle() {
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet($url, 0);
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr["data"])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr["data"])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
            }
            return $rzArr["data"];
        }
        
        function getCurlData_CommonNewStartapp_NewStartapp() {
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet($url, 0);
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr["campaigns"])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr["campaigns"])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
            }
            return $rzArr["campaigns"];
        }
        
        function getCurlData_CommonTaptica_Taptica() {
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet($url, 0);
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr["Data"])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr["Data"])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
            }
            return $rzArr["Data"];
        }
        
        function getCurlData_CommonInstal_Instal() {
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet_Instal($url, array("Authorization: Token 47ef91b0a9bab372a0fb5680678f78571ecba1c9"));
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr["results"])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr["results"])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
            }
            return $rzArr["results"];
        }
        
        function getCurlData_CommonAdxmi_Adxmi() {
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet($url, 0);
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr["offers"])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr["offers"])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
            }
            return $rzArr["offers"];
        }
        
        function getCurlData_CommonNew3s_New3s(){
            if(!empty($this->keyArr)){
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api.$buildParam;
            }else{
                $url = $this->api;
            }
            $maxCotWhile = 3;
            $cotWhile = 0;
            while (1){
                $max_page = 2;
                $apiMerge = array();
                $cotFor = 0;
                for ($currentPage=1;$currentPage<=$max_page;$currentPage++){
                    $newUrl = $url.'&page='.$currentPage;
                    $singleUrlRetry = 0;
                    while(1){
                        $rz = $this->syncCurlGet($newUrl,0);
                        $this->advertiserApiHttpCode = $this->httpCode;
                        $rzArr = json_decode($rz,true);
                        if($rz === false){
                            echo "Error: to singleUrlRetry ".__FUNCTION__." get data fail url is : ".$newUrl." retry num is: ".$singleUrlRetry."\n";
                        }elseif(empty($rzArr['offers'])){
                            echo "Error: to singleUrlRetry ".__FUNCTION__." offers data fail url is : ".$newUrl." retry num is: ".$singleUrlRetry."\n";
                        }else{
                            break;
                        }
                        $singleUrlRetry ++;
                        if($singleUrlRetry >=3){
                            break;
                        }
                    }
                    if($rz === false || empty($rzArr['offers'])){
                        echo "Error: ".__FUNCTION__." get data fail url is : ".$newUrl." and retry ".$singleUrlRetry." time fail.\n";
                        break;
                    }
                    if(empty($cotFor)){
                        if(empty($rzArr['total_offers_num'])){
                            echo "Error: get 3s api total_offers_num param value empty error to stop.\n";
                            break;
                        }else{
                            $max_page = $rzArr['max_page'];
                        }
                        if(empty($max_page)){
                            echo "Error: get 3s api max_page value is null to stop.\n";
                            break;
                        }
                        echo "max_page is: ".$max_page."\n";
                        echo "total_offers_num is: ".$rzArr['total_offers_num']."\n";
                        $apiMerge = $rzArr['offers'];
                    }else{
                        $apiMerge = array_merge($apiMerge,$rzArr['offers']);
                    }
                    echo '3s request api: url is : '.$newUrl."\n";
                    $cotFor ++;
                }
                if(!empty($apiMerge)){
                    break;
                }
                if($cotWhile >= $maxCotWhile){
                    echo "Error: ".__FUNCTION__." get data retry ".$cotWhile." time fail to stop sync jop.\n";
                    return false;
                }
                $cotWhile ++;
            }
            return $apiMerge;
        }
        
        function getCurlData_CommonMobpartner_Mobpartner(){
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet($url, 0);
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr['service']['campaigns']['campaign'])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr['service']['campaigns']['campaign'])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
                return array();
            }
            
            /*按照国家拆单*/
            $new_data = array();
            foreach ($rzArr['service']['campaigns']['campaign'] as $k => $campaign) {
                //一个action信息
                $action = array();
                //存在target国家的code
                $country_check = array();
                foreach ($campaign['actions']['action'] as $act) {
                    if (strtolower($act['type']) == "cpi") {
                        $action = $act;
                        break;
                    }
                }
                if ($action && isset($action['targets']['target']) && is_array($action['targets']['target'])) {
                    $campaign_id = $campaign['id'];
                    $new_action = $action;
                    //按投放国家拆分
                    foreach ($action['targets']['target'] as $target) {
                        //target 只为一个国家对应的信息
                        $new_action['targets']['target'] = $target;
                        $campaign['actions']['action'] = $new_action;
                        //开始组装一个新单子
                        if ($target['country'] && is_string($target['country']) && strlen($target['country']) <= 3) {
                            $campaign['id'] = $campaign_id . "_" . strtoupper($target['country']);
                            if (!in_array($campaign['id'], $country_check)) {
                                $new_data[] = $campaign;
                                $country_check[] = $campaign['id'];
                            }
                        }
                    }
                }
            }
            
            return $new_data;
        }
        
        function getCurlData_CommonDisplay_Display() {
            if (!empty($this->keyArr)) {
                $buildParam = http_build_query($this->keyArr);
                $url = $this->api . $buildParam;
            } else {
                $url = $this->api;
            }
            $cot = 0;
            while (1) {
                $rz = $this->syncCurlGet($url, 0);
                $this->advertiserApiHttpCode = $this->httpCode;
                $rzArr = json_decode($rz, true);
                if ($rz !== false) {
                    if (!empty($rzArr["data"])) {
                        break;
                    } else {
                        if ($cot > 10) {
                            echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error.\n";
                            break;
                        }
                        echo "offers null to retry " . $cot . " time.\n";
                        sleep(2);
                    }
                }
                if ($cot > 10) {
                    echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time but fail error.\n";
                    break;
                }
                $cot ++;
            }

            if (empty($rzArr["data"])) {
                echo "response-offers null rz is: \n";
                var_dump($rzArr);
                echo "rz json is: " . $rz . "\n";
            }
            return $rzArr["data"];
        }
}
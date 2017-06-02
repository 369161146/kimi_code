<?php
namespace Helper;
use Lib\Core\SyncHelper;
use Lib\Core\SyncConf;
use Helper\OfferSyncHelper;
use Lib\Core\SyncApi;
use Model\SyncStatusMongoModel;
use Helper\NoticeSyncHelper;
use Api\CommonHelperSyncApi;
class CommonSyncHelper extends SyncHelper{
	
	public static $geoArr = array();
	public static $getUrlArr;
	public static $syncLogPath;
	
	function __construct(){
		$systemConf = SyncConf::getSyncConf ( 'system' );
		$this->send_mail_status = $systemConf ['email']['send_mail_status'];
		self::$syncLogPath = $systemConf['sync_log_path'];
	}
	
	function get3GeoTo2Geo(){
		$geoFile = '../Public/geo/json_3geo_to_2geo_ISO_3166-1_alpha.txt';
		if(empty(self::$geoArr)){
			if(file_exists($geoFile)){
				$geoJson = file_get_contents($geoFile);
				$geoArr = json_decode($geoJson,true);
				return $geoArr;
			}else{
				echo "Error : get geo json file fail. \n";
				return false;
			}
		}else{
			return self::$geoArr;
		}
	}
	
	static function memory_usage($type = 'memory_get_usage') {
		if($type == 'memory_get_usage'){ //当前内存使用
			$mem_usage = memory_get_usage(true);
			if ($mem_usage < 1024){
				return $mem_usage."B";
			}elseif ($mem_usage < 1048576){
				return round($mem_usage/1024,2). "K";
			}else{
				return round($mem_usage /1048576,2)."M" ;
			}
		}elseif($type == 'memory_get_peak_usage'){ //内存峰值
			$mem_usage = memory_get_peak_usage(true);
			if ($mem_usage < 1024){
				return $mem_usage."B";
			}elseif ($mem_usage < 1048576){
				return round($mem_usage/1024,2). "K";
			}else{
				return round($mem_usage /1048576,2)."M" ;
			}
		}else{
			return false;
		}
	}
	
	function sync_getrusage(){
		return getrusage();
	}
	
	public static function microtime_float(){
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
	
	public static function getRunTime($beginTime){
		return self::microtime_float() - $beginTime;
	}
	/**
	 * 获取运行状态信息
	 */
	public static function getRunTimeStatus($beginTime){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $runTime = self::getRunTime($beginTime);
	    $thisTime = date('Y-m-d H:i:s');
	    $memory_get_peak_usage = self::memory_usage('memory_get_peak_usage');
	    $memory_get_usage = self::memory_usage('memory_get_usage');
		echo "-----------------------------------------".PHP_EOL;
		echo 'show run status time: '.$thisTime.PHP_EOL;;
		echo 'memory_get_peak_usage: '.$memory_get_peak_usage.PHP_EOL;;
		echo 'memory_get_usage: '.$memory_get_usage.PHP_EOL;;
		echo 'run time: '.$runTime."s".PHP_EOL;;
		echo "-----------------------------------------".PHP_EOL;
		$SYNC_ANALYSIS_GLOBAL['run_time'] = floor($runTime);
		$SYNC_ANALYSIS_GLOBAL['run_end_time'] = $thisTime;
		$SYNC_ANALYSIS_GLOBAL['memory_get_peak_usage'] = $memory_get_peak_usage;
		$SYNC_ANALYSIS_GLOBAL['memory_get_usage'] = $memory_get_usage;
		
	}
	
	/**
	 * 获取当前内存状况
	 */
	public static function getCurrentMemory($message = 'Normal'){
		$memory_get_peak_usage = self::memory_usage('memory_get_peak_usage');
		$memory_get_usage = self::memory_usage('memory_get_usage');
		echo "-----------------------------------------".PHP_EOL;;
		echo 'info: '.$message.' time: '.date('Y-m-d H:i:s').PHP_EOL;
		echo 'memory_get_peak_usage: '.$memory_get_peak_usage.PHP_EOL;
		echo 'memory_get_usage: '.$memory_get_usage.PHP_EOL;
		echo "-----------------------------------------".PHP_EOL;
	
	}
	
	public static function getPhpLoadFiles(){
		$files = get_included_files();
		echo "Load Files :......\n";
		foreach ($files as $k => $v){
			echo $v."\n";
		}
		echo "Load Files Count: ".count($files)."\n";
		echo "Load Files :......end\n";
	}
	
	public static function traceOfferUrlInfo($url,$ip){
		if(empty($url) || empty($ip)){
			return false;
		}
		
		if(empty($url)){
			$url = 'http://media.yemonisoni.com/get?t=s&aff_id=27913&id=101227&fos=Android&uts=1436952610';
		}
		$ssl = substr($url, 0, 8) == "https://" ? true : false;
	
		$header = array ();
		$header [] = 'CLIENT-IP: '.$ip;
		$header [] = 'X-FORWARDED-FOR: '.$ip;
		$header [] = 'User-Agent: Opera/9.80 (Android; Opera Mini/7.5.32193/36.2592; U; en) Presto/2.12.423 Version/12.16';
		//$header [] = 'Mozilla/5.0 (Linux; Android 4.4.4; SM-A500F Build/KTU84P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.78 Mobile Safari/537.36 OPR/30.0.1856.93524';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT,10);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //后面的跳转会继续跟踪访问，而且cookie在header里面被保留了下来。
		curl_setopt($ch, CURLOPT_MAXREDIRS, 20);  //设置最多的HTTP重定向的数量
	
		if($ssl){
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		$file_content = curl_exec($ch);
		if (curl_errno($ch)) {
			print "Error traceOfferUrlInfo : " . curl_error($ch)."\n";
			curl_close($ch);
			return false;
		}
		$headers = curl_getinfo($ch);
		#var_dump($headers);
		curl_close($ch);
		if(!empty($headers['redirect_url'])){
			self::$getUrlArr[] = $headers['redirect_url'];
			if(substr($headers['redirect_url'], 0, 9) == "market://"){
				return self::$getUrlArr;
			}
			$this->curlGetInfo($headers['redirect_url'],$ip);
			return self::$getUrlArr;
		}
		return self::$getUrlArr;
	}
	
	public static function getGeoIpFromRedShift(){
	
		$logFile = '../Public/geo_map_ip/GeoMapArr.txt';
		$geoFile = '../Public/geo/json_3geo_to_2geo_ISO_3166-1_alpha.txt';
		if(file_exists($logFile)){
			$rzIpMap = file_get_contents($logFile);
			$rzIpMap = json_decode($rzIpMap,true);
			return $rzIpMap;
		}else{
			return false;
		}
	}
	
	/**
	 * use by adn to 3s api version logic new
	 * @param unknown $min
	 * @param unknown $max
	 * @param unknown $osType
	 * @return boolean|string
	 */
	public static function getOsVersion($row,$subNetWork){
		if(empty($subNetWork)){
			self::syncEcho(__CLASS__,__FUNCTION__,'param subNetWork null error',2);
			return false;
		}
		if(empty($row['platform'])){
			echo "Error: CommonSyncHelper->getOsVersion platform null error.\n";
			return false;
		}	
		//minOSVersion
		if(!empty($row['min_version'])){
			$minOSVersion = $row['min_version'];
		}else{
			$minOSVersion = empty($row['minOSVersion'])?'':$row['minOSVersion'];
		}
		//maxOSVersion
		if(!empty($row['max_version'])){
			$maxOSVersion = $row['max_version'];
		}else{
			$maxOSVersion = '';
		}
		$versionConf = '';
		if(strtolower($row['platform']) == 'android'){
			if(empty($minOSVersion)){
				$minOSVersion = '1.1';
			}
			$versionConf = OfferSyncHelper::android_versions();
		}elseif(strtolower($row['platform']) == 'ios'){
			if(empty($minOSVersion)){
				$minOSVersion = '2.0';
			}
			$versionConf = OfferSyncHelper::ios_versions();
		}
		if(empty($versionConf)){
			return false;
		}
		$newVersion = array();
		if(!empty($minOSVersion) && !in_array($minOSVersion, $versionConf)){
			$newVersion[] = $minOSVersion;
		}
		if(!empty($maxOSVersion)  && !in_array($maxOSVersion, $versionConf)){
			$newVersion[] = $maxOSVersion;
		}
		if(!empty($newVersion)){
			$versionConf = array_merge($versionConf,$newVersion);
			$versionCodeArr = array();
			foreach ($versionConf as $k => $v_version){
				$versionCodeArr[$v_version] = OfferSyncHelper::getOsVersionCode($v_version);
			}
			asort($versionCodeArr);
			$versionConf = array_keys($versionCodeArr);
		}
		$os_version = array();
		foreach($versionConf as $k => $v){
			$os_version[] = $v;
			if($minOSVersion == $v){ // limit min version
				$os_version = array($v);
			}
			if($maxOSVersion == $v){ //limit max version
				break;
			}
		}
		$rz_version = implode(',', $os_version);
		return $rz_version;
	}
	
	/**
	 * get_filetree
	 * @param unknown $path
	 * @return boolean|Ambigous <multitype:, multitype:unknown >
	 */
	public static function get_filetree($path){
		if(empty($path)){
			return false;
		}
		$tree = array();
		foreach(glob($path.'/*') as $single){
			if(is_dir($single)){
				$tree = array_merge($tree,self::get_filetree($single));
			}
			else{
				$tree[] = $single;
			}
		}
		return $tree;
	}
	
	/**
	 * recursive remove_directory
	 * @param unknown $dir
	 */
	public static function remove_directory($dir,$printlog = 0){
		if(empty($dir)){
			return false;
		}
		if(!is_dir($dir)){
			echo "Error: ".$dir." > is not dir error\n";
			return false;
		}
		if($handle = opendir($dir)){
			while(false !== ($item = readdir($handle))){
				if($item != "." && $item !=".."){
					if(is_dir($dir.'/'.$item)){
						self::remove_directory($dir.'/'.$item);
					}else{
						unlink($dir.'/'.$item);
						if($printlog){
							echo "removing ".$dir."/".$item."\n";
						}
					}
				}
			}
			closedir($handle);
			rmdir($dir);
			if($printlog){
				echo "removing ".$dir."\n";
			}
		}
		return true;
	}
	
	/**
	 * commonWriteLog
	 * @param unknown $logTypeFolder
	 * @param string $subLogTypeFolder
	 * @param string $logMessages
	 * @param string $logDataType : string、array、json
	 * @param string $fileAppEnd : default write log do not rewrite log
	 * @param string $writeTime : 2015-12-21
	 * @return boolean
	 */
	public static function commonWriteLog($logTypeFolder,$subLogTypeFolder = '',$logMessages = '',$logDataType='',$fileAppEnd = 1,$writeTime = '',$debugToPrint = 1){
		if(empty($logTypeFolder)){
		    echo __CLASS__."->commonWriteLog param logTypeFolder is null error.\n";
		    return false;
		}
		if(!in_array($logDataType, array('string','array','json')) && !empty($logDataType)){
		    echo __CLASS__."->commonWriteLog logDataType not in (string、array、json) error.\n";
		    return false;
		}
		$systemConf = SyncConf::getSyncConf ( 'system' );
		self::$syncLogPath = $systemConf['sync_log_path'];
		$dateYM = date ( 'Y-m' );
		$dateYMD = date ( 'Y-m-d' );
		$dateYMDH = date ( 'Y-m-d-H' );
		if(!empty($writeTime)){
		    $dateYM = date ( 'Y-m' ,strtotime($writeTime));
		    $dateYMD = date ( 'Y-m-d',strtotime($writeTime) );
		    $dateYMDH = date ( 'Y-m-d-H',strtotime($writeTime));
		}
		if(empty($subLogTypeFolder)){
			$logPath = self::$syncLogPath . $logTypeFolder.'/' . $dateYM . '/' . $dateYMD . '/';
		}else{
			$logPath = self::$syncLogPath . $logTypeFolder.'/' . $subLogTypeFolder .'/'. $dateYM . '/' . $dateYMD . '/';
		}
		if (! is_dir ( $logPath )) {
		    mkdir ( $logPath, 0777, true );
		}
		$logFile = $dateYMDH . '.log';
		$logRowStr = '';
		if(is_array($logMessages) && $logDataType == 'array' && !empty($logMessages)){
		    foreach ( $logMessages as $k => $v ) {
		    	if(is_array($v)){
		    		$logRowStr .= $k.':'.json_encode($v) . "\t";
		    	}else{
		    		$logRowStr .= $k.':'.$v . "\t";
		    	}
		    }
		    trim ( $logRowStr, "\t" );
		    $logRowStr .= "\n";
		}elseif($logDataType == 'json' && !empty($logMessages)){
			$logRowStr = json_encode($logMessages);
			$logRowStr .= "\t";
			trim ( $logRowStr, "\t" );
			$logRowStr .= "\n";
		}elseif($logDataType == 'string' && !empty($logMessages)){
			$logRowStr = $logMessages;
			$logRowStr .= "\t";
			trim ( $logRowStr, "\t" );
			$logRowStr .= "\n";
			if(defined('SYNC_DEBUG')){
				$ifDebug = SYNC_DEBUG;
				if(!empty($ifDebug) && $debugToPrint){
					echo date('Y-m-d_H:i:s')."\t".$logRowStr;
				}
			}
		}
		if(empty($logRowStr)){
		    echo __CLASS__."->commonWriteLog there is no log message error.\n";
		    return false;
		}
		if($fileAppEnd){
		    file_put_contents ( $logPath . $logFile, $logRowStr, FILE_APPEND );
		}else{
		    file_put_contents ( $logPath . $logFile, $logRowStr);
		}
		chmod($logPath . $logFile, 0777);
		return true;
	}
	
	/**
	 * 3s获取正确direct_url方法,3s 目前campaign_type 逻辑：return false是apk单子,if return not null direct_url is gp campaign.
	 * @param unknown $camp_info
	 * @return multitype:string mixed
	 */
	public static function get3sDirectUrl($direct_url){
	    if(empty($direct_url)){
	        return false;
	    }
	    $out = array();
	    $direct_url = preg_replace('/\{\s*@\s*info\.appsflyerid\s*@\s*\}/', '{clickid}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*info\.clickid\s*@\s*\}/', '{clickid}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*info\.query\.ip\s*@\s*\}/', '{ip}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*info\.ip\s*@\s*\}/', '{ip}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*parseInt\(Date\.now\(\)\/1000\)\s*@\s*\}/', '{timestamp}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*info\.sub_channel\s*@\s*\}/', '{sub_channel}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*info\.country\s*@\s*\}/', '{country}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*info\.query\.mb_campid\s*@\s*\}/', $camp_info['uuid'], $direct_url);
	    $direct_url = preg_replace('/\{\s*@\s*info\.query\.mb_cb\s*@\s*\}/', '{mb_cb}', $direct_url);
	    //正式
 	    $direct_url = preg_replace('/\{\s*@\s*info\.sub_channel\.replace\(\/\\\_\/(ig)?\s*,\s*[\'|"]\.[\'|"]\s*\)\s*@\s*\}/', '{sub_channel1}', $direct_url);

	    $direct_url = preg_replace('/\{\s*@.*adn_devid.*@\s*\}/', '{devId}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@.*adn_idfa.*@\s*\}/', '{idfa}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@.*adn_gaid.*@\s*\}/', '{gaid}', $direct_url);
	    $direct_url = preg_replace('/\{\s*@.*@\s*\}/', '', $direct_url);
	    $mode = strstr($direct_url, '{clickid}') !== false ? 'clickid' : 'sdk';
	    if($mode != 'clickid' || empty($direct_url)){
	        return false;
	    }
	    return $direct_url;
	}
	
	/**
	 * handle xml function
	 * @param unknown $fname
	 * @return Ambigous <multitype:multitype: , string>
	 */
	public static function xml2array($fname){
	    //simplexml_load_string
	    $sxi = new \SimpleXmlIterator($fname, null, true);
	    return self::sxiToArray($sxi);
	}
	/**
	 * handle xml function , for xml2array function use.
	 * @param unknown $sxi
	 * @return Ambigous <multitype:multitype: , string>
	 */
	public static function sxiToArray($sxi){
	    $a = array();
	    for( $sxi->rewind(); $sxi->valid(); $sxi->next() ) {
	        if(!array_key_exists($sxi->key(), $a)){
	            $a[$sxi->key()] = array();
	        }
	        if($sxi->hasChildren()){
	            $a[$sxi->key()][] = self::sxiToArray($sxi->current());
	        }
	        else{
	            $a[$sxi->key()][] = strval($sxi->current());
	        }
	    }
	    return $a;
	}
	
    /**
     * echo function
     * @param unknown $class
     * @param unknown $function
     * @param unknown $logContent
     * @param number $logType 1：Error 2:Normal
     * @return boolean
     */
	public static function syncEcho($class = '',$function = '',$logContent,$logType = 1){
	    if(empty($logContent) || empty($logType)){
	        return false;
	    }
	    if(!in_array($logType, array(1,2))){
	        echo "Error: ".__CLASS__." ".__FUNCTION__." logType 1:Error echo type 2:Common echo type\n";
	        return false;
	    }
	    if($logType == 1){
	        $logType = 'Error';
	    }elseif($logType == 2){
	        $logType = 'Normal';
	    }
	    if(empty($class) && empty($function)){
	        $echoStr = $logType.': '.$logContent." time: ".date('Y-m-d H:i:s')."\n";
	    }else{
	        $echoStr = $logType.': '.$class.'=>'.$function.":: ".$logContent." time: ".date('Y-m-d H:i:s')."\n";
	    }
	    
	    if(!empty($echoStr)){
	        echo $echoStr;
	        return true;
	    }
	    return false;
	}
	
	/**
	 * debug echo or special echo 
	 * @param unknown $str
	 * @param number $needTime
	 */
	public static function xEcho($str,$onlyDebugEcho = 0,$needTime = 0){
	    $isDebug = SYNC_OFFER_DEBUG;
	    if($onlyDebugEcho && empty($isDebug)){ //only debug model can echo.
	        return false;
	    }
	    if(empty($needTime)){
	        echo $str."\n";
	    }else{
	        echo $str." ".date('Y-m-d H:i:s')."\n";
	    }
	}
	
	/**
	 * check url if right
	 * @param unknown $url
	 * @return boolean
	 */
	public static function checkUrlIfRight($url){
	    if(empty($url) || substr($url, 0,4) != 'http'){
	        return false;
	    }
	    return true;
	}
	
	/**
	 * get advertier obj name
	 * @param unknown $subNetWork
	 * @return string
	 */
	public static function getAdvertiserObjName($subNetWork){
	    $ifHasOffer = substr($subNetWork, -8);
	    $advertiserName = '';
	    $advertiserName = '\Advertiser\\'.$subNetWork.'SyncAdvertiser';
	    $hasOfferStr = 'HasOffer';
	    if($ifHasOffer == $hasOfferStr){ //if hasoffer advertiser special logic
//	        $advertiserName = '\Advertiser\\'.$hasOfferStr.'SyncAdvertiser';
	    }
	    if($subNetWork == '3s'){
	        $subNetWork = 'ThreeS';
	        $advertiserName = '\Advertiser\\'.$subNetWork.'SyncAdvertiser';
	    }
	    return $advertiserName;
	}
	/**
	 * checkArrIfHaveEmpty
	 * @param unknown $arr
	 * @return boolean
	 */
	static function checkArrIfHaveEmpty($arr){
	    if(empty($arr)){
	        return true;
	    }
	    foreach ($arr as $k => $v){
	        if(empty($v)){
	            return true;
	        }
	    }
	    return false;
	}
	/**
	 * checkArrIfAllEmpty
	 * @param unknown $arr
	 * @return boolean
	 */
	static function checkArrIfAllEmpty($arr){
	    if(empty($arr)){
	        return true;
	    }
	    foreach ($arr as $k => $v){
	        if(!empty($v)){
	            return false;
	        }
	    }
	    return true;
	}
		
	/**
	 * get url params
	 * @param unknown $url
	 * @return multitype:
	 */
	static function getUrlParams($url){
	    $parseUrl = parse_url($url);
	    $parStrArr = array();
	    parse_str($parseUrl['query'],$parStrArr);
	    return $parStrArr;
	}
	
	/**
	 * compare arr1 key value same as arr2 
	 * @param unknown $arr1
	 * @param unknown $arr2
	 * @return boolean
	 */
	static function checkTwoArrIfKeyValSame($arr1,$arr2){
	    foreach ($arr1 as $k => $v){
	        if(!isset($arr2[$k])){
	            return false;
	        }
	        if($arr1[$k] != $arr2[$k]){
	            return false;
	        }
	    }
	    return true;
	}
	
	static function syncStatus($statusInfo){
	    if(empty($statusInfo)){
	        return false;
	    }
	    //$insRz = $syncStatusObj->insertData($statusInfo,$sendMailTime); //把运行状态信息录入mongo
	    self::upKibana($statusInfo);
	}
	
	static function upKibana($statusInfo){
	    if(empty($statusInfo)){
	        return false;
	    }
	    $commonSyncHelperObj = new CommonHelperSyncApi();
	    $statusInfo['memory_get_usage'] = trim($statusInfo['memory_get_usage'],'M');
	    $statusInfo['memory_get_usage'] = trim($statusInfo['memory_get_peak_usage'],'M');
	    $statusInfo['postDate'] = date('c');
	    $kibanaData = '';
	    if(SYNC_DEBUG){
	        $index = 'sync-debug-offer-status-'.date('Y.m.d');
	    }else{
	        $index = 'sync-online-offer-status-'.date('Y.m.d');
	    }
	    $id = date('YmdHi') . '_' . $statusInfo['source'];
	    $kibanaData .= "{ \"index\" : { \"_index\" : \"$index\", \"_type\" : \"sync_offer_info\", \"_id\" : \"$id\" } }\n";
	    $kibanaData .= json_encode($statusInfo)."\n";
	    $upRz = $commonSyncHelperObj->updateKibana($kibanaData);
	}
	
	/**
	 * check if arr value is all number
	 * @param unknown $arr
	 * @return boolean
	 */
	static function checkArrAllIfNum($arr){
	    if(empty($arr)){
	        return false;
	    }
	    foreach ($arr as $k => $v){
	        if(!is_numeric($v)){
	            return false;
	        }
	    }
	    return true;
	}

	/**
	 * check if arr have value if in another arr value
	 */
	static function checkArrHavValIfInAnotherArrVal($chkArr,$inArr){
	    if(empty($chkArr) || empty($inArr)){
	        return false;
	    }
	    foreach($chkArr as $k => $v){
	        if(in_array($v, $inArr)){
	            return true;
	        }
	    }
	    return false;
	}
	
	/**
	 * check if arr all value if in another arr value
	 */
	static function checkArrAllValIfInAnotherArrVal($chkArr,$inArr){
	    if(empty($chkArr) || empty($inArr)){
	        return false;
	    }
	    foreach($chkArr as $k => $v){
	        if(!in_array($v, $inArr)){
	            return false;
	        }
	    }
	    return true;
	}
	
	/**
	 * get android or ios packagename
	 * @param unknown $url
	 * @param unknown $platform
	 * @return Ambigous <>|unknown|boolean
	 */
	static function getPreviewUrlPackageName($url,$platform){
	    $params = array();
	    if(strtolower($platform) == 'android'){
	        $params = self::getUrlParams($url);
	        if(!empty($params['id']) && !is_numeric($params['id'])){
	            return $params['id'];
	        }
	    }elseif(strtolower($platform) == 'ios'){
	        $params = pathinfo($url);
	        $pos = strpos($params['filename'], '?');
	        $iosPackageName = str_replace(substr($params['filename'],$pos),'', $params['filename']);
	        if(empty($iosPackageName) && !empty($params['filename'])){
	            return $params['filename'];
	        }else{
	            return $iosPackageName;
	        }
	    }
	    return false;
	}
	
	/**
	 * check offer app_name and app_desc if messy code.
	 * @return array not empty means is mess code msg,otherwise is not mess code msg.
	 */
	static function checkMessyCode($msg,$geoArr = array()){
	    //???,ã、š、¤、¶、å、ç、â、®、Ÿ
	    //æ 丹麦DK、挪威NO、冰岛IS
	    //ä 德国(DE)

	    //first check 
	    $rz = array();
	    $chkStr = array("???","ã","¤","¶","å","ç","â","Ÿ");
	    foreach ($chkStr as $k => $v){
	        $ifContain = self::checkContainStr($msg, $v);
	        if($ifContain){
	            $rz['contain'] = $v;
	            return $rz;
	        }
	    }
	    if(empty($geoArr)){
	        echo "param geoArr null error\n";
	        return $rz;
	    }
	    //second check
	    $chkStrToGeo = array(
	        "æ" => array(
	               "DK",
	               "NO",
	               "IS",
	        ),
	        "ä" => array(
	               "DE",
	        ),
	        "š" => array(
	               "LT",
	        ),
	    );
	    foreach ($chkStrToGeo as $k_v => $v_geo){
	        $ifContain = self::checkContainStr($msg, $k_v);
	        if($ifContain){
	            $jiaoJi = array_intersect($v_geo, $geoArr);
	            if(empty($jiaoJi)){
	                $rz['contain'] = $k_v;
	                $rz['map_geo'] = implode(',', $v_geo); // means offer contain messy code and the mess code country not in offer country ,so it is the mess code offer. 
	                return $rz;
	            }
	        }
	        
	    }
	    return $rz;
	}
	
    /**
     * check one array value if in another array
     * @param unknown $chkArr
     * @param unknown $beChkArr
     * @return unknown|boolean
     */
	static function checkArrValInOtherArr($chkArr,$beChkArr){
	    foreach ($chkArr as $k => $v){
	        if(in_array($v, $beChkArr)){
	            return $v;
	        }
	    }
	    return false;
	}
	
	/**
	 * check if str contain sub str
	 * @param unknown $chkStr
	 * @param unknown $chkSubStr
	 * @return boolean
	 */
	static function checkContainStr($chkStr,$chkSubStr){
	    if(strpos($chkStr, $chkSubStr) !== false){
	        return true;
	    }
	    return false;
	}
	
	/**
	 * trackingUrlHandel
	 */
	static function trackingUrlHandel($url,$appendStr){
		if(empty($appendStr)){
			return $url;
		}
		$url = trim($url,"&");
		$appendStr = htmlspecialchars_decode($appendStr,ENT_QUOTES);
		$appendStr = trim($appendStr,"&");
		$newUrl = $url."&".$appendStr;
		return $newUrl;
	}
}
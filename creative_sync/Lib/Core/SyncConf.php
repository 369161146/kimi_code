<?php
namespace Lib\Core;
use Model\ApiConfigModel;
class SyncConf{
	
    public static $apiConfigModel;
	function __construct(){
	    
	}
    
	private static $conf = array();
	
	public static function getSyncConf($name){
		if(!isset(self::$conf[$name])){
			self::loadSyncConf($name);
		}
		return self::$conf[$name];
	}
	
	private static function loadSyncConf($name){
		if(!$name) return false;
		$apiConfigIfGetMongo = 1; //on off get mongo config
		if($apiConfigIfGetMongo == 1){
		    if($name == 'apiSync'){
		        $apiConfig = self::getApiConfigMongo();
		        if(!empty($apiConfig)){
		            self::$conf[$name] = $apiConfig;
		        }
		        return;
		    }
		}
		$testSyncConf = '../../../../../creative_sync_local_conf/'.$name.'.php';
		$localSyncConf = ROOT_OUT_DIR.'/creative_sync_local_conf/'.$name.'.php';
		$syncConf = ROOT_DIR.'/Conf/'.$name.'.php';
		if(file_exists($testSyncConf)){
			self::$conf[$name] = include $testSyncConf;
		}elseif(file_exists($localSyncConf)){
			self::$conf[$name] = include $localSyncConf;
		}elseif(file_exists($syncConf)){
			self::$conf[$name] = include $syncConf;
		}else{
			throw new \Exception('no sync conf find.');
		}
	}
	
	private static function getApiConfigMongo(){
	    if(empty(self::$apiConfigModel)){
	        self::$apiConfigModel = new ApiConfigModel();
	    }
	    $rz = self::$apiConfigModel->getMongoApiConfig();
	    $formatApiConfig = array();
	    foreach ($rz as $k => $v){
	        $offer_source = $v['offer_source'];
	        $sub_network = $v['sub_network'];
	        unset($v['_id']);
	        unset($v['offer_source']);
	        unset($v['sub_network']);
	        unset($v['time']);
	        $formatApiConfig[$offer_source][$sub_network] = $v;
	    }
	    return $formatApiConfig;
	}
	
	public static function getDirectMongoConf(){
	    if(empty(self::$apiConfigModel)){
	        self::$apiConfigModel = new ApiConfigModel();
	    }
	    $rz = self::$apiConfigModel->getMongoApiConfig();
	    return $rz;
	}
}
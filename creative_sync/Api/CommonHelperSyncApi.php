<?php
namespace Api;
use Lib\Core\SyncApi;
use Lib\Core\SyncConf;
use Helper\AdwaysSyncApiHelper;
use Helper\CommonSyncHelper;
class CommonHelperSyncApi extends SyncApi{
    public static $specialApi;
    
	function __construct(){
	    $specialApiArr = SyncConf::getSyncConf('specialApi');
	    if(SYNC_DEBUG){
	        self::$specialApi = $specialApiArr['debug'];
	    }else{
	        self::$specialApi = $specialApiArr['online'];
	    }
	}
	
	function updateKibana($data){
	    $apiUrl = self::$specialApi['kibana_api'];
	    if(empty($apiUrl)){
	        return false;
	    }
	    try{
	        $rz = $this->kibanaPostCurl($apiUrl, $data, 15);
	    }catch(\Exception $e){
	        CommonSyncHelper::syncEcho(__CLASS__,__FILE__,$e->getMessage());
	    }
	    return $rz;
	}
}
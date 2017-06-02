<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
class CreativeMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
	public function __construct(){
		$this->mongoObj = $this->getCommonMongo('sync_campaign');
		self::$collection = 'creative_list';
	}
    
    public function getMongoByPackageName($trace_app_id,$platform,$type){
        if(empty($trace_app_id) || empty($type) || empty($platform)){
            return false;
        }
    	$conds = array(
    	    'trace_app_id' => $trace_app_id,
    	    'platform' => $platform,
    	    'type' => $type,
    	);
    	$rz = $this->mongoObj->where($conds)->get(self::$collection);
    	return $rz;
    }
    
}
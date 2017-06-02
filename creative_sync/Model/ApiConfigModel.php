<?php
namespace Model;
use Lib\Core\SyncDB;
use Lib\Core\SyncConf;
class ApiConfigModel extends SyncDB{
    public $mongoObj;
    public static $collection;
	public function __construct(){
		$this->mongoObj = $this->getApiConfigMongo('new_adn');
		self::$collection = 'api_config';
	}
    
    public function getMongoApiConfig() {
        $rz = $this->mongoObj->orderBy(array('advertiser_id'=>'asc'))->get(self::$collection);
        return $rz;
    }
    
}
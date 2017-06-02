<?php
namespace Pool;
use \Pheanstalk\Pheanstalk;
use Lib\Core\SyncConf;
class PheanstalkSyncPool{
	
	private static $instance;
	
	private function __construct(){

	}
	
	public static function getInstance(){
		$conf = SyncConf::getSyncConf('queue');
		if(empty(self::$instance)){
			self::$instance = new Pheanstalk($conf['sync']['host']);
		}
		return self::$instance;
	}
}
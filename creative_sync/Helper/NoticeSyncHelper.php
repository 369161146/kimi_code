<?php
namespace Helper;
use Lib\Core\SyncHelper;
use Lib\Core\SyncConf;
class NoticeSyncHelper extends SyncHelper{
	
	public $send_mail_status;
	
	function __construct(){
		$systemConf = SyncConf::getSyncConf ( 'system' );
		$this->send_mail_status = $systemConf ['email']['send_mail_status'];
	}
	
	
}